#!/usr/bin/env php
<?php

/**
 * php-io-cli — Interactive Demo
 *
 * A menu-driven tour of every component in the library.
 * Run:  php examples/demo.php
 *
 * Use ↑ ↓ to navigate, ENTER to launch a demo, or choose "Exit".
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use AlfacodeTeam\PhpIoCli\Components\Alert;
use AlfacodeTeam\PhpIoCli\Components\Autocomplete;
use AlfacodeTeam\PhpIoCli\Components\Confirm;
use AlfacodeTeam\PhpIoCli\Components\DatePicker;
use AlfacodeTeam\PhpIoCli\Components\MultiSelect;
use AlfacodeTeam\PhpIoCli\Components\NumberInput;
use AlfacodeTeam\PhpIoCli\Components\Password;
use AlfacodeTeam\PhpIoCli\Components\ProgressBar;
use AlfacodeTeam\PhpIoCli\Components\Select;
use AlfacodeTeam\PhpIoCli\Components\SpinnerComponent;
use AlfacodeTeam\PhpIoCli\Components\Table;
use AlfacodeTeam\PhpIoCli\Components\TextInput;
use AlfacodeTeam\PhpIoCli\Depends\Colors;
use AlfacodeTeam\PhpIoCli\Depends\Shell;

// ── Helpers ──────────────────────────────────────────────────────────────────

function banner(string $title): void
{
    $line = str_repeat('─', mb_strlen($title) + 4);
    echo PHP_EOL;
    Colors::line("  ┌{$line}┐", Colors::CYAN);
    Colors::line("  │  " . Colors::wrap($title, Colors::BOLD) . "  │", Colors::CYAN);
    Colors::line("  └{$line}┘", Colors::CYAN);
    echo PHP_EOL;
}

function result(string $label, mixed $value): void
{
    $display = is_array($value) ? implode(', ', $value) : (string) $value;
    Colors::line("  ✔ {$label}: " . Colors::wrap($display, Colors::GREEN), Colors::BOLD);
    echo PHP_EOL;
}

function pauseForUser(): void
{
    Colors::line('  Press ENTER to return to the menu…', Colors::GRAY);
    fgets(STDIN);
}

// ── Demo routines ─────────────────────────────────────────────────────────────

function demoTextInput(): void
{
    banner('TextInput');
    Colors::line('  Free-text input with virtual cursor, placeholder, validation.', Colors::GRAY);
    echo PHP_EOL;

    $name = (new TextInput('What is your name?'))
        ->placeholder('e.g. Alice')
        ->default('World')
        ->validate(fn(string $v): ?string => mb_strlen($v) >= 2 ? null : 'Name must be ≥ 2 characters')
        ->run();

    result('Name', $name);
    pauseForUser();
}

function demoNumberInput(): void
{
    banner('NumberInput');
    Colors::line('  Numeric entry with ↑↓ stepping, min/max clamping, range hint.', Colors::GRAY);
    echo PHP_EOL;

    $port = (new NumberInput('Server port'))
        ->min(1)
        ->max(65535)
        ->default(8080)
        ->step(100)
        ->integer()
        ->run();

    result('Port', $port);
    pauseForUser();
}

function demoPassword(): void
{
    banner('Password');
    Colors::line('  Masked input. TAB to toggle visibility. Live strength meter.', Colors::GRAY);
    echo PHP_EOL;

    $secret = (new Password('Enter a password'))->showStrength()->run();

    result('Length', mb_strlen((string) $secret) . ' chars');
    pauseForUser();
}

function demoConfirm(): void
{
    banner('Confirm');
    Colors::line('  Boolean toggle. ← → to switch. y/n shortcuts.', Colors::GRAY);
    echo PHP_EOL;

    $ok = (new Confirm('Do you want to continue?', true))->run();

    result('Answer', $ok ? 'Yes' : 'No');
    pauseForUser();
}

function demoSelect(): void
{
    banner('Select');
    Colors::line('  Single-selection list with fuzzy search and scroll windowing.', Colors::GRAY);
    echo PHP_EOL;

    $env = (new Select('Deployment environment', [
        'production', 'staging', 'development', 'local',
    ]))->run();

    result('Environment', (string) $env);
    pauseForUser();
}

function demoMultiSelect(): void
{
    banner('MultiSelect');
    Colors::line('  Checkbox list. SPACE to toggle, ENTER to confirm.', Colors::GRAY);
    echo PHP_EOL;

    $features = (new MultiSelect('Enable features', [
        'Authentication', 'API Gateway', 'Queue Worker',
        'Scheduler', 'WebSockets', 'Rate Limiting',
    ]))->run();

    result('Features', $features);
    pauseForUser();
}

function demoAutocomplete(): void
{
    banner('Autocomplete');
    Colors::line('  Text + live fuzzy dropdown. TAB to fill, ↑↓ to navigate.', Colors::GRAY);
    echo PHP_EOL;

    $framework = (new Autocomplete('PHP framework', [
        'Laravel', 'Symfony', 'Slim', 'Laminas', 'CodeIgniter',
        'Yii', 'CakePHP', 'Phalcon', 'Lumen', 'Hyperf',
    ]))->maxSuggestions(6)->run();

    result('Framework', (string) $framework);
    pauseForUser();
}

function demoDatePicker(): void
{
    banner('DatePicker');
    Colors::line('  Calendar grid. ←→ day, ↑↓ week, [ ] month, t = today.', Colors::GRAY);
    echo PHP_EOL;

    $date = (new DatePicker('Select a date'))->run();

    result('Date', $date->format('Y-m-d'));
    pauseForUser();
}

function demoTable(): void
{
    banner('Table');
    Colors::line('  Unicode box-drawing table. ANSI-safe column alignment.', Colors::GRAY);
    echo PHP_EOL;

    $styles = ['box', 'bold', 'compact', 'minimal'];

    foreach ($styles as $style) {
        Colors::line("  Style: {$style}", Colors::YELLOW);
        Table::make()
            ->headers(['Service', 'Status', 'Latency'])
            ->rows([
                ['api-gateway',    Colors::wrap('healthy',  Colors::GREEN),  '12 ms'],
                ['auth-service',   Colors::wrap('degraded', Colors::YELLOW), '340 ms'],
                ['payment-worker', Colors::wrap('down',     Colors::RED),    '—'],
            ])
            ->style($style)
            ->render();
    }

    pauseForUser();
}

function demoAlert(): void
{
    banner('Alert');
    Colors::line('  Bordered notification boxes in four severity levels.', Colors::GRAY);
    echo PHP_EOL;

    Alert::success('Deployment complete!', ['Version: 2.4.1', 'Region: eu-west-1']);
    Alert::error('Build failed', ['Exit code: 1', 'Check /var/log/build.log']);
    Alert::warning('API quota at 80%', ['Resets in 4 hours']);
    Alert::info('Maintenance window tonight 02:00–04:00 UTC');

    pauseForUser();
}

function demoProgressBar(): void
{
    banner('ProgressBar');
    Colors::line('  Determinate (fill + ETA) and indeterminate (bounce) modes.', Colors::GRAY);
    echo PHP_EOL;

    Colors::line('  Determinate (30 steps):', Colors::BOLD);
    $bar = new ProgressBar('Processing records', 30);
    $bar->start();
    for ($i = 0; $i < 30; $i++) {
        usleep(40_000);
        $bar->advance(1, "Record #{$i}");
    }
    $bar->finish('All 30 records processed');

    echo PHP_EOL;
    Colors::line('  Indeterminate (bounce, 2 s):', Colors::BOLD);
    $ind = new ProgressBar('Waiting for lock');
    $ind->start();
    for ($i = 0; $i < 40; $i++) {
        usleep(50_000);
        $ind->tick('Attempt ' . ($i + 1));
    }
    $ind->finish('Lock acquired');

    pauseForUser();
}

function demoSpinner(): void
{
    banner('SpinnerComponent');
    Colors::line('  Non-blocking animated spinner. Six built-in frame styles.', Colors::GRAY);
    echo PHP_EOL;

    $styles = ['dots', 'line', 'bars', 'pulse', 'arc', 'bounce'];

    foreach ($styles as $style) {
        $spin = new SpinnerComponent("Style: {$style}", $style);
        $spin->start();
        for ($i = 0; $i < 18; $i++) {
            usleep(80_000);
            $spin->tick('Running…');
        }
        $spin->stop("Finished: {$style}");
    }

    pauseForUser();
}

function demoShell(): void
{
    banner('Shell Integration');
    Colors::line('  Shell::run with SpinnerComponent — live output, no deadlocks.', Colors::GRAY);
    echo PHP_EOL;

    $spin = new SpinnerComponent('Checking environment', 'arc');
    $spin->start();

    $result = Shell::run(
        'php -r "
            echo \"PHP  : \" . PHP_VERSION . PHP_EOL;
            echo \"OS   : \" . PHP_OS_FAMILY . PHP_EOL;
            echo \"SAPI : \" . php_sapi_name() . PHP_EOL;
        "',
        tick: fn(string $line) => $spin->tick($line),
    );

    if ($result->ok()) {
        $spin->stop('Environment checked');
        foreach ($result->stdout as $line) {
            Colors::line("  {$line}", Colors::GREEN);
        }
    } else {
        $spin->fail('Command failed');
        Alert::error('Shell error', $result->meaningfulErrors());
    }

    pauseForUser();
}

// ── Main menu loop ────────────────────────────────────────────────────────────

$menu = [
    '1. TextInput'          => 'demoTextInput',
    '2. NumberInput'        => 'demoNumberInput',
    '3. Password'           => 'demoPassword',
    '4. Confirm'            => 'demoConfirm',
    '5. Select'             => 'demoSelect',
    '6. MultiSelect'        => 'demoMultiSelect',
    '7. Autocomplete'       => 'demoAutocomplete',
    '8. DatePicker'         => 'demoDatePicker',
    '9. Table'              => 'demoTable',
    '10. Alert'             => 'demoAlert',
    '11. ProgressBar'       => 'demoProgressBar',
    '12. SpinnerComponent'  => 'demoSpinner',
    '13. Shell Integration' => 'demoShell',
    '─────────────────'     => null,
    'Exit'                  => null,
];

$choices = array_keys($menu);

while (true) {
    echo PHP_EOL;
    Colors::line('  ██████╗ ██╗  ██╗██████╗       ██╗ ██████╗      ██████╗██╗     ██╗', Colors::CYAN);
    Colors::line('  ██╔══██╗██║  ██║██╔══██╗      ██║██╔═══██╗    ██╔════╝██║     ██║', Colors::CYAN);
    Colors::line('  ██████╔╝███████║██████╔╝█████╗██║██║   ██║    ██║     ██║     ██║', Colors::CYAN);
    Colors::line('  ██╔═══╝ ██╔══██║██╔═══╝ ╚════╝██║██║   ██║    ██║     ██║     ██║', Colors::CYAN);
    Colors::line('  ██║     ██║  ██║██║           ██║╚██████╔╝    ╚██████╗███████╗██║', Colors::CYAN);
    Colors::line('  ╚═╝     ╚═╝  ╚═╝╚═╝           ╚═╝ ╚═════╝      ╚═════╝╚══════╝╚═╝', Colors::CYAN);
    echo PHP_EOL;
    Colors::line('  Interactive component demo — pick a component to explore', Colors::GRAY);
    echo PHP_EOL;

    $pick = (new Select('Which component?', $choices))->run();

    if ($pick === 'Exit' || $pick === '─────────────────') {
        break;
    }

    $fn = $menu[(string) $pick] ?? null;
    if ($fn !== null && function_exists($fn)) {
        $fn();
    }
}

echo PHP_EOL;
Colors::line('  Thanks for exploring php-io-cli! 🚀', [Colors::BOLD, Colors::GREEN]);
Colors::line('  https://github.com/alfacode-team/php-io-cli', Colors::GRAY);
echo PHP_EOL;
