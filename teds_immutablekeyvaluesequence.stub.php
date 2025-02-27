<?php

/** @generate-class-entries */
// Stub generation requires build/gen_stubs.php from php 8.1 or newer.

namespace Teds;

/**
 * An immutable sequence of keys and values, where keys are repeatable and can be any type.
 */
final class ImmutableKeyValueSequence implements IteratorAggregate, Countable, JsonSerializable
{
    public function __construct(iterable $iterator) {}
    public function getIterator(): InternalIterator {}
    public function count(): int {}
    public static function fromPairs(iterable $pairs): ImmutableKeyValueSequence {}
    public function toPairs(): array {}

    public function __serialize(): array {}
    public function __unserialize(array $data): void {}
    public static function __set_state(array $array): ImmutableKeyValueSequence {}

    public function keys(): array {}
    public function values(): array {}
    public function keyAt(int $offset): mixed {}
    public function valueAt(int $offset): mixed {}

    public function jsonSerialize(): array {}
}
