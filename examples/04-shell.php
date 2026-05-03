#!/usr/bin/env php
<?php
/**
 * php-io-cli — Example: Shell::run + SpinnerComponent integration
 *
 * Shows how to wrap real shell commands with animated UI feedback.
 * All examples use safe read-only commands — nothing is modified.
 *
 * Run: php examples/04-shell.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use AlfacodeTeam\PhpIoCli\Components\Alert;
use AlfacodeTeam\PhpIoCli\Components\ProgressBar;
use AlfacodeTeam\PhpIoCli\Components\SpinnerComponent;
use AlfacodeTeam\PhpIoCli\Depends\Colors;
use AlfacodeTeam\PhpIoCli\Depends\Shell;

Colors::line("\n  php-io-cli — Shell Integration Demo\n", [Colors::BOLD, Colors::CYAN]);

// ── Example 1: Shell::capture (quick value read) ───────────────────

Colors::line("  1. Shell::capture — read a value", Colors::BOLD);

$phpVersion = Shell::capture('php --version');
$gitVersion = Shell::capture('git --version');

echo PHP_EOL;
Colors::line("    PHP: " . explode("\n", (string)$phpVersion)[0], Colors::GREEN);
Colors::line("    Git: " . (string)$gitVersion, Colors::GREEN);
echo PHP_EOL;

// ── Example 2: Shell::run with SpinnerComponent ────────────────────

Colors::line("  2. Shell::run with SpinnerComponent", Colors::BOLD);
echo PHP_EOL;

$spin = new SpinnerComponent('Listing /tmp directory', 'dots');
$spin->start();

$result = Shell::run(
    'ls -la /tmp 2>&1 | head -20',
    tick: function (string $lastLine) use ($spin): void {
        $spin->tick($lastLine);
    }
);

if ($result->ok()) {
    $spin->stop('Directory listing complete');
    Colors::line("  Output lines: " . count($result->stdout), Colors::GREEN);
} else {
    $spin->fail('Command failed');
    Alert::error('Shell error', $result->meaningfulErrors());
}

echo PHP_EOL;

// ── Example 3: Shell::run with ProgressBar (multi-step) ────────────

Colors::line("  3. Multi-step pipeline with ProgressBar", Colors::BOLD);
echo PHP_EOL;

$steps = [
    ['Check PHP version', 'php --version'],
    ['Check Git version', 'git --version'],
    ['List current dir',  'ls -1 . | head -5'],
    ['Show disk usage',   'df -h / 2>/dev/null || echo "n/a"'],
    ['Show date/time',    'date'],
];

$bar = new ProgressBar('Running pipeline', count($steps));
$bar->start();

$allPassed = true;

foreach ($steps as [$label, $command]) {
    $stepResult = Shell::run(
        $command,
        tick: fn() => $bar->advance(0) // redraw without advancing
    );

    if ($stepResult->failed()) {
        $allPassed = false;
        $bar->advance(1, "✘ {$label}");
    } else {
        $bar->advance(1, "✔ {$label}");
    }
}

$bar->finish($allPassed ? 'All steps passed' : 'Some steps failed');

echo PHP_EOL;

// ── Example 4: Error handling ──────────────────────────────────────

Colors::line("  4. Error handling", Colors::BOLD);
echo PHP_EOL;

$spin2 = new SpinnerComponent('Running a command that fails', 'arc');
$spin2->start();

$failResult = Shell::run('ls /nonexistent/path/that/does/not/exist 2>&1');

for ($i = 0; $i < 5; $i++) {
    usleep(100_000);
    $spin2->tick('Checking…');
}

if ($failResult->failed()) {
    $spin2->fail("Command exited with code: {$failResult->exitCode}");
    Alert::warning('Expected failure (demo)', [
        'Exit code: ' . $failResult->exitCode,
        'Stderr: ' . implode(' ', $failResult->meaningfulErrors()),
    ]);
} else {
    $spin2->stop('Unexpectedly succeeded');
}

echo PHP_EOL;
Colors::line("  Shell integration demo complete!", [Colors::BOLD, Colors::GREEN]);
echo PHP_EOL;
