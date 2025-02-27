/*
  +----------------------------------------------------------------------+
  | teds extension for PHP                                               |
  | See COPYING file for further copyright information                   |
  +----------------------------------------------------------------------+
  | Author: Tyson Andre <tandre@php.net>                                 |
  +----------------------------------------------------------------------+
*/

/* This is based on spl_fixedarray.c but has lower overhead (when size is known) and is more efficient to endeque and remove elements from the end of the list */
#ifdef HAVE_CONFIG_H
#include "config.h"
#endif

#include "php.h"
#include "php_ini.h"
#include "ext/standard/info.h"
#include "zend_exceptions.h"

#include "php_teds.h"
#include "teds.h"
#include "teds_deque_arginfo.h"
#include "teds_deque.h"
// #include "ext/spl/spl_functions.h"
#include "ext/spl/spl_exceptions.h"
#include "ext/spl/spl_iterators.h"
#include "ext/json/php_json.h"

#include <stdbool.h>

zend_object_handlers teds_handler_Deque;
zend_class_entry *teds_ce_Deque;

/* This is a placeholder value to distinguish between empty and uninitialized Deque instances.
 * Compilers require at least one element. Make this constant - reads/writes should be impossible. */
static const zval empty_entry_list[1];

typedef struct _teds_deque_entries {
	size_t size;
	size_t capacity;
	size_t offset;
	/** This is a circular buffer with an offset, size, and capacity */
	zval *circular_buffer;
} teds_deque_entries;

#if ZEND_DEBUG
static void DEBUG_ASSERT_CONSISTENT_DEQUE(const teds_deque_entries *array) {
	ZEND_ASSERT(array->size <= array->capacity);
	ZEND_ASSERT(array->offset < array->capacity || array->capacity == 0);
	ZEND_ASSERT(array->capacity == 0 || (array->circular_buffer != NULL && array->circular_buffer != empty_entry_list));
	ZEND_ASSERT(array->circular_buffer != NULL || (array->size == 0 && array->offset == 0 || array->capacity == 0));
}
#else
#define DEBUG_ASSERT_CONSISTENT_DEQUE(array) do {} while(0)
#endif

static zend_always_inline zval* teds_deque_get_offset_entries(const teds_deque_entries *array) {
	DEBUG_ASSERT_CONSISTENT_DEQUE(array);
	return &array->circular_buffer[array->offset];
}

static zend_always_inline zval* teds_deque_get_entry_at_offset(const teds_deque_entries *array, size_t offset) {
	DEBUG_ASSERT_CONSISTENT_DEQUE(array);
	ZEND_ASSERT(offset < array->size);
	return &array->circular_buffer[(array->offset + offset) % array->capacity];
}

typedef struct _teds_deque {
	teds_deque_entries		array;
	zend_object				std;
} teds_deque;

/* Used by InternalIterator returned by Deque->getIterator() */
typedef struct _teds_deque_it {
	zend_object_iterator intern;
	zend_long            current;
} teds_deque_it;

static teds_deque *teds_deque_from_object(zend_object *obj)
{
	return (teds_deque*)((char*)(obj) - XtOffsetOf(teds_deque, std));
}

#define Z_DEQUE_P(zv)  teds_deque_from_object(Z_OBJ_P((zv)))

/* Helps enforce the invariants in debug mode:
 *   - if size == 0, then circular_buffer == NULL
 *   - if size > 0, then circular_buffer != NULL
 *   - size is not less than 0
 */
static bool teds_deque_entries_empty_size(const teds_deque_entries *array)
{
	DEBUG_ASSERT_CONSISTENT_DEQUE(array);
	return array->size == 0;
}

static bool teds_deque_entries_empty_capacity(const teds_deque_entries *array)
{
	DEBUG_ASSERT_CONSISTENT_DEQUE(array);
	return array->capacity == 0;
}

static bool teds_deque_entries_uninitialized(teds_deque_entries *array)
{
	DEBUG_ASSERT_CONSISTENT_DEQUE(array);
	return array->circular_buffer == NULL;
}

static void teds_deque_entries_init_from_array(teds_deque_entries *array, zend_array *values)
{
	zend_long size = zend_hash_num_elements(values);
	array->offset = 0;
	array->size = 0; /* reset size in case ecalloc() fails */
	array->capacity = 0; /* reset size in case ecalloc() fails */
	if (size > 0) {
		zval *val;
		zval *circular_buffer;
		int i = 0;

		array->circular_buffer = circular_buffer = safe_emalloc(size, sizeof(zval), 0);
		array->size = size;
		array->capacity = size;
		ZEND_HASH_FOREACH_VAL(values, val)  {
			ZEND_ASSERT(i < size);
			ZVAL_COPY(&circular_buffer[i], val);
			i++;
		} ZEND_HASH_FOREACH_END();
	} else {
		array->circular_buffer = (zval *)empty_entry_list;
	}
}

static void teds_deque_entries_init_from_traversable(teds_deque_entries *array, zend_object *obj)
{
	zend_class_entry *ce = obj->ce;
	zend_object_iterator *iter;
	zend_long size = 0, capacity = 0;
	array->size = 0;
	array->offset = 0;
	array->circular_buffer = NULL;
	zval *circular_buffer = NULL;
	zval tmp_obj;
	ZVAL_OBJ(&tmp_obj, obj);
	iter = ce->get_iterator(ce, &tmp_obj, 0);

	if (UNEXPECTED(EG(exception))) {
		return;
	}

	const zend_object_iterator_funcs *funcs = iter->funcs;

	if (funcs->rewind) {
		funcs->rewind(iter);
		if (UNEXPECTED(EG(exception))) {
			return;
		}
	}

	while (funcs->valid(iter) == SUCCESS) {
		if (EG(exception)) {
			break;
		}
		zval *value = funcs->get_current_data(iter);
		if (UNEXPECTED(EG(exception))) {
			break;
		}
		if (UNEXPECTED(EG(exception))) {
			break;
		}

		if (size >= capacity) {
			/* TODO: Could use countable and get_count handler to estimate the size of the array to allocate */
			if (circular_buffer) {
				capacity *= 2;
				circular_buffer = safe_erealloc(circular_buffer, capacity, sizeof(zval), 0);
			} else {
				capacity = 4;
				circular_buffer = safe_emalloc(capacity, sizeof(zval), 0);
			}
		}
		ZVAL_COPY_DEREF(&circular_buffer[size], value);
		size++;

		iter->index++;
		funcs->move_forward(iter);
		if (EG(exception)) {
			break;
		}
	}
	if (capacity > size) {
		/* Shrink allocated value to actual required size */
		circular_buffer = erealloc(circular_buffer, size * sizeof(zval));
	}

	array->size = size;
	array->capacity = size;
	array->circular_buffer = circular_buffer;
	if (iter) {
		zend_iterator_dtor(iter);
	}
}

static void teds_deque_entries_copy_ctor(teds_deque_entries *to, const teds_deque_entries *from)
{
	zend_long size = from->size;
	to->size = 0; /* reset size in case emalloc() fails */
	to->capacity = 0;
	to->offset = 0;
	if (!size) {
		to->circular_buffer = (zval *)empty_entry_list;
		return;
	}

	to->circular_buffer = safe_emalloc(size, sizeof(zval), 0);
	to->size = size;
	to->capacity = size;

	// account for offsets
	zval *const from_buffer_start = from->circular_buffer;
	zval *from_begin = &from_buffer_start[from->offset];
	zval *const from_end = &from_buffer_start[from->capacity];

	zval *p_dest = to->circular_buffer;
	zval *p_end = p_dest + size;
	do {
		if (from_begin == from_end) {
			from_begin = from_buffer_start;
		}
		ZVAL_COPY(p_dest, from_begin);
		from_begin++;
		p_dest++;
	} while (p_dest < p_end);
}

/* Destructs and frees contents but not the array itself.
 * If you want to re-use the array then you need to re-initialize it.
 */
static void teds_deque_entries_dtor(teds_deque_entries *array)
{
	if (teds_deque_entries_empty_capacity(array)) {
		return;
	}
	size_t remaining = array->size;
	zval *const circular_buffer = array->circular_buffer;
	if (remaining > 0) {
		zval *const end = circular_buffer + array->capacity;
		zval *p = circular_buffer + array->offset;
		ZEND_ASSERT(p < end);
		array->circular_buffer = NULL;
		array->offset = 0;
		array->size = 0;
		array->capacity = 0;
		do {
			if (p == end) {
				p = circular_buffer;
			}
			zval_ptr_dtor(p);
			p++;
			remaining--;
		} while (remaining > 0);
	}
	efree(circular_buffer);
}

static HashTable* teds_deque_get_gc(zend_object *obj, zval **table, int *n)
{
	teds_deque *intern = teds_deque_from_object(obj);

	// TODO: Check for overflow
	const size_t size = intern->array.size;
	const size_t capacity = intern->array.capacity;
	const size_t offset = intern->array.offset;
	zval * const circular_buffer = intern->array.circular_buffer;
	if (capacity - offset >= size) {
		*table = &circular_buffer[offset];
		*n = (int)size;
		return NULL;
	}

	// Based on spl_dllist.c
	zend_get_gc_buffer *gc_buffer = zend_get_gc_buffer_create();
	for (size_t i = offset; i < capacity; i++) {
		zend_get_gc_buffer_add_zval(gc_buffer, &circular_buffer[i]);
	}

	for (size_t i = 0, len = offset + size - capacity; i < len; i++) {
		zend_get_gc_buffer_add_zval(gc_buffer, &circular_buffer[len]);
	}

	zend_get_gc_buffer_use(gc_buffer, table, n);
	// Returning the object's properties is redundant if dynamic properties are not allowed,
	// and this can't be subclassed.
	return NULL;
}

static HashTable* teds_deque_get_properties(zend_object *obj)
{
	teds_deque *intern = teds_deque_from_object(obj);
	HashTable *ht = zend_std_get_properties(obj);

	DEBUG_ASSERT_CONSISTENT_DEQUE(&intern->array);

	/* Re-initialize properties array */
	if (!intern->array.size && !zend_hash_num_elements(ht)) {
		/* Nothing to add, update, or remove. */
		return ht;
	}

	// Note that destructors may mutate the original array,
	// so we fetch the size and circular buffer each time to avoid invalid memory accesses.
	for (size_t i = 0; i < intern->array.size; i++) {
		zval *elem = teds_deque_get_entry_at_offset(&intern->array, i);
		Z_TRY_ADDREF_P(elem);
		zend_hash_index_update(ht, i, elem);
	}
	const size_t properties_size = zend_hash_num_elements(ht);
	if (UNEXPECTED(properties_size > intern->array.size)) {
		for (size_t i = intern->array.size; i < properties_size; i++) {
			zend_hash_index_del(ht, i);
		}
	}

	return ht;
}

static void teds_deque_free_storage(zend_object *object)
{
	teds_deque *intern = teds_deque_from_object(object);
	teds_deque_entries_dtor(&intern->array);
	zend_object_std_dtor(&intern->std);
}

static zend_object *teds_deque_new_ex(zend_class_entry *class_type, zend_object *orig, bool clone_orig)
{
	teds_deque *intern;

	intern = zend_object_alloc(sizeof(teds_deque), class_type);
	/* This is a final class */
	ZEND_ASSERT(class_type == teds_ce_Deque);

	zend_object_std_init(&intern->std, class_type);
	object_properties_init(&intern->std, class_type);
	intern->std.handlers = &teds_handler_Deque;

	if (orig && clone_orig) {
		teds_deque *other = teds_deque_from_object(orig);
		teds_deque_entries_copy_ctor(&intern->array, &other->array);
	} else {
		intern->array.circular_buffer = NULL;
	}

	return &intern->std;
}

static zend_object *teds_deque_new(zend_class_entry *class_type)
{
	return teds_deque_new_ex(class_type, NULL, 0);
}


static zend_object *teds_deque_clone(zend_object *old_object)
{
	zend_object *new_object = teds_deque_new_ex(old_object->ce, old_object, 1);

	zend_objects_clone_members(new_object, old_object);

	return new_object;
}

static int teds_deque_count_elements(zend_object *object, zend_long *count)
{
	const teds_deque *intern = teds_deque_from_object(object);
	*count = intern->array.size;
	return SUCCESS;
}

/* Get the number of circular_buffer in this deque */
PHP_METHOD(Teds_Deque, count)
{
	ZEND_PARSE_PARAMETERS_NONE();

	const teds_deque *intern = Z_DEQUE_P(ZEND_THIS);
	RETURN_LONG(intern->array.size);
}

/* Get the capacity of this deque */
PHP_METHOD(Teds_Deque, capacity)
{
	ZEND_PARSE_PARAMETERS_NONE();

	const teds_deque *intern = Z_DEQUE_P(ZEND_THIS);
	RETURN_LONG(intern->array.capacity);
}

/* Create this from an iterable */
PHP_METHOD(Teds_Deque, __construct)
{
	zval *object = ZEND_THIS;
	zval* iterable;

	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_ITERABLE(iterable)
	ZEND_PARSE_PARAMETERS_END();

	teds_deque *intern = Z_DEQUE_P(object);

	if (UNEXPECTED(!teds_deque_entries_uninitialized(&intern->array))) {
		zend_throw_exception(spl_ce_RuntimeException, "Called Teds\\Deque::__construct twice", 0);
		/* called __construct() twice, bail out */
		RETURN_THROWS();
	}

	switch (Z_TYPE_P(iterable)) {
		case IS_ARRAY:
			teds_deque_entries_init_from_array(&intern->array, Z_ARRVAL_P(iterable));
			return;
		case IS_OBJECT:
			teds_deque_entries_init_from_traversable(&intern->array, Z_OBJ_P(iterable));
			return;
		EMPTY_SWITCH_DEFAULT_CASE();
	}
}

PHP_METHOD(Teds_Deque, getIterator)
{
	ZEND_PARSE_PARAMETERS_NONE();

	zend_create_internal_iterator_zval(return_value, ZEND_THIS);
}

static void teds_deque_it_dtor(zend_object_iterator *iter)
{
	zval_ptr_dtor(&iter->data);
}

static void teds_deque_it_rewind(zend_object_iterator *iter)
{
	((teds_deque_it*)iter)->current = 0;
}

static int teds_deque_it_valid(zend_object_iterator *iter)
{
	const teds_deque_it *iterator = (teds_deque_it*)iter;
	const teds_deque *object = Z_DEQUE_P(&iter->data);

	if (iterator->current >= 0 && iterator->current < object->array.size) {
		return SUCCESS;
	}

	return FAILURE;
}

static zval *teds_deque_read_offset_helper(teds_deque *intern, size_t offset)
{
	/* we have to return NULL on error here to avoid memleak because of
	 * ZE duplicating uninitialized_zval_ptr */
	if (UNEXPECTED(offset >= intern->array.size)) {
		zend_throw_exception(spl_ce_OutOfBoundsException, "Index out of range", 0);
		return NULL;
	} else {
		return teds_deque_get_entry_at_offset(&intern->array, offset);
	}
}

static zval *teds_deque_it_get_current_data(zend_object_iterator *iter)
{
	const teds_deque_it     *iterator = (teds_deque_it*)iter;
	teds_deque *object   = Z_DEQUE_P(&iter->data);

	zval *data = teds_deque_read_offset_helper(object, iterator->current);

	if (UNEXPECTED(data == NULL)) {
		return &EG(uninitialized_zval);
	} else {
		return data;
	}
}

static void teds_deque_it_get_current_key(zend_object_iterator *iter, zval *key)
{
	const teds_deque_it     *iterator = (teds_deque_it*)iter;
	const teds_deque *object   = Z_DEQUE_P(&iter->data);

	size_t offset = iterator->current;
	if (offset >= object->array.size) {
		ZVAL_NULL(key);
	} else {
		ZVAL_LONG(key, offset);
	}
}

static void teds_deque_it_move_forward(zend_object_iterator *iter)
{
	((teds_deque_it*)iter)->current++;
}

/* iterator handler table */
static const zend_object_iterator_funcs teds_deque_it_funcs = {
	teds_deque_it_dtor,
	teds_deque_it_valid,
	teds_deque_it_get_current_data,
	teds_deque_it_get_current_key,
	teds_deque_it_move_forward,
	teds_deque_it_rewind,
	NULL,
	NULL, /* get_gc */
};


zend_object_iterator *teds_deque_get_iterator(zend_class_entry *ce, zval *object, int by_ref)
{
	if (UNEXPECTED(by_ref)) {
		zend_throw_error(NULL, "An iterator cannot be used with foreach by reference");
		return NULL;
	}

	teds_deque_it *iterator = emalloc(sizeof(teds_deque_it));

	zend_iterator_init((zend_object_iterator*)iterator);

	ZVAL_OBJ_COPY(&iterator->intern.data, Z_OBJ_P(object));
	iterator->intern.funcs = &teds_deque_it_funcs;

	return &iterator->intern;
}

PHP_METHOD(Teds_Deque, __unserialize)
{
	HashTable *raw_data;
	zval *val;

	if (zend_parse_parameters(ZEND_NUM_ARGS(), "h", &raw_data) == FAILURE) {
		RETURN_THROWS();
	}

	const size_t num_entries = zend_hash_num_elements(raw_data);
	teds_deque *intern = Z_DEQUE_P(ZEND_THIS);
	if (UNEXPECTED(!teds_deque_entries_uninitialized(&intern->array))) {
		zend_throw_exception(spl_ce_RuntimeException, "Already unserialized", 0);
		RETURN_THROWS();
	}

	ZEND_ASSERT(intern->array.circular_buffer == NULL);

	zval *const circular_buffer = safe_emalloc(num_entries, sizeof(zval), 0);
	zval *it = circular_buffer;

	zend_string *str;

	ZEND_HASH_FOREACH_STR_KEY_VAL(raw_data, str, val) {
		if (UNEXPECTED(str)) {
			for (zval *deleteIt = circular_buffer; deleteIt < it; deleteIt++) {
				zval_ptr_dtor_nogc(deleteIt);
			}
			efree(circular_buffer);
			zend_throw_exception(spl_ce_UnexpectedValueException, "Teds\\Deque::__unserialize saw unexpected string key, expected sequence of values", 0);
			RETURN_THROWS();
		}
		ZVAL_COPY_DEREF(it++, val);
	} ZEND_HASH_FOREACH_END();
	ZEND_ASSERT(it <= circular_buffer + num_entries);

	intern->array.size = it - circular_buffer;
	intern->array.capacity = num_entries;
	intern->array.circular_buffer = circular_buffer;
}

static void teds_deque_entries_init_from_array_values(teds_deque_entries *array, zend_array *raw_data)
{
	size_t num_entries = zend_hash_num_elements(raw_data);
	if (num_entries == 0) {
		array->size = 0;
		array->capacity = 0;
		array->circular_buffer = NULL;
		return;
	}
	zval * circular_buffer = safe_emalloc(num_entries, sizeof(zval), 0);
	size_t actual_size = 0;
	zval *val;
	ZEND_HASH_FOREACH_VAL(raw_data, val) {
		ZVAL_COPY_DEREF(&circular_buffer[actual_size], val);
		actual_size++;
	} ZEND_HASH_FOREACH_END();

	ZEND_ASSERT(actual_size <= num_entries);

	array->circular_buffer = circular_buffer;
	array->size = actual_size;
	array->capacity = num_entries;
}

PHP_METHOD(Teds_Deque, __set_state)
{
	zend_array *array_ht;

	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_ARRAY_HT(array_ht)
	ZEND_PARSE_PARAMETERS_END();
	zend_object *object = teds_deque_new(teds_ce_Deque);
	teds_deque *intern = teds_deque_from_object(object);
	teds_deque_entries_init_from_array_values(&intern->array, array_ht);

	RETURN_OBJ(object);
}

static zend_array* teds_deque_to_new_array(const teds_deque_entries *array) {
	zval *const circular_buffer = array->circular_buffer;
	zval *p = circular_buffer + array->offset;
	zval *const end = circular_buffer + array->capacity;
	size_t len = array->size;
	zend_array *values = zend_new_array(len);
	/* Initialize return array */
	zend_hash_real_init_packed(values);

	/* Go through values and add values to the return array */
	ZEND_HASH_FILL_PACKED(values) {
		do {
			Z_TRY_ADDREF_P(p);
			ZEND_HASH_FILL_ADD(p);
			if (p == end) {
				p = circular_buffer;
			}
			len--;
		} while (len > 0);
	} ZEND_HASH_FILL_END();
	return values;
}

PHP_METHOD(Teds_Deque, __serialize)
{
	ZEND_PARSE_PARAMETERS_NONE();

	teds_deque *intern = Z_DEQUE_P(ZEND_THIS);

	if (teds_deque_entries_empty_size(&intern->array)) {
		RETURN_EMPTY_ARRAY();
	}
	/* Unlike FixedArray, there's no setSize, so there's no reason to delete indexes */

	RETURN_ARR(teds_deque_to_new_array(&intern->array));
}

PHP_METHOD(Teds_Deque, toArray)
{
	ZEND_PARSE_PARAMETERS_NONE();
	teds_deque *intern = Z_DEQUE_P(ZEND_THIS);
	size_t len = intern->array.size;
	if (!len) {
		RETURN_EMPTY_ARRAY();
	}
	RETURN_ARR(teds_deque_to_new_array(&intern->array));
}

static zend_always_inline void teds_deque_get_value_at_offset(zval *return_value, const zval *zval_this, zend_long offset)
{
	const teds_deque *intern = Z_DEQUE_P(zval_this);
	size_t len = intern->array.size;
	if (UNEXPECTED((zend_ulong) offset >= len)) {
		zend_throw_exception(spl_ce_OutOfBoundsException, "Index out of range", 0);
		RETURN_THROWS();
	}
	RETURN_COPY(teds_deque_get_entry_at_offset(&intern->array, offset));
}

PHP_METHOD(Teds_Deque, valueAt)
{
	zend_long offset;
	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_LONG(offset)
	ZEND_PARSE_PARAMETERS_END();

	teds_deque_get_value_at_offset(return_value, ZEND_THIS, offset);
}

PHP_METHOD(Teds_Deque, offsetGet)
{
	zval *offset_zv;
	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_ZVAL(offset_zv)
	ZEND_PARSE_PARAMETERS_END();

	zend_long offset;
	CONVERT_OFFSET_TO_LONG_OR_THROW(offset, offset_zv);

	teds_deque_get_value_at_offset(return_value, ZEND_THIS, offset);
}

PHP_METHOD(Teds_Deque, offsetExists)
{
	zval *offset_zv;
	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_ZVAL(offset_zv)
	ZEND_PARSE_PARAMETERS_END();

	zend_long offset;
	CONVERT_OFFSET_TO_LONG_OR_THROW(offset, offset_zv);

	const teds_deque *intern = Z_DEQUE_P(ZEND_THIS);
	const size_t len = intern->array.size;
	if ((zend_ulong) offset >= len) {
		RETURN_FALSE;
	}
	RETURN_BOOL(Z_TYPE_P(teds_deque_get_entry_at_offset(&intern->array, offset)) != IS_NULL);
}

static zval *teds_deque_read_dimension(zend_object *object, zval *offset_zv, int type, zval *rv)
{
	if (!offset_zv) {
		zend_throw_exception(spl_ce_RuntimeException, "[] operator not supported for Teds\\Deque", 0);
		return NULL;
	}

	zend_long offset;
	CONVERT_OFFSET_TO_LONG_OR_THROW_RETURN_NULLPTR(offset, offset_zv);

	const teds_deque *intern = teds_deque_from_object(object);

	if (offset < 0 || offset >= intern->array.size) {
		if (type != BP_VAR_IS) {
			zend_throw_exception(spl_ce_OutOfBoundsException, "Index out of range", 0);
		}
		return NULL;
	} else {
		return teds_deque_get_entry_at_offset(&intern->array, offset);
	}
}

static zend_always_inline void teds_deque_set_value_at_offset(zend_object *object, zend_long offset, zval *value) {
	const teds_deque *intern = teds_deque_from_object(object);
	zval *const ptr = &intern->array.circular_buffer[offset];

	size_t len = intern->array.size;
	if (UNEXPECTED((zend_ulong) offset >= len)) {
		zend_throw_exception(spl_ce_OutOfBoundsException, "Index out of range", 0);
		return;
	}
	zval tmp;
	ZVAL_COPY_VALUE(&tmp, ptr);
	ZVAL_COPY(ptr, value);
	zval_ptr_dtor(&tmp);
}

static void teds_deque_write_dimension(zend_object *object, zval *offset_zv, zval *value)
{
	if (!offset_zv) {
		zend_throw_exception(spl_ce_RuntimeException, "[] operator not supported for Teds\\Deque", 0);
		return;
	}

	zend_long offset;
	CONVERT_OFFSET_TO_LONG_OR_THROW(offset, offset_zv);

	const teds_deque *intern = teds_deque_from_object(object);
	if (offset < 0 || offset >= intern->array.size) {
		zend_throw_exception(spl_ce_RuntimeException, "Index invalid or out of range", 0);
		return;
	}
	teds_deque_set_value_at_offset(object, offset, value);
}

PHP_METHOD(Teds_Deque, indexOf)
{
	zval *value;
	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_ZVAL(value)
	ZEND_PARSE_PARAMETERS_END();

	const teds_deque *intern = Z_DEQUE_P(ZEND_THIS);
	const size_t len = intern->array.size;
	zval *circular_buffer = intern->array.circular_buffer;
	for (size_t i = 0; i < len; i++) {
		if (zend_is_identical(value, &circular_buffer[i])) {
			RETURN_LONG(i);
		}
	}
	RETURN_FALSE;
}

PHP_METHOD(Teds_Deque, contains)
{
	zval *value;
	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_ZVAL(value)
	ZEND_PARSE_PARAMETERS_END();

	const teds_deque *intern = Z_DEQUE_P(ZEND_THIS);
	const size_t len = intern->array.size;
	zval *circular_buffer = intern->array.circular_buffer;
	for (size_t i = 0; i < len; i++) {
		if (zend_is_identical(value, &circular_buffer[i])) {
			RETURN_TRUE;
		}
	}
	RETURN_FALSE;
}

PHP_METHOD(Teds_Deque, setValueAt)
{
	zend_long offset;
	zval *value;
	ZEND_PARSE_PARAMETERS_START(2, 2)
		Z_PARAM_LONG(offset)
		Z_PARAM_ZVAL(value)
	ZEND_PARSE_PARAMETERS_END();

	teds_deque_set_value_at_offset(Z_OBJ_P(ZEND_THIS), offset, value);
}

PHP_METHOD(Teds_Deque, offsetSet)
{
	zval                  *offset_zv, *value;

	ZEND_PARSE_PARAMETERS_START(2, 2)
		Z_PARAM_ZVAL(offset_zv)
		Z_PARAM_ZVAL(value)
	ZEND_PARSE_PARAMETERS_END();

	zend_long offset;
	CONVERT_OFFSET_TO_LONG_OR_THROW(offset, offset_zv);

	teds_deque_set_value_at_offset(Z_OBJ_P(ZEND_THIS), offset, value);
}

static void teds_deque_raise_capacity(teds_deque *intern, const zend_long new_capacity) {
	const size_t old_capacity = intern->array.capacity;
	ZEND_ASSERT(new_capacity > old_capacity);
	if (teds_deque_entries_empty_capacity(&intern->array)) {
		intern->array.circular_buffer = safe_emalloc(new_capacity, sizeof(zval), 0);
	} else if (intern->array.offset + intern->array.size <= old_capacity) {
		intern->array.circular_buffer = safe_erealloc(intern->array.circular_buffer, new_capacity, sizeof(zval), 0);
	} else {
		zval *const circular_buffer = intern->array.circular_buffer;
		const size_t size = intern->array.size;
		const size_t first_len = intern->array.capacity - intern->array.offset;
		zval *new_entries = safe_emalloc(new_capacity, sizeof(zval), 0);
		ZEND_ASSERT(first_len < size);
		memcpy(new_entries, circular_buffer + intern->array.offset, first_len * sizeof(zval));
		memcpy(new_entries + first_len, circular_buffer, (size - first_len) * sizeof(zval));
		efree(circular_buffer);
		intern->array.circular_buffer = new_entries;
		intern->array.offset = 0;
	}
	intern->array.capacity = new_capacity;
}

PHP_METHOD(Teds_Deque, pushBack)
{
	zval *value;

	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_ZVAL(value)
	ZEND_PARSE_PARAMETERS_END();

	teds_deque *intern = Z_DEQUE_P(ZEND_THIS);
	const size_t old_size = intern->array.size;
	const size_t old_capacity = intern->array.capacity;

	if (old_size >= old_capacity) {
		ZEND_ASSERT(old_size == old_capacity);
		teds_deque_raise_capacity(intern, old_size ? old_size * 2 : 4);
	}
	intern->array.size++;
	zval *dest = teds_deque_get_entry_at_offset(&intern->array, old_size);
	ZVAL_COPY(dest, value);
}

PHP_METHOD(Teds_Deque, pushFront)
{
	zval *value;

	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_ZVAL(value)
	ZEND_PARSE_PARAMETERS_END();

	teds_deque *intern = Z_DEQUE_P(ZEND_THIS);
	const size_t old_size = intern->array.size;
	const size_t old_capacity = intern->array.capacity;

	if (old_size >= old_capacity) {
		ZEND_ASSERT(old_size == old_capacity);
		const size_t new_capacity = old_size ? old_size * 2 : 4;
		teds_deque_raise_capacity(intern, new_capacity);
	}
	if (intern->array.offset == 0) {
		intern->array.offset = intern->array.capacity - 1;
	} else {
		intern->array.offset--;
	}
	intern->array.size++;
	DEBUG_ASSERT_CONSISTENT_DEQUE(&intern->array);
	zval *dest = &intern->array.circular_buffer[intern->array.offset];
	ZVAL_COPY(dest, value);
}

PHP_METHOD(Teds_Deque, popBack)
{
	ZEND_PARSE_PARAMETERS_NONE();

	teds_deque *intern = Z_DEQUE_P(ZEND_THIS);
	const size_t old_size = intern->array.size;
	if (old_size == 0) {
		zend_throw_exception(spl_ce_UnderflowException, "Cannot popBack from empty deque", 0);
		RETURN_THROWS();
	}

	zval *val = teds_deque_get_entry_at_offset(&intern->array, old_size - 1);
	intern->array.size--;
	RETURN_COPY_VALUE(val);
}

PHP_METHOD(Teds_Deque, popFront)
{
	ZEND_PARSE_PARAMETERS_NONE();

	teds_deque *intern = Z_DEQUE_P(ZEND_THIS);
	DEBUG_ASSERT_CONSISTENT_DEQUE(&intern->array);
	const size_t old_size = intern->array.size;
	if (old_size == 0) {
		zend_throw_exception(spl_ce_UnderflowException, "Cannot popFront from empty deque", 0);
		RETURN_THROWS();
	}

	intern->array.size--;
	const size_t old_offset = intern->array.offset;
	intern->array.offset++;
	if (intern->array.offset >= intern->array.capacity) {
		intern->array.offset = 0;
	}
	RETURN_COPY_VALUE(&intern->array.circular_buffer[old_offset]);
}

PHP_METHOD(Teds_Deque, offsetUnset)
{
	zval                  *offset_zv;

	if (zend_parse_parameters(ZEND_NUM_ARGS(), "z", &offset_zv) == FAILURE) {
		RETURN_THROWS();
	}
	// Diverge from SplFixedArray - null isn't the same thing as undefined.
	zend_throw_exception(spl_ce_RuntimeException, "Teds\\Deque does not support offsetUnset - elements must be set to null or removed by resizing", 0);
	RETURN_THROWS();
}

static void teds_deque_return_list(zval *return_value, const teds_deque *intern)
{
	// Can't use teds_convert_zval_list_to_php_array_list(return_value, intern->array.circular_buffer, intern->array.size) for deque.
	const size_t len = intern->array.size;
	if (!len) {
		RETURN_EMPTY_ARRAY();
	}

	zend_array *values = zend_new_array(len);
	/* Initialize return array */
	zend_hash_real_init_packed(values);

	zval *const from_buffer_start = intern->array.circular_buffer;
	zval *from_begin = &from_buffer_start[intern->array.offset];
	zval *const from_end = &from_buffer_start[intern->array.capacity];
	ZEND_ASSERT(from_begin <= from_end);
	/* Go through values and add values to the return array */
	ZEND_HASH_FILL_PACKED(values) {
		for (size_t i = 0; i < len; i++) {
			if (from_begin == from_end) {
				from_begin = from_buffer_start;
			}
			Z_TRY_ADDREF_P(from_begin);
			ZEND_HASH_FILL_ADD(from_begin);
			from_begin++;
		}
	} ZEND_HASH_FILL_END();
	RETURN_ARR(values);
}

PHP_METHOD(Teds_Deque, jsonSerialize)
{
	/* json_encoder.c will always encode objects as {"0":..., "1":...}, and detects recursion if an object returns its internal property array, so we have to return a new array */
	ZEND_PARSE_PARAMETERS_NONE();
	teds_deque *intern = Z_DEQUE_P(ZEND_THIS);
	teds_deque_return_list(return_value, intern);
}

PHP_MINIT_FUNCTION(teds_deque)
{
	teds_ce_Deque = register_class_Teds_Deque(zend_ce_aggregate, zend_ce_countable, php_json_serializable_ce, zend_ce_arrayaccess);
	teds_ce_Deque->create_object = teds_deque_new;

	memcpy(&teds_handler_Deque, &std_object_handlers, sizeof(zend_object_handlers));

	teds_handler_Deque.offset          = XtOffsetOf(teds_deque, std);
	teds_handler_Deque.clone_obj       = teds_deque_clone;
	teds_handler_Deque.count_elements  = teds_deque_count_elements;
	teds_handler_Deque.get_properties  = teds_deque_get_properties;
	teds_handler_Deque.get_gc          = teds_deque_get_gc;
	teds_handler_Deque.dtor_obj        = zend_objects_destroy_object;
	teds_handler_Deque.free_obj        = teds_deque_free_storage;

	teds_handler_Deque.read_dimension  = teds_deque_read_dimension;
	teds_handler_Deque.write_dimension = teds_deque_write_dimension;
	//teds_handler_Deque.unset_dimension = teds_deque_unset_dimension;
	//teds_handler_Deque.has_dimension   = teds_deque_has_dimension;

	teds_ce_Deque->ce_flags |= ZEND_ACC_FINAL | ZEND_ACC_NO_DYNAMIC_PROPERTIES;
	teds_ce_Deque->get_iterator = teds_deque_get_iterator;

	return SUCCESS;
}
