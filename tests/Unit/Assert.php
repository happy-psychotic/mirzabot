<?php

function assertSameValue($expected, $actual, $message)
{
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: {$message}\nExpected: " . var_export($expected, true) . "\nActual:   " . var_export($actual, true) . "\n");
        exit(1);
    }
}

function assertTrueValue($actual, $message)
{
    if ($actual !== true) {
        fwrite(STDERR, "FAIL: {$message}\nActual: " . var_export($actual, true) . "\n");
        exit(1);
    }
}

function passTest($name)
{
    echo "PASS {$name}\n";
}

