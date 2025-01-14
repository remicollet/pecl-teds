--TEST--
Teds\Deque iterator
--FILE--
<?php

function expect_throws(Closure $cb): void {
    try {
        $cb();
        echo "Unexpectedly didn't throw\n";
    } catch (Throwable $e) {
        printf("Caught %s: %s\n", $e::class, $e->getMessage());
    }
}

// Iterators are associated with an index (as in offsetGet), not a position in the deque.
$dq = new Teds\Deque([new stdClass(), strtoupper('test')]);
$it = $dq->getIterator();
var_dump($it->key());
var_dump($it->current());
var_dump($it->next());
var_dump($it->valid());
$dq->popFront();
var_dump($it->key());
expect_throws(fn() => $it->current());
var_dump($it->valid());
$dq->popFront();
var_dump($it->key()); // null for invalid iterator
expect_throws(fn() => $it->current());
var_dump($it->valid());
foreach ($it as $key => $value) {
    printf("Key: %s\nValue: %s\n", var_export($key, true), var_export($value, true));
}
var_dump($it);
?>
--EXPECT--
int(0)
object(stdClass)#2 (0) {
}
NULL
bool(true)
NULL
Caught OutOfBoundsException: Index out of range
bool(false)
NULL
Caught OutOfBoundsException: Index out of range
bool(false)
object(InternalIterator)#4 (0) {
}
