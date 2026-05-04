#!/usr/bin/env php
<?php

/**
 * php-io-cli — Example: Display Components
 *
 * Run: php examples/02-display.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use AlfacodeTeam\PhpIoCli\Components\Alert;
use AlfacodeTeam\PhpIoCli\Components\ProgressBar;
use AlfacodeTeam\PhpIoCli\Components\SpinnerComponent;
use AlfacodeTeam\PhpIoCli\Components\Table;
use AlfacodeTeam\PhpIoCli\Depends\Colors;

// ── Alerts ────────────────────────────────────────────────────────

Colors::line("\n  ── Alert Boxes ─────────────────────────────", Colors::CYAN);

Alert::success('Deployment complete!', [
    'Version: 2.4.1',
    'Region: eu-west-1',
    'Uptime: 99.98%',
]);

Alert::error('Build failed', [
    'Step: composer install',
    'Exit code: 1',
    'Check: /var/log/build.log',
]);

Alert::warning('API quota at 80%', ['Resets in 4 hours']);

Alert::info('Maintenance window tonight 02:00–04:00 UTC');

// ── Table ─────────────────────────────────────────────────────────

Colors::line('  ── Tables ──────────────────────────────────', Colors::CYAN);
echo PHP_EOL;

Colors::line('  Box style (default):', Colors::BOLD);
Table::make()
    ->headers(['Service', 'Status', 'Latency', 'Requests'])
    ->rows([
        ['api-gateway',    Colors::wrap('healthy', Colors::GREEN),  '12 ms',  '15,204'],
        ['auth-service',   Colors::wrap('degraded', Colors::YELLOW), '340 ms', '3,891'],
        ['payment-worker', Colors::wrap('down', Colors::RED),    '—',      '0'],
        ['cache-service',  Colors::wrap('healthy', Colors::GREEN),  '2 ms',   '52,001'],
    ])
    ->align([3 => 'right'])
    ->render();

Colors::line('  Bold style:', Colors::BOLD);
Table::make()
    ->headers(['Package', 'Version', 'License'])
    ->rows([
        ['php-io-cli',  '1.0.0', 'MIT'],
        ['psr/log',     '3.0.0', 'MIT'],
        ['phpunit',     '11.0',  'BSD-3'],
    ])
    ->style('bold')
    ->render();

Colors::line('  Minimal style:', Colors::BOLD);
Table::make()
    ->headers(['Key', 'Value'])
    ->rows([
        ['APP_ENV',  'production'],
        ['DB_HOST',  'localhost'],
        ['DB_PORT',  '5432'],
    ])
    ->style('minimal')
    ->striped(false)
    ->render();

// ── Progress Bar (Determinate) ────────────────────────────────────

Colors::line('  ── Progress Bar (Determinate) ───────────────', Colors::CYAN);
echo PHP_EOL;

$bar = new ProgressBar('Processing records', 50);
$bar->start();

for ($i = 0; $i < 50; $i++) {
    usleep(30_000); // 30ms per step
    $bar->advance(1, "Record #{$i}");
}

$bar->finish('All 50 records processed');

// ── Progress Bar (Indeterminate) ──────────────────────────────────

Colors::line('  ── Progress Bar (Indeterminate) ────────────', Colors::CYAN);
echo PHP_EOL;

$indeterminate = new ProgressBar('Connecting to cluster');
$indeterminate->start();

for ($i = 0; $i < 30; $i++) {
    usleep(50_000);
    $indeterminate->tick("Attempt {$i}…");
}

$indeterminate->finish('Connection established');

// ── Spinner ───────────────────────────────────────────────────────

Colors::line('  ── Spinner Styles ───────────────────────────', Colors::CYAN);
echo PHP_EOL;

$styles = ['dots', 'line', 'bars', 'pulse', 'arc', 'bounce'];

foreach ($styles as $style) {
    $spin = new SpinnerComponent("Spinner: {$style}", $style);
    $spin->start();

    for ($i = 0; $i < 20; $i++) {
        usleep(80_000);
        $spin->tick("Running {$style} animation…");
    }

    $spin->stop("Finished: {$style}");
}

echo PHP_EOL;
Colors::line('  Display components demo complete!', [Colors::BOLD, Colors::GREEN]);
echo PHP_EOL;
