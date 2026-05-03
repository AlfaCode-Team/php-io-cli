#!/usr/bin/env php
<?php

/**
 * php-io-cli — Example: All Interactive Input Components
 *
 * Run: php examples/01-inputs.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use AlfacodeTeam\PhpIoCli\Components\Autocomplete;
use AlfacodeTeam\PhpIoCli\Components\Confirm;
use AlfacodeTeam\PhpIoCli\Components\DatePicker;
use AlfacodeTeam\PhpIoCli\Components\MultiSelect;
use AlfacodeTeam\PhpIoCli\Components\NumberInput;
use AlfacodeTeam\PhpIoCli\Components\Password;
use AlfacodeTeam\PhpIoCli\Components\Select;
use AlfacodeTeam\PhpIoCli\Components\TextInput;
use AlfacodeTeam\PhpIoCli\Depends\Colors;

Colors::line("\n  php-io-cli — Interactive Components Demo\n", [Colors::BOLD, Colors::CYAN]);

// ── 1. Text Input ─────────────────────────────────────────────────

$name = (new TextInput('What is your name?'))
    ->placeholder('e.g. Alice')
    ->default('World')
    ->validate(fn(string $value): ?string => mb_strlen($value) >= 2 ? null : 'Name must be at least 2 characters.')
    ->run();

Colors::line("  → Name: {$name}", Colors::GREEN);

// ── 2. Number Input ───────────────────────────────────────────────

$port = (new NumberInput('Server port?'))
    ->min(1)
    ->max(65535)
    ->default(8080)
    ->step(100)
    ->integer()
    ->run();

Colors::line("  → Port: {$port}", Colors::GREEN);

// ── 3. Password ───────────────────────────────────────────────────

$secret = (new Password('Enter a password'))
    ->showStrength()
    ->run();

Colors::line("  → Password length: " . mb_strlen((string) $secret) . " chars", Colors::GREEN);

// ── 4. Confirm ────────────────────────────────────────────────────

$confirmed = (new Confirm("Do you want to continue?", true))->run();

Colors::line("  → Confirmed: " . ($confirmed ? 'Yes' : 'No'), Colors::GREEN);

// ── 5. Select ─────────────────────────────────────────────────────

$environment = (new Select('Select deployment environment', [
    'production',
    'staging',
    'development',
    'local',
]))->run();

Colors::line("  → Environment: {$environment}", Colors::GREEN);

// ── 6. Multi Select ───────────────────────────────────────────────

$features = (new MultiSelect('Which features to enable?', [
    'Authentication',
    'API Gateway',
    'Queue Worker',
    'Scheduler',
    'WebSockets',
    'Rate Limiting',
]))->run();

Colors::line("  → Features: " . implode(', ', $features), Colors::GREEN);

// ── 7. Autocomplete ───────────────────────────────────────────────

$framework = (new Autocomplete('Pick a PHP framework', [
    'Laravel', 'Symfony', 'Slim', 'Laminas', 'CodeIgniter',
    'Yii', 'CakePHP', 'Phalcon', 'Lumen', 'Hyperf',
]))
    ->maxSuggestions(5)
    ->run();

Colors::line("  → Framework: {$framework}", Colors::GREEN);

// ── 8. DatePicker ─────────────────────────────────────────────────

$date = (new DatePicker('Select a release date'))->run();

Colors::line("  → Date: " . $date->format('Y-m-d'), Colors::GREEN);

// ── Summary ───────────────────────────────────────────────────────

echo PHP_EOL;
Colors::line("  All inputs collected successfully!", [Colors::BOLD, Colors::GREEN]);
echo PHP_EOL;
