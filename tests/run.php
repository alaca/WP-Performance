<?php

/**
 * Test runner: php tests/run.php [suite-name]
 *
 * Each suite is a tests/suites/*.php file returning a callable that runs its
 * assertions. Suites share the stub bootstrap and reset global state between runs.
 */

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$only = $argv[1] ?? '';
$files = glob(__DIR__ . '/suites/*.php') ?: [];
sort($files);

$totalPassed = 0;
$totalFailed = 0;

foreach ($files as $file) {
    $name = basename($file, '.php');
    if ($only !== '' && ! str_contains($name, $only)) {
        continue;
    }

    WPP_Test_State::reset();
    WPP_Assert::$passed = 0;
    WPP_Assert::$failures = [];

    $suite = require $file;
    if (! is_callable($suite)) {
        fwrite(STDERR, "Suite {$name} did not return a callable\n");
        $totalFailed++;
        continue;
    }

    try {
        $suite();
    } catch (Throwable $e) {
        WPP_Assert::$failures[] = 'THREW ' . get_class($e) . ': ' . $e->getMessage()
            . ' at ' . $e->getFile() . ':' . $e->getLine();
    }

    $passed = WPP_Assert::$passed;
    $failures = WPP_Assert::$failures;
    $totalPassed += $passed;
    $totalFailed += count($failures);

    printf(
        "%-28s %s  %d passed%s\n",
        $name,
        $failures === [] ? 'PASS' : 'FAIL',
        $passed,
        $failures === [] ? '' : ', ' . count($failures) . ' failed'
    );

    foreach ($failures as $f) {
        echo "   - " . $f . "\n";
    }
}

echo str_repeat('-', 60) . "\n";
printf("%d assertions passed, %d failed\n", $totalPassed, $totalFailed);

exit($totalFailed === 0 ? 0 : 1);
