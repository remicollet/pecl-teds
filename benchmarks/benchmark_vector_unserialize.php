<?php

use Teds\ImmutableSortedIntSet;
use Teds\IntVector;
use Teds\LowMemoryVector;
use Teds\SortedIntVectorSet;
use Teds\Vector;

// @phan-file-suppress PhanPossiblyUndeclaredVariable

function bench_array(int $n, int $iterations) {
    $totalSearchTime = 0.0;
    $total = 0;
    srand(1234);
    $values = [];
    for ($i = 0; $i < $n; $i++) {
        $values[] = rand();
    }
    $lastValue = $values[$n - 1];
    $ser = serialize($values);
    if (!is_string($ser)) { throw new RuntimeException("failed to serialize\n"); }
    unset($values);

    $totalSearchTime = 0;
    $totalReserializeTime = 0;
    $totalSearchTime = 0;
    $startTime = hrtime(true);

    for ($j = 0; $j < $iterations; $j++) {
        unset($values);
        $startMemory = memory_get_usage();
        $startUnserializeTime = hrtime(true);
        $values = unserialize($ser);
        $endMemory = memory_get_usage();

        $startSingleSearchTime = hrtime(true);
        $total += array_search($lastValue, $values);
        $startReserializeTime = hrtime(true);

        serialize($values);  // serialize, not used

        $endReserializeTime = hrtime(true);

        $totalSearchTime += $startReserializeTime - $startSingleSearchTime;
        $totalReserializeTime += $endReserializeTime - $startReserializeTime;
    }
    $endTime = hrtime(true);

    $totalTimeSeconds = ($endTime - $startTime) / 1000000000;
    $totalSearchTimeSeconds = $totalSearchTime / 1000000000;
    $totalReserializeTimeSeconds = $totalReserializeTime / 1000000000;
    $unserializeAndFreeTimeSeconds = $totalTimeSeconds - $totalSearchTimeSeconds - $totalReserializeTimeSeconds;

    printf("Repeatedly unserializing, searching, reserializing with in_array():   n=%8d iterations=%8d memory=%8d bytes\n => total time = %.4f seconds unserialize+free time=%.4f reserialize time = %.4f search time=%.4f result=%d\n => serialized memory=%8d bytes\n",
        $n, $iterations, $endMemory - $startMemory,
        $totalTimeSeconds, $unserializeAndFreeTimeSeconds, $totalReserializeTimeSeconds, $totalSearchTimeSeconds, $total, strlen($ser));
}
function bench_associative_array(int $n, int $iterations) {
    $totalSearchTime = 0.0;
    $total = 0;
    srand(1234);
    $values = [];
    for ($i = 0; $i < $n; $i++) {
        $values[rand()] = $i;
    }
    $lastValue = array_key_last($values);
    $ser = serialize($values);
    if (!is_string($ser)) { throw new RuntimeException("failed to serialize\n"); }
    unset($values);

    $totalSearchTime = 0;
    $totalReserializeTime = 0;
    $totalSearchTime = 0;
    $startTime = hrtime(true);

    for ($j = 0; $j < $iterations; $j++) {
        unset($values);
        $startMemory = memory_get_usage();
        $startUnserializeTime = hrtime(true);
        $values = unserialize($ser);
        $endMemory = memory_get_usage();

        $startSingleSearchTime = hrtime(true);
        $total += $values[$lastValue];
        $startReserializeTime = hrtime(true);

        serialize($values);  // serialize, not used

        $endReserializeTime = hrtime(true);

        $totalSearchTime += $startReserializeTime - $startSingleSearchTime;
        $totalReserializeTime += $endReserializeTime - $startReserializeTime;
    }
    $endTime = hrtime(true);

    $totalTimeSeconds = ($endTime - $startTime) / 1000000000;
    $totalSearchTimeSeconds = $totalSearchTime / 1000000000;
    $totalReserializeTimeSeconds = $totalReserializeTime / 1000000000;
    $unserializeAndFreeTimeSeconds = $totalTimeSeconds - $totalSearchTimeSeconds - $totalReserializeTimeSeconds;

    printf("Repeatedly unserializing, searching, reserialize associative array:   n=%8d iterations=%8d memory=%8d bytes\n => total time = %.4f seconds unserialize+free time=%.4f reserialize time = %.4f search time=%.4f result=%d\n => serialized memory=%8d bytes\n",
        $n, $iterations, $endMemory - $startMemory,
        $totalTimeSeconds, $unserializeAndFreeTimeSeconds, $totalReserializeTimeSeconds, $totalSearchTimeSeconds, $total, strlen($ser));
}
function bench_vector(int $n, int $iterations) {
    $totalSearchTime = 0.0;
    $total = 0;
    srand(1234);
    $values = new Vector();
    for ($i = 0; $i < $n; $i++) {
        $values[] = rand();
    }
    $lastValue = $values[$n - 1];
    $ser = serialize($values);
    if (!is_string($ser)) { throw new RuntimeException("failed to serialize\n"); }
    unset($values);

    $totalSearchTime = 0;
    $totalReserializeTime = 0;
    $totalSearchTime = 0;
    $startTime = hrtime(true);

    for ($j = 0; $j < $iterations; $j++) {
        unset($values);
        $startMemory = memory_get_usage();
        $startUnserializeTime = hrtime(true);
        $values = unserialize($ser);
        $endMemory = memory_get_usage();

        $startSingleSearchTime = hrtime(true);
        $total += $values->indexOf($lastValue);
        $startReserializeTime = hrtime(true);

        serialize($values);  // serialize, not used

        $endReserializeTime = hrtime(true);

        $totalSearchTime += $startReserializeTime - $startSingleSearchTime;
        $totalReserializeTime += $endReserializeTime - $startReserializeTime;
    }
    $endTime = hrtime(true);

    $totalTimeSeconds = ($endTime - $startTime) / 1000000000;
    $totalSearchTimeSeconds = $totalSearchTime / 1000000000;
    $totalReserializeTimeSeconds = $totalReserializeTime / 1000000000;
    $unserializeAndFreeTimeSeconds = $totalTimeSeconds - $totalSearchTimeSeconds - $totalReserializeTimeSeconds;

    printf("Repeatedly unserializing, searching, reserializing Vector:            n=%8d iterations=%8d memory=%8d bytes\n => total time = %.4f seconds unserialize+free time=%.4f reserialize time = %.4f search time=%.4f result=%d\n => serialized memory=%8d bytes\n",
        $n, $iterations, $endMemory - $startMemory,
        $totalTimeSeconds, $unserializeAndFreeTimeSeconds, $totalReserializeTimeSeconds, $totalSearchTimeSeconds, $total, strlen($ser));
}
function bench_low_memory_vector(int $n, int $iterations) {
    $totalSearchTime = 0.0;
    $total = 0;
    srand(1234);
    $values = new LowMemoryVector();
    for ($i = 0; $i < $n; $i++) {
        $values[] = rand();
    }
    $lastValue = $values[$n - 1];
    $ser = serialize($values);
    if (!is_string($ser)) { throw new RuntimeException("failed to serialize\n"); }
    unset($values);

    $totalSearchTime = 0;
    $totalReserializeTime = 0;
    $totalSearchTime = 0;
    $startTime = hrtime(true);

    for ($j = 0; $j < $iterations; $j++) {
        unset($values);
        $startMemory = memory_get_usage();
        $startUnserializeTime = hrtime(true);
        $values = unserialize($ser);
        $endMemory = memory_get_usage();

        $startSingleSearchTime = hrtime(true);
        $total += $values->indexOf($lastValue);
        $startReserializeTime = hrtime(true);

        serialize($values);  // serialize, not used

        $endReserializeTime = hrtime(true);

        $totalSearchTime += $startReserializeTime - $startSingleSearchTime;
        $totalReserializeTime += $endReserializeTime - $startReserializeTime;
    }
    $endTime = hrtime(true);

    $totalTimeSeconds = ($endTime - $startTime) / 1000000000;
    $totalSearchTimeSeconds = $totalSearchTime / 1000000000;
    $totalReserializeTimeSeconds = $totalReserializeTime / 1000000000;
    $unserializeAndFreeTimeSeconds = $totalTimeSeconds - $totalSearchTimeSeconds - $totalReserializeTimeSeconds;

    printf("Repeatedly unserializing, searching, reserializing LowMemoryVector:   n=%8d iterations=%8d memory=%8d bytes\n => total time = %.4f seconds unserialize+free time=%.4f reserialize time = %.4f search time=%.4f result=%d\n => serialized memory=%8d bytes\n",
        $n, $iterations, $endMemory - $startMemory,
        $totalTimeSeconds, $unserializeAndFreeTimeSeconds, $totalReserializeTimeSeconds, $totalSearchTimeSeconds, $total, strlen($ser));
}
function bench_int_vector(int $n, int $iterations) {
    $totalSearchTime = 0.0;
    $total = 0;
    srand(1234);
    $values = new IntVector();
    for ($i = 0; $i < $n; $i++) {
        $values[] = rand();
    }
    $lastValue = $values[$n - 1];
    $ser = serialize($values);
    if (!is_string($ser)) { throw new RuntimeException("failed to serialize\n"); }
    unset($values);

    $totalSearchTime = 0;
    $totalReserializeTime = 0;
    $totalSearchTime = 0;
    $startTime = hrtime(true);

    for ($j = 0; $j < $iterations; $j++) {
        unset($values);
        $startMemory = memory_get_usage();
        $startUnserializeTime = hrtime(true);
        $values = unserialize($ser);
        $endMemory = memory_get_usage();

        $startSingleSearchTime = hrtime(true);
        $total += $values->indexOf($lastValue);
        $startReserializeTime = hrtime(true);

        serialize($values);  // serialize, not used

        $endReserializeTime = hrtime(true);

        $totalSearchTime += $startReserializeTime - $startSingleSearchTime;
        $totalReserializeTime += $endReserializeTime - $startReserializeTime;
    }
    $endTime = hrtime(true);

    $totalTimeSeconds = ($endTime - $startTime) / 1000000000;
    $totalSearchTimeSeconds = $totalSearchTime / 1000000000;
    $totalReserializeTimeSeconds = $totalReserializeTime / 1000000000;
    $unserializeAndFreeTimeSeconds = $totalTimeSeconds - $totalSearchTimeSeconds - $totalReserializeTimeSeconds;

    printf("Repeatedly unserializing, searching, reserializing IntVector          n=%8d iterations=%8d memory=%8d bytes\n => total time = %.4f seconds unserialize+free time=%.4f reserialize time = %.4f search time=%.4f result=%d\n => serialized memory=%8d bytes\n",
        $n, $iterations, $endMemory - $startMemory,
        $totalTimeSeconds, $unserializeAndFreeTimeSeconds, $totalReserializeTimeSeconds, $totalSearchTimeSeconds, $total, strlen($ser));
}
function bench_sortedintvectorset(int $n, int $iterations) {
    $totalSearchTime = 0.0;
    $total = 0;
    srand(1234);
    $values = [];
    for ($i = 0; $i < $n; $i++) {
        $values[] = rand();
    }
    $values = new SortedIntVectorSet($values); // NOTE: this uses insertion sort by design to reduce memory usage and speed up unserialization, and is faster to construct all at once.
    $lastValue = $values->last();
    $ser = serialize($values);
    if (!is_string($ser)) { throw new RuntimeException("failed to serialize\n"); }
    unset($values);

    $totalSearchTime = 0;
    $totalReserializeTime = 0;
    $totalSearchTime = 0;
    $startTime = hrtime(true);

    for ($j = 0; $j < $iterations; $j++) {
        unset($values);
        $startMemory = memory_get_usage();
        $startUnserializeTime = hrtime(true);
        $values = unserialize($ser);
        $endMemory = memory_get_usage();

        $startSingleSearchTime = hrtime(true);
        $total += $values->indexOf($lastValue);
        $startReserializeTime = hrtime(true);

        serialize($values);  // serialize, not used

        $endReserializeTime = hrtime(true);

        $totalSearchTime += $startReserializeTime - $startSingleSearchTime;
        $totalReserializeTime += $endReserializeTime - $startReserializeTime;
    }
    $endTime = hrtime(true);

    $totalTimeSeconds = ($endTime - $startTime) / 1000000000;
    $totalSearchTimeSeconds = $totalSearchTime / 1000000000;
    $totalReserializeTimeSeconds = $totalReserializeTime / 1000000000;
    $unserializeAndFreeTimeSeconds = $totalTimeSeconds - $totalSearchTimeSeconds - $totalReserializeTimeSeconds;

    printf("Repeatedly unserializing, searching, reserializing SortedIntVectorSet n=%8d iterations=%8d memory=%8d bytes\n => total time = %.4f seconds unserialize+free time=%.4f reserialize time = %.4f search time=%.4f result=%d\n => serialized memory=%8d bytes\n",
        $n, $iterations, $endMemory - $startMemory,
        $totalTimeSeconds, $unserializeAndFreeTimeSeconds, $totalReserializeTimeSeconds, $totalSearchTimeSeconds, $total, strlen($ser));
}
function bench_immutablesortedintset(int $n, int $iterations) {
    $totalSearchTime = 0.0;
    $total = 0;
    srand(1234);
    $values = [];
    for ($i = 0; $i < $n; $i++) {
        $values[] = rand();
    }
    $values = new ImmutableSortedIntSet($values); // NOTE: this uses insertion sort by design to reduce memory usage and speed up unserialization, and is faster to construct all at once.
    $lastValue = $values->last();
    $ser = serialize($values);
    if (!is_string($ser)) { throw new RuntimeException("failed to serialize\n"); }
    unset($values);

    $totalSearchTime = 0;
    $totalReserializeTime = 0;
    $totalSearchTime = 0;
    $startTime = hrtime(true);

    for ($j = 0; $j < $iterations; $j++) {
        unset($values);
        $startMemory = memory_get_usage();
        $startUnserializeTime = hrtime(true);
        $values = unserialize($ser);
        $endMemory = memory_get_usage();

        $startSingleSearchTime = hrtime(true);
        $total += $values->indexOf($lastValue);
        $startReserializeTime = hrtime(true);

        serialize($values);  // serialize, not used

        $endReserializeTime = hrtime(true);

        $totalSearchTime += $startReserializeTime - $startSingleSearchTime;
        $totalReserializeTime += $endReserializeTime - $startReserializeTime;
    }
    $endTime = hrtime(true);

    $totalTimeSeconds = ($endTime - $startTime) / 1000000000;
    $totalSearchTimeSeconds = $totalSearchTime / 1000000000;
    $totalReserializeTimeSeconds = $totalReserializeTime / 1000000000;
    $unserializeAndFreeTimeSeconds = $totalTimeSeconds - $totalSearchTimeSeconds - $totalReserializeTimeSeconds;

    printf("Repeatedly unserializing, searching, reser... ImmutableSortedIntSet   n=%8d iterations=%8d memory=%8d bytes\n => total time = %.4f seconds unserialize+free time=%.4f reserialize time = %.4f search time=%.4f result=%d\n => serialized memory=%8d bytes\n",
        $n, $iterations, $endMemory - $startMemory,
        $totalTimeSeconds, $unserializeAndFreeTimeSeconds, $totalReserializeTimeSeconds, $totalSearchTimeSeconds, $total, strlen($ser));
}

$n = 2**20;
$iterations = 10;
$sizes = [
    [1, 4000000],
    [4, 1000000],
    [8, 500000],
    [1024, 8000],
    [4096, 2000],
    [2**17, 160],
    [2**18, 80],
    [2**20, 20],
];
echo "Test efficiency of different representations of lists of 32-bit integers\n\n";
printf(
    "Results for php %s debug=%s with opcache enabled=%s\n",
    PHP_VERSION,
    PHP_DEBUG ? 'true' : 'false',
    json_encode(function_exists('opcache_get_status') && (opcache_get_status(false)['opcache_enabled'] ?? false))
);
echo "(Note that LowMemoryVector/IntVector has specialized representations for collections of int with smaller in-memory and serialized representations, and can skip garbage collection if using a specialized type where none of the elements are reference counted)\n";
echo "(Note that there are faster serializations than unserialize() available as PECLs, e.g. igbinary/msgpack.)\n";
echo "(Note that unserializers pass the array of mixed values to Vector::__unserialize so it is slower to unserialize than the array of values)\n";
echo "(Note that SortedIntVectorSet removes duplicates)\n\n";

foreach ($sizes as [$n, $iterations]) {
    bench_array($n, $iterations);
    bench_associative_array($n, $iterations);
    bench_vector($n, $iterations);
    bench_low_memory_vector($n, $iterations);
    bench_int_vector($n, $iterations);
    bench_sortedintvectorset($n, $iterations);
    bench_immutablesortedintset($n, $iterations);
    echo "\n";
}
/*

Test efficiency of different representations of lists of 32-bit integers

Results for php 8.2.0-dev debug=false with opcache enabled=true
(Note that LowMemoryVector/IntVector has specialized representations for collections of int with smaller in-memory and serialized representations, and can skip garbage collection if using a specialized type where none of the elements are reference counted)
(Note that there are faster serializations than unserialize() available as PECLs, e.g. igbinary/msgpack.)
(Note that unserializers pass the array of mixed values to Vector::__unserialize so it is slower to unserialize than the array of values)
(Note that SortedIntVectorSet removes duplicates)

Repeatedly unserializing, searching, reserializing with in_array():   n=       1 iterations= 4000000 memory=     376 bytes
 => total time = 1.4334 seconds unserialize+free time=0.8653 reserialize time = 0.3957 search time=0.1724 result=0
 => serialized memory=      22 bytes
Repeatedly unserializing, searching, reserialize associative array:   n=       1 iterations= 4000000 memory=     376 bytes
 => total time = 1.4762 seconds unserialize+free time=0.8974 reserialize time = 0.3992 search time=0.1796 result=0
 => serialized memory=      22 bytes
Repeatedly unserializing, searching, reserializing Vector:            n=       1 iterations= 4000000 memory=      72 bytes
 => total time = 2.4027 seconds unserialize+free time=1.4842 reserialize time = 0.6938 search time=0.2247 result=0
 => serialized memory=      39 bytes
Repeatedly unserializing, searching, reserializing LowMemoryVector:   n=       1 iterations= 4000000 memory=      72 bytes
 => total time = 2.6505 seconds unserialize+free time=1.6424 reserialize time = 0.7823 search time=0.2258 result=0
 => serialized memory=      55 bytes
Repeatedly unserializing, searching, reserializing IntVector          n=       1 iterations= 4000000 memory=      88 bytes
 => total time = 2.6432 seconds unserialize+free time=1.6379 reserialize time = 0.7778 search time=0.2275 result=0
 => serialized memory=      49 bytes
Repeatedly unserializing, searching, reserializing SortedIntVectorSet n=       1 iterations= 4000000 memory=      88 bytes
 => total time = 2.6521 seconds unserialize+free time=1.6463 reserialize time = 0.7771 search time=0.2286 result=0
 => serialized memory=      58 bytes
Repeatedly unserializing, searching, reser... ImmutableSortedIntSet   n=       1 iterations= 4000000 memory=      88 bytes
 => total time = 2.6501 seconds unserialize+free time=1.6413 reserialize time = 0.7780 search time=0.2307 result=0
 => serialized memory=      61 bytes

Repeatedly unserializing, searching, reserializing with in_array():   n=       4 iterations= 1000000 memory=     376 bytes
 => total time = 0.5605 seconds unserialize+free time=0.3347 reserialize time = 0.1775 search time=0.0483 result=3000000
 => serialized memory=      73 bytes
Repeatedly unserializing, searching, reserialize associative array:   n=       4 iterations= 1000000 memory=     376 bytes
 => total time = 0.5692 seconds unserialize+free time=0.3397 reserialize time = 0.1844 search time=0.0451 result=3000000
 => serialized memory=      73 bytes
Repeatedly unserializing, searching, reserializing Vector:            n=       4 iterations= 1000000 memory=     120 bytes
 => total time = 0.8187 seconds unserialize+free time=0.5029 reserialize time = 0.2534 search time=0.0623 result=3000000
 => serialized memory=      90 bytes
Repeatedly unserializing, searching, reserializing LowMemoryVector:   n=       4 iterations= 1000000 memory=      80 bytes
 => total time = 0.6955 seconds unserialize+free time=0.4288 reserialize time = 0.2060 search time=0.0607 result=3000000
 => serialized memory=      68 bytes
Repeatedly unserializing, searching, reserializing IntVector          n=       4 iterations= 1000000 memory=      96 bytes
 => total time = 0.6666 seconds unserialize+free time=0.4122 reserialize time = 0.1962 search time=0.0582 result=3000000
 => serialized memory=      62 bytes
Repeatedly unserializing, searching, reserializing SortedIntVectorSet n=       4 iterations= 1000000 memory=      96 bytes
 => total time = 0.6720 seconds unserialize+free time=0.4175 reserialize time = 0.1960 search time=0.0586 result=3000000
 => serialized memory=      71 bytes
Repeatedly unserializing, searching, reser... ImmutableSortedIntSet   n=       4 iterations= 1000000 memory=      96 bytes
 => total time = 0.6701 seconds unserialize+free time=0.4152 reserialize time = 0.1962 search time=0.0587 result=3000000
 => serialized memory=      74 bytes

Repeatedly unserializing, searching, reserializing with in_array():   n=       8 iterations=  500000 memory=     376 bytes
 => total time = 0.4237 seconds unserialize+free time=0.2450 reserialize time = 0.1515 search time=0.0272 result=3500000
 => serialized memory=     140 bytes
Repeatedly unserializing, searching, reserialize associative array:   n=       8 iterations=  500000 memory=     376 bytes
 => total time = 0.4298 seconds unserialize+free time=0.2441 reserialize time = 0.1632 search time=0.0224 result=3500000
 => serialized memory=     140 bytes
Repeatedly unserializing, searching, reserializing Vector:            n=       8 iterations=  500000 memory=     184 bytes
 => total time = 0.5569 seconds unserialize+free time=0.3339 reserialize time = 0.1882 search time=0.0348 result=3500000
 => serialized memory=     157 bytes
Repeatedly unserializing, searching, reserializing LowMemoryVector:   n=       8 iterations=  500000 memory=      96 bytes
 => total time = 0.3453 seconds unserialize+free time=0.2122 reserialize time = 0.1018 search time=0.0313 result=3500000
 => serialized memory=      84 bytes
Repeatedly unserializing, searching, reserializing IntVector          n=       8 iterations=  500000 memory=     112 bytes
 => total time = 0.3431 seconds unserialize+free time=0.2114 reserialize time = 0.1006 search time=0.0311 result=3500000
 => serialized memory=      78 bytes
Repeatedly unserializing, searching, reserializing SortedIntVectorSet n=       8 iterations=  500000 memory=     112 bytes
 => total time = 0.3465 seconds unserialize+free time=0.2147 reserialize time = 0.1010 search time=0.0308 result=3500000
 => serialized memory=      87 bytes
Repeatedly unserializing, searching, reser... ImmutableSortedIntSet   n=       8 iterations=  500000 memory=     112 bytes
 => total time = 0.3477 seconds unserialize+free time=0.2154 reserialize time = 0.1012 search time=0.0310 result=3500000
 => serialized memory=      90 bytes

Repeatedly unserializing, searching, reserializing with in_array():   n=    1024 iterations=    8000 memory=   41016 bytes
 => total time = 0.6774 seconds unserialize+free time=0.3629 reserialize time = 0.3001 search time=0.0144 result=8184000
 => serialized memory=   18850 bytes
Repeatedly unserializing, searching, reserialize associative array:   n=    1024 iterations=    8000 memory=   41016 bytes
 => total time = 0.6863 seconds unserialize+free time=0.3789 reserialize time = 0.3070 search time=0.0004 result=8184000
 => serialized memory=   18850 bytes
Repeatedly unserializing, searching, reserializing Vector:            n=    1024 iterations=    8000 memory=   16440 bytes
 => total time = 0.6670 seconds unserialize+free time=0.3832 reserialize time = 0.2737 search time=0.0101 result=8184000
 => serialized memory=   18867 bytes
Repeatedly unserializing, searching, reserializing LowMemoryVector:   n=    1024 iterations=    8000 memory=    4160 bytes
 => total time = 0.0123 seconds unserialize+free time=0.0043 reserialize time = 0.0028 search time=0.0052 result=8184000
 => serialized memory=    4150 bytes
Repeatedly unserializing, searching, reserializing IntVector          n=    1024 iterations=    8000 memory=    4176 bytes
 => total time = 0.0101 seconds unserialize+free time=0.0044 reserialize time = 0.0027 search time=0.0030 result=8184000
 => serialized memory=    4144 bytes
Repeatedly unserializing, searching, reserializing SortedIntVectorSet n=    1024 iterations=    8000 memory=    4176 bytes
 => total time = 0.0110 seconds unserialize+free time=0.0076 reserialize time = 0.0028 search time=0.0006 result=8184000
 => serialized memory=    4153 bytes
Repeatedly unserializing, searching, reser... ImmutableSortedIntSet   n=    1024 iterations=    8000 memory=    4176 bytes
 => total time = 0.0110 seconds unserialize+free time=0.0076 reserialize time = 0.0028 search time=0.0006 result=8184000
 => serialized memory=    4156 bytes

Repeatedly unserializing, searching, reserializing with in_array():   n=    4096 iterations=    2000 memory=  163896 bytes
 => total time = 0.6909 seconds unserialize+free time=0.3648 reserialize time = 0.3121 search time=0.0140 result=8190000
 => serialized memory=   78683 bytes
Repeatedly unserializing, searching, reserialize associative array:   n=    4096 iterations=    2000 memory=  163896 bytes
 => total time = 0.7022 seconds unserialize+free time=0.3888 reserialize time = 0.3132 search time=0.0001 result=8190000
 => serialized memory=   78683 bytes
Repeatedly unserializing, searching, reserializing Vector:            n=    4096 iterations=    2000 memory=   65592 bytes
 => total time = 0.6782 seconds unserialize+free time=0.3877 reserialize time = 0.2808 search time=0.0097 result=8190000
 => serialized memory=   78700 bytes
Repeatedly unserializing, searching, reserializing LowMemoryVector:   n=    4096 iterations=    2000 memory=   16448 bytes
 => total time = 0.0093 seconds unserialize+free time=0.0023 reserialize time = 0.0023 search time=0.0047 result=8190000
 => serialized memory=   16439 bytes
Repeatedly unserializing, searching, reserializing IntVector          n=    4096 iterations=    2000 memory=   16464 bytes
 => total time = 0.0073 seconds unserialize+free time=0.0023 reserialize time = 0.0025 search time=0.0025 result=8190000
 => serialized memory=   16433 bytes
Repeatedly unserializing, searching, reserializing SortedIntVectorSet n=    4096 iterations=    2000 memory=   16464 bytes
 => total time = 0.0078 seconds unserialize+free time=0.0053 reserialize time = 0.0023 search time=0.0002 result=8190000
 => serialized memory=   16442 bytes
Repeatedly unserializing, searching, reser... ImmutableSortedIntSet   n=    4096 iterations=    2000 memory=   16464 bytes
 => total time = 0.0077 seconds unserialize+free time=0.0053 reserialize time = 0.0022 search time=0.0002 result=8190000
 => serialized memory=   16445 bytes

Repeatedly unserializing, searching, reserializing with in_array():   n=  131072 iterations=     160 memory= 5242960 bytes
 => total time = 2.4320 seconds unserialize+free time=1.2311 reserialize time = 1.1510 search time=0.0499 result=20971360
 => serialized memory= 2704563 bytes
Repeatedly unserializing, searching, reserialize associative array:   n=  131072 iterations=     160 memory= 5242960 bytes
 => total time = 2.5723 seconds unserialize+free time=1.4271 reserialize time = 1.1451 search time=0.0001 result=20971360
 => serialized memory= 2704523 bytes
Repeatedly unserializing, searching, reserializing Vector:            n=  131072 iterations=     160 memory= 2097232 bytes
 => total time = 2.5886 seconds unserialize+free time=1.3898 reserialize time = 1.1675 search time=0.0313 result=20971360
 => serialized memory= 2704580 bytes
Repeatedly unserializing, searching, reserializing LowMemoryVector:   n=  131072 iterations=     160 memory=  524352 bytes
 => total time = 0.0225 seconds unserialize+free time=0.0056 reserialize time = 0.0058 search time=0.0111 result=20971360
 => serialized memory=  524344 bytes
Repeatedly unserializing, searching, reserializing IntVector          n=  131072 iterations=     160 memory=  524368 bytes
 => total time = 0.0173 seconds unserialize+free time=0.0057 reserialize time = 0.0058 search time=0.0059 result=20971360
 => serialized memory=  524338 bytes
Repeatedly unserializing, searching, reserializing SortedIntVectorSet n=  131072 iterations=     160 memory=  524368 bytes
 => total time = 0.0196 seconds unserialize+free time=0.0136 reserialize time = 0.0059 search time=0.0000 result=20971040
 => serialized memory=  524339 bytes
Repeatedly unserializing, searching, reser... ImmutableSortedIntSet   n=  131072 iterations=     160 memory=  524368 bytes
 => total time = 0.0194 seconds unserialize+free time=0.0135 reserialize time = 0.0058 search time=0.0000 result=20971040
 => serialized memory=  524342 bytes

Repeatedly unserializing, searching, reserializing with in_array():   n=  262144 iterations=      80 memory=10485840 bytes
 => total time = 2.5124 seconds unserialize+free time=1.2308 reserialize time = 1.2292 search time=0.0524 result=20971440
 => serialized memory= 5520026 bytes
Repeatedly unserializing, searching, reserialize associative array:   n=  262144 iterations=      80 memory=10485840 bytes
 => total time = 2.8174 seconds unserialize+free time=1.7215 reserialize time = 1.0958 search time=0.0001 result=20971440
 => serialized memory= 5519692 bytes
Repeatedly unserializing, searching, reserializing Vector:            n=  262144 iterations=      80 memory= 4194384 bytes
 => total time = 2.5764 seconds unserialize+free time=1.4201 reserialize time = 1.1247 search time=0.0316 result=20971440
 => serialized memory= 5520043 bytes
Repeatedly unserializing, searching, reserializing LowMemoryVector:   n=  262144 iterations=      80 memory= 1048640 bytes
 => total time = 0.0759 seconds unserialize+free time=0.0322 reserialize time = 0.0325 search time=0.0112 result=20971440
 => serialized memory= 1048633 bytes
Repeatedly unserializing, searching, reserializing IntVector          n=  262144 iterations=      80 memory= 1048656 bytes
 => total time = 0.0705 seconds unserialize+free time=0.0318 reserialize time = 0.0327 search time=0.0059 result=20971440
 => serialized memory= 1048627 bytes
Repeatedly unserializing, searching, reserializing SortedIntVectorSet n=  262144 iterations=      80 memory= 1048656 bytes
 => total time = 0.0723 seconds unserialize+free time=0.0397 reserialize time = 0.0327 search time=0.0000 result=20970160
 => serialized memory= 1048572 bytes
Repeatedly unserializing, searching, reser... ImmutableSortedIntSet   n=  262144 iterations=      80 memory= 1048656 bytes
 => total time = 0.0725 seconds unserialize+free time=0.0400 reserialize time = 0.0325 search time=0.0000 result=20970160
 => serialized memory= 1048575 bytes

Repeatedly unserializing, searching, reserializing with in_array():   n= 1048576 iterations=      20 memory=41943120 bytes
 => total time = 2.6757 seconds unserialize+free time=1.2659 reserialize time = 1.3564 search time=0.0534 result=20971500
 => serialized memory=22462741 bytes
Repeatedly unserializing, searching, reserialize associative array:   n= 1048576 iterations=      20 memory=41943120 bytes
 => total time = 4.0826 seconds unserialize+free time=2.7910 reserialize time = 1.2915 search time=0.0000 result=20971500
 => serialized memory=22456955 bytes
Repeatedly unserializing, searching, reserializing Vector:            n= 1048576 iterations=      20 memory=16777296 bytes
 => total time = 3.0509 seconds unserialize+free time=1.4712 reserialize time = 1.5479 search time=0.0319 result=20971500
 => serialized memory=22462758 bytes
Repeatedly unserializing, searching, reserializing LowMemoryVector:   n= 1048576 iterations=      20 memory= 4194392 bytes
 => total time = 0.1559 seconds unserialize+free time=0.0720 reserialize time = 0.0705 search time=0.0134 result=20971500
 => serialized memory= 4194361 bytes
Repeatedly unserializing, searching, reserializing IntVector          n= 1048576 iterations=      20 memory= 4194408 bytes
 => total time = 0.1524 seconds unserialize+free time=0.0726 reserialize time = 0.0718 search time=0.0080 result=20971500
 => serialized memory= 4194355 bytes
Repeatedly unserializing, searching, reserializing SortedIntVectorSet n= 1048576 iterations=      20 memory= 4194408 bytes
 => total time = 0.1531 seconds unserialize+free time=0.0825 reserialize time = 0.0706 search time=0.0000 result=20966060
 => serialized memory= 4193276 bytes
Repeatedly unserializing, searching, reser... ImmutableSortedIntSet   n= 1048576 iterations=      20 memory= 4194408 bytes
 => total time = 0.0824 seconds unserialize+free time=0.0469 reserialize time = 0.0354 search time=0.0000 result=20966060
 => serialized memory= 4193279 bytes

*/
