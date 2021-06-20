--TEST--
Teds\Deque to array
--FILE--
<?php
// discards keys
$it = new Teds\Deque([strtoupper('test')]);
var_dump((array)$it);
$it[0] = strtoupper('test2');
var_dump((array)$it);
var_dump($it);
$it->popBack();
var_dump($it);


?>
--EXPECT--
array(1) {
  [0]=>
  string(4) "TEST"
}
array(1) {
  [0]=>
  string(5) "TEST2"
}
object(Teds\Deque)#1 (1) {
  [0]=>
  string(5) "TEST2"
}
object(Teds\Deque)#1 (0) {
}