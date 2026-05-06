<?php

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Tests must run from CLI.\n");
    exit(1);
}

$root = dirname(__DIR__);
$testFiles = glob($root . '/tests/Unit/*Test.php');
sort($testFiles);

$failures = 0;
foreach ($testFiles as $testFile) {
    $command = PHP_BINARY . ' ' . escapeshellarg($testFile);
    passthru($command, $exitCode);
    if ($exitCode !== 0) {
        $failures++;
    }
}

if ($failures > 0) {
    fwrite(STDERR, "\n{$failures} test file(s) failed.\n");
    exit(1);
}

echo "\nAll tests passed.\n";

