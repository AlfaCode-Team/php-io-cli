# php-io-cli

> **Interactive CLI runtime for PHP microservice and hexagonal architectures.**
> Reactive terminal components, structured command execution, and a unified I/O layer — designed for OpenSwoole, multi-repository platforms, and production backend systems.

---

## Table of Contents

- [Overview](#overview)
- [Installation](#installation)
- [Architecture](#architecture)
- [Building Commands](#building-commands)
  - [Defining a Command](#defining-a-command)
  - [Arguments & Options](#arguments--options)
  - [Output Helpers](#output-helpers)
- [Interactive Components](#interactive-components)
  - [TextInput](#textinput)
  - [Password](#password)
  - [NumberInput](#numberinput)
  - [Confirm](#confirm)
  - [Select](#select)
  - [MultiSelect](#multiselect)
  - [Autocomplete](#autocomplete)
  - [DatePicker](#datepicker)
- [Display Components](#display-components)
  - [Table](#table)
  - [Alert](#alert)
  - [ProgressBar](#progressbar)
  - [SpinnerComponent](#spinnercomponent)
- [Shell Execution](#shell-execution)
  - [Shell::run()](#shellrun)
  - [Shell + ProgressBar Pattern](#shell--progressbar-pattern)
- [I/O Layer](#io-layer)
  - [ConsoleIO](#consoleio)
  - [BufferIO](#bufferio)
  - [NullIO](#nullio)
- [Application Bootstrap](#application-bootstrap)
  - [CLIApplication](#cliapplication)
  - [Command Discovery](#command-discovery)
- [Internals](#internals)
  - [Lifecycle: AbstractPrompt](#lifecycle-abstractprompt)
  - [State](#state)
  - [Input Bindings](#input-bindings)
  - [Hooks (Event Bus)](#hooks-event-bus)
  - [Colors](#colors)
  - [Terminal Driver](#terminal-driver)
- [Testing](#testing)
- [Requirements](#requirements)

---

## Overview

`php-io-cli` is a self-contained CLI framework for PHP 8.2+. It provides:

- **Reactive terminal components** with ANSI-safe rendering and flicker-free redraws
- **A structured command layer** (`AbstractCommand`) with argument/option parsing, help generation, and typed component factory methods
- **A unified I/O interface** (`IOInterface`) bridging Symfony Console, PSR-3 logging, and reactive components under one API
- **A `Shell` driver** that streams stdout/stderr without deadlocks and feeds live output into `ProgressBar` animations
- **A self-bootstrapping `CLIApplication`** with Composer-based command discovery, fuzzy "did you mean?" suggestions, and built-in `list`, `help`, and `version` commands

The design is inspired by Composer's CLI internals and Laravel Zero's component model, adapted for multi-repository hexagonal architectures where commands are distributed across packages.

---

## Installation

```bash
composer require alfacode-team/php-io-cli
```

**Requirements:** PHP 8.2+, `psr/log: ^3.0`. Symfony Console is an optional dev dependency used only by `ConsoleIO` and `BufferIO`.

---

## Architecture

```
CLIApplication
    └── AbstractCommand          # Your commands extend this
          ├── IOInterface        # Unified I/O (write, ask, select …)
          │     ├── ConsoleIO    # Real terminal — delegates to reactive components on TTY
          │     ├── BufferIO     # In-memory capture for testing
          │     └── NullIO       # Silent, returns defaults (CI / daemons)
          └── Components
                ├── TextInput / Password / NumberInput / Confirm
                ├── Select / MultiSelect / Autocomplete / DatePicker
                ├── Table / Alert / ProgressBar / SpinnerComponent
                └── AbstractPrompt → ILifecycle (mount/render/update/destroy)
                      ├── State          # Reactive key-value store with watchers
                      ├── Input          # Key binding dispatcher
                      ├── Renderer       # Scroll windowing, cursor management
                      └── Terminal       # Raw mode, escape sequences, cross-platform
```

---

## Building Commands

### Defining a Command

Extend `AbstractCommand`, implement `configure()` to declare metadata, and `handle()` to run your logic.

```php
use AlfacodeTeam\PhpIoCli\AbstractCommand;

final class DeployCommand extends AbstractCommand
{
    protected function configure(): void
    {
        $this->name        = 'deploy';
        $this->description = 'Deploy the application to a target environment';

        $this->addArgument('environment', 'Target environment (production, staging)', required: true);
        $this->addOption('dry-run', 'd', 'Simulate the deployment without side-effects');
        $this->addOption('tag',     't', 'Git tag to deploy',  acceptsValue: true);
    }

    protected function handle(): int
    {
        $env    = $this->argument('environment');
        $dryRun = $this->hasOption('dry-run');
        $tag    = $this->option('tag', 'latest');

        if (!$this->confirm("Deploy {$tag} to {$env}?")) {
            $this->muted('Aborted.');
            return self::SUCCESS;
        }

        // ... deployment logic

        $this->alertSuccess("Deployed {$tag} → {$env}");
        return self::SUCCESS;
    }
}
```

Return one of the three exit code constants from `handle()`:

| Constant | Value | Meaning |
|---|---|---|
| `self::SUCCESS` | `0` | Command completed normally |
| `self::FAILURE` | `1` | Command failed |
| `self::INVALID` | `2` | Bad input / missing required arguments |

### Arguments & Options

```php
// Positional argument
$this->addArgument(
    name:        'name',
    description: 'Module name in kebab-case',
    required:    true,
    default:     null,
);

// Long option  (--force)
// Short option (-f)
// Flag (no value) or value-accepting option
$this->addOption(
    long:         'force',
    short:        'f',
    description:  'Skip confirmation prompts',
    acceptsValue: false,
    default:      null,
);

// Value option: --tag=v1.2.0  or  --tag v1.2.0
$this->addOption('tag', 't', 'Git tag', acceptsValue: true, default: 'latest');
```

Retrieving values inside `handle()`:

```php
$env    = $this->argument('environment');        // string|null
$force  = $this->hasOption('force');             // bool
$tag    = $this->option('tag', 'latest');        // mixed, with fallback default
```

### Output Helpers

All output methods are available inside `handle()` without touching `IOInterface` directly.

```php
$this->info('Connecting to database…');          // cyan text
$this->success('Migration complete.');           // ✔ green
$this->warning('Disk usage above 80%.');         // ! yellow (stderr)
$this->error('Connection refused.');             // ✘ red   (stderr)
$this->muted('Skipped — already exists.');       // dim gray

$this->section('Build Pipeline');                // bold cyan heading + underline
$this->newLine(2);                               // blank lines

// Inline component factory shortcuts
$name    = $this->ask('Project name');
$env     = $this->select('Target', ['prod', 'staging', 'dev']);
$confirm = $this->confirm('Continue?');
$bar     = $this->progressBar('Installing', 10);
$spin    = $this->spinner('Compiling');
$table   = $this->table();

// Alert boxes
$this->alertSuccess('Deployed!', ['Version: 2.4.1', 'Region: eu-west-1']);
$this->alertError('Build failed', ['Check logs at /var/log/build.log']);
$this->alertWarning('API rate limit at 80%');
$this->alertInfo('New version available: 3.0.0');
```

---

## Interactive Components

All components implement `IPromptComponent::run(): mixed` — call `->run()` to start the reactive loop and block until the user submits.

Every component is usable standalone or through the `AbstractCommand` factory helpers. They activate raw terminal mode automatically and restore it on exit, including on `Ctrl+C`.

### TextInput

Free-text input with virtual block cursor, inline validation, placeholder, default value, and `HOME`/`END` navigation.

```php
use AlfacodeTeam\PhpIoCli\Components\TextInput;

$hostname = (new TextInput('Database hostname'))
    ->placeholder('localhost')
    ->default('127.0.0.1')
    ->validate(function (string $value): ?string {
        return filter_var($value, FILTER_VALIDATE_IP) || $value === 'localhost'
            ? null                          // null = valid
            : 'Must be a valid IP or hostname';
    })
    ->run();
```

| Key | Action |
|---|---|
| Printable chars | Insert at cursor |
| `←` / `→` | Move cursor |
| `HOME` / `END` | Jump to start / end |
| `Backspace` / `Delete` | Delete left / right |
| `Enter` | Submit (runs validator) |

### Password

Masked input with toggle visibility, live strength meter, and `●` masking.

```php
use AlfacodeTeam\PhpIoCli\Components\Password;

$secret = (new Password('Encryption key'))
    ->showStrength()   // renders a 5-point strength bar
    ->run();
```

| Key | Action |
|---|---|
| Printable chars | Append to value |
| `Backspace` | Delete last character |
| `TAB` | Toggle plaintext / masked |
| `Enter` | Submit |

Strength scoring: length ≥ 8, length ≥ 12, uppercase, digit, special character — one point each. Displayed as `Very weak` → `Weak` → `Fair` → `Good` → `Strong`.

### NumberInput

Numeric input with arrow-key stepping, min/max clamping, integer mode, and an inline range hint.

```php
use AlfacodeTeam\PhpIoCli\Components\NumberInput;

$port = (new NumberInput('Server port'))
    ->min(1)
    ->max(65535)
    ->default(8080)
    ->step(1)
    ->integer()       // rejects decimal input
    ->run();           // returns int
```

| Key | Action |
|---|---|
| Digits / `-` / `.` | Append character |
| `Backspace` | Delete last character |
| `↑` / `↓` | Increment / decrement by step |
| `Enter` | Submit (validates range) |

### Confirm

Boolean toggle rendered as highlighted buttons.

```php
use AlfacodeTeam\PhpIoCli\Components\Confirm;

$proceed = (new Confirm('Overwrite existing files?', default: false))->run(); // bool
```

| Key | Action |
|---|---|
| `y` / `Y` | Set Yes |
| `n` / `N` | Set No |
| `←` / `→` | Toggle |
| `Enter` | Confirm |

### Select

Searchable single-selection list with fuzzy filtering and scroll windowing (8 items visible).

```php
use AlfacodeTeam\PhpIoCli\Components\Select;

$region = (new Select('Deploy region', [
    'eu-west-1', 'us-east-1', 'ap-southeast-1', 'us-west-2',
]))->run(); // string
```

| Key | Action |
|---|---|
| Printable chars | Filter list (fuzzy) |
| `↑` / `↓` | Navigate |
| `Backspace` | Delete filter character |
| `Enter` | Select highlighted item |

### MultiSelect

Checkbox list with spacebar toggle.

```php
use AlfacodeTeam\PhpIoCli\Components\MultiSelect;

$features = (new MultiSelect('Enable features', [
    'Auth', 'API Gateway', 'Queue Worker', 'Scheduler', 'Websockets',
]))->run(); // string[]
```

| Key | Action |
|---|---|
| `↑` / `↓` | Navigate |
| `Space` | Toggle selected |
| `Enter` | Confirm selection |

### Autocomplete

Text input with a live fuzzy-search dropdown. Useful for large suggestion sets.

```php
use AlfacodeTeam\PhpIoCli\Components\Autocomplete;

$package = (new Autocomplete('Package name', $allPackages))
    ->maxSuggestions(8)
    ->run(); // string
```

| Key | Action |
|---|---|
| Printable chars | Type / filter |
| `↑` / `↓` | Navigate dropdown |
| `TAB` | Fill highlighted suggestion |
| `Enter` | Confirm (fills suggestion first if divergent) |
| `Backspace` | Delete character |

### DatePicker

Interactive calendar grid with week and month navigation.

```php
use AlfacodeTeam\PhpIoCli\Components\DatePicker;

$date = (new DatePicker('Release date'))->run(); // DateTimeImmutable
echo $date->format('Y-m-d');
```

| Key | Action |
|---|---|
| `←` / `→` | Previous / next day |
| `↑` / `↓` | Previous / next week |
| `[` / `]` | Previous / next month |
| `t` | Jump to today |
| `Enter` | Confirm |

---

## Display Components

### Table

Unicode box-drawing table with ANSI-safe column width calculation, alignment, and alternating row shading.

```php
use AlfacodeTeam\PhpIoCli\Components\Table;

Table::make()
    ->headers(['Service', 'Status', 'Latency'])
    ->rows([
        ['api-gateway',   Colors::wrap('healthy',  Colors::GREEN), '12 ms'],
        ['auth-service',  Colors::wrap('degraded', Colors::YELLOW), '340 ms'],
        ['payment-worker',Colors::wrap('down',     Colors::RED),    '—'],
    ])
    ->style('box')       // 'box' (default) | 'bold' | 'compact' | 'minimal'
    ->align([0 => 'left', 2 => 'right'])
    ->striped()
    ->render();
```

All four border styles:

| Style | Characters |
|---|---|
| `box` | `╔ ╗ ╚ ╝ ═ ║` (double-line, default) |
| `bold` | `┏ ┓ ┗ ┛ ━ ┃` |
| `compact` | `┌ ┐ └ ┘ ─ │` |
| `minimal` | space-separated, horizontal rules only |

Column widths are measured on the **visual** (stripped) string, so ANSI color codes in cells never corrupt alignment.

### Alert

Attention-grabbing bordered notification boxes.

```php
use AlfacodeTeam\PhpIoCli\Components\Alert;

Alert::success('Deployment complete!', ['Version: 2.4.1', 'Uptime: 99.98%']);
Alert::error('Migration failed', ['Error: duplicate key constraint on users.email']);
Alert::warning('API quota at 80%', ['Resets in 4 hours']);
Alert::info('Maintenance window tonight 02:00–04:00 UTC');
```

Each variant renders a Unicode box with a coloured icon and optional multi-line body section. Use `Alert::block()` for solid-background enterprise-style error blocks.

### ProgressBar

Determinate (percentage fill) and indeterminate (bounce) progress bars.

**Determinate** — tracks a known number of steps with ETA and throughput:

```php
$bar = new ProgressBar('Processing records', total: 1000);
$bar->start();

foreach ($records as $record) {
    process($record);
    $bar->advance();       // +1 step
}

$bar->finish('All records processed');
```

**Indeterminate** — animating bounce bar for unknown-duration work:

```php
$bar = new ProgressBar('Waiting for lock');  // total=0 → indeterminate
$bar->start();
// ... do work ...
$bar->finish('Lock acquired');
```

Fluent configuration:

```php
$bar->width(60)           // bar width in characters (default 40)
    ->fill('▓')           // fill character
    ->empty('░');         // empty character
```

Determinate bars automatically render:
- Colour-coded fill: red → yellow → cyan → green as percentage climbs
- `current / total` counter
- Items/second throughput
- ETA in seconds

### SpinnerComponent

Non-blocking spinner for wrapping long-running tasks. Best used when integrated with `Shell::run()` (see [Shell + ProgressBar Pattern](#shell--progressbar-pattern)).

```php
use AlfacodeTeam\PhpIoCli\Components\SpinnerComponent;
use AlfacodeTeam\PhpIoCli\Depends\SpinnerFrames;

$spin = new SpinnerComponent('Connecting to cluster', style: 'dots');
$spin->start();

$result = doSlowWork();

$result->ok()
    ? $spin->stop('Connected')
    : $spin->fail('Connection refused');
```

Available styles: `dots` (default), `line`, `bars`, `pulse`, `arc`, `bounce`.

---

## Shell Execution

### Shell::run()

`Shell::run()` executes a command via `proc_open` and streams stdout and stderr simultaneously using `stream_select()`, eliminating the classic pipe-deadlock problem. It fires a `$tick` callback on every `≤50 ms` poll cycle, making it trivially composable with animated UI components.

```php
use AlfacodeTeam\PhpIoCli\Depends\Shell;

$result = Shell::run(
    command: 'composer install --no-interaction',
    tick:    function (string $lastLine, bool $isStderr): void {
        // called every 50 ms with the most recent output line
    },
    env:     ['COMPOSER_NO_INTERACTION' => '1'],
    cwd:     '/var/www/app',
);

if ($result->failed()) {
    echo $result->errors();   // all stderr joined
    exit($result->exitCode);
}

echo $result->output();       // all stdout joined
```

`Shell::capture()` is a convenience wrapper for quick value reads:

```php
$branch = Shell::capture('git rev-parse --abbrev-ref HEAD', cwd: $projectRoot);
// returns trimmed stdout string, or null on failure
```

**`ShellResult` API:**

```php
$result->ok();                  // exitCode === 0
$result->failed();              // exitCode !== 0
$result->exitCode;              // int
$result->output();              // stdout joined with PHP_EOL
$result->errors();              // stderr joined with PHP_EOL
$result->meaningfulErrors();    // non-empty stderr lines as string[]
$result->stdout;                // string[] raw lines
$result->stderr;                // string[] raw lines
```

### Shell + ProgressBar Pattern

The canonical pattern for animated shell steps in commands: pass the live `ProgressBar` into `Shell::run()`'s tick, and call `advance(0)` to redraw without moving `$current` forward. Only one `ProgressBar` instance draws at a time — no terminal fighting.

```php
protected function handle(): int
{
    $overall = $this->progressBar('Deploying', total: 4);
    $overall->start();

    // Each shell step redraws the same bar on every 50 ms tick.
    // advance(0) = redraw only; advance() = redraw + increment.
    $result = Shell::run(
        'composer install --no-dev',
        tick: fn() => $overall->advance(0),
        cwd:  $this->projectRoot(),
    );

    if ($result->failed()) {
        $overall->finish('Aborted');
        $this->alertError('composer install failed', $result->meaningfulErrors());
        return self::FAILURE;
    }

    $overall->advance();   // step 1 complete → fill moves forward

    // Pure-PHP steps need no tick — just do the work and advance.
    $this->generateConfig();
    $overall->advance();   // step 2 complete

    // ... more steps ...

    $overall->finish('Deployment complete');
    return self::SUCCESS;
}
```

> **Why not nest two bars?** Each `ProgressBar` instance tracks `$lastLines` independently. If two instances are live simultaneously, their `moveCursorUp()` calls interfere, producing interleaved frames. Always use a single instance per command and pass it by reference into helpers.

---

## I/O Layer

### ConsoleIO

The production I/O implementation. On a real TTY (`posix_isatty(STDIN) === true`) every interactive method delegates to the corresponding reactive component. On non-interactive streams (piped input, CI) it falls back gracefully to Symfony's `QuestionHelper`.

```php
use AlfacodeTeam\PhpIoCli\ConsoleIO;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;

$io = new ConsoleIO(
    new ArgvInput(),
    new ConsoleOutput(),
    new HelperSet([new QuestionHelper()]),
);

$io->ask('Project name');
$io->askConfirmation('Continue?');
$io->askAndValidate('Email', fn($v) => filter_var($v, FILTER_VALIDATE_EMAIL) ? $v : throw new \RuntimeException('Invalid email'));
$io->askAndHideAnswer('Password');
$io->select('Environment', ['prod', 'staging'], default: 'staging');
$io->select('Features', ['Auth', 'API', 'Queue'], default: 0, multiselect: true);
```

Verbosity levels mirror Symfony's constants and are set via `--verbose` / `--very-verbose` / `--debug` flags:

```php
$io->write('Always visible',      verbosity: IOInterface::NORMAL);
$io->write('With --verbose',      verbosity: IOInterface::VERBOSE);
$io->write('With --very-verbose', verbosity: IOInterface::VERY_VERBOSE);
$io->write('With --debug',        verbosity: IOInterface::DEBUG);
```

`ConsoleIO` also implements `LoggerInterface` (PSR-3) — severity levels map to ANSI-themed output and stderr automatically.

Enable debug timing (prepends `[MiB/seconds]` to every line):

```php
$io->enableDebugging(microtime(true));
```

### BufferIO

In-memory I/O implementation for testing. Captures all output and simulates user input via pre-set answer streams.

```php
use AlfacodeTeam\PhpIoCli\BufferIO;

$io = new BufferIO();
$io->setUserInputs(['my-module', 'y', '']);   // simulated keystrokes

$command->execute(['module:add', 'my-module', 'git@…', 'acme'], $io);

$output = $io->getOutput();   // captured, ANSI-stripped string
assertStringContainsString('added successfully', $output);
```

`getOutput()` strips all ANSI/VT100 escape sequences and cleans up backspace characters, returning a plain readable string safe for `assertStringContainsString`.

### NullIO

Completely silent implementation — every write is a no-op, every interactive method returns its `$default`. Ideal for daemons, background workers, and unit tests that don't care about output.

```php
use AlfacodeTeam\PhpIoCli\NullIO;

$io = new NullIO();
$io->ask('Name', 'fallback');         // returns 'fallback'
$io->askConfirmation('Sure?', true);  // returns true
$io->select('Env', ['prod'], 'prod'); // returns 'prod'
$io->write('ignored');                // no-op
```

---

## Application Bootstrap

### CLIApplication

`CLIApplication` is the entry point for your CLI binary. It auto-builds the I/O layer, dispatches commands, handles exceptions, and provides built-in `list`, `help`, and `version` commands.

```php
#!/usr/bin/env php
<?php
require __DIR__ . '/vendor/autoload.php';

(new AlfacodeTeam\PhpIoCli\CLIApplication('MyPlatform', '2.1.0'))
    ->discoverCommands(__DIR__ . '/composer.json')
    ->add(new App\Commands\ServeCommand())
    ->run();
```

Built-in commands available automatically:

| Command | Description |
|---|---|
| `list` | All registered commands grouped by namespace |
| `help <command>` | Detailed usage for a specific command |
| `version` | Application name and version |

Global flags:

| Flag | Effect |
|---|---|
| `--no-ansi` | Disable all ANSI color output |
| `--debug` / `-d` | Enable debug verbosity + timing prefix |

**Not-found handling:** when an unknown command is typed, `CLIApplication` computes Levenshtein distances against all registered names and offers up to three suggestions. On a real TTY with multiple matches, an interactive `Select` picker is shown.

```php
// Swap I/O for tests or custom environments
$app->withIO(new BufferIO());

// Prevent the application from swallowing exceptions (e.g. in tests)
$app->catchExceptions(false);
```

### Command Discovery

Register commands explicitly or via Composer's `extra` block for zero-configuration auto-discovery across multiple packages.

**Explicit registration:**
```php
$app->add(
    new App\Commands\MigrateCommand(),
    new App\Commands\SeedCommand(),
    new App\Commands\ServeCommand(),
);
```

**Composer auto-discovery** — add to your project's `composer.json` (not the library's):

```json
{
  "extra": {
    "php-io-cli": {
      "commands": [
        "App\\Commands\\MigrateCommand",
        "App\\Commands\\SeedCommand",
        "App\\Commands\\ServeCommand",
        "Modules\\Auth\\Commands\\CreateUserCommand"
      ]
    }
  }
}
```

Then call `discoverCommands()` in your bootstrap:

```php
$app->discoverCommands('/path/to/composer.json');
// or omit the path to auto-detect from the project root:
$app->discoverCommands();
```

Classes that are absent, abstract, or not an `AbstractCommand` subclass are silently skipped (logged under `--debug`). Commands are grouped by namespace in the `list` output — the segment before the first `:` is the group name (`module:add` → group `module`).

---

## Internals

### Lifecycle: AbstractPrompt

Every interactive component extends `AbstractPrompt`, which drives the reactive event loop. The loop is started by `run()` and runs until `stop()` is called (typically from an `ENTER` binding).

```
run()
 ├── Terminal::enableRaw()
 ├── mount()          ← Component::setup() — wire State + Input bindings
 │
 └── loop:
       ├── render()   ← draw the current frame to the terminal
       ├── readKey()  ← block until a keypress arrives
       └── update()   ← dispatch key through Input bindings → mutate State
           → context->markDirty() → triggers re-render on next cycle
```

On `CTRL+C` the loop calls `handleCancel()` which prints a cancellation message and exits cleanly. On any exception, `handleError()` prints the error before re-throwing. `destroy()` and `Terminal::disableRaw()` run in a `finally` block, guaranteeing terminal restoration.

### State

`State` is a reactive key-value container. Property access uses `__get` / `__set`. Watchers fire synchronously on change.

```php
use AlfacodeTeam\PhpIoCli\Depends\State;

$state = new State(['count' => 0, 'selected' => []]);

// Magic access
$state->count = 5;
echo $state->count;   // 5

// Batch update (single notification burst)
$state->batch(['index' => 0, 'search' => '', 'done' => false]);

// Navigation helpers
$state->increment('index', max: 9);   // clamps at max
$state->decrement('index');            // clamps at 0

// Multi-select toggle
$state->toggle('selected', 'Auth');   // adds if absent, removes if present

// Reactivity
$state->watch('index', function (mixed $new, mixed $old, State $state): void {
    // fires whenever 'index' changes
});
```

### Input Bindings

`Input` maps normalized key names to handler closures. Handlers receive `(State $state, string $key)` and may return `false` to stop propagation.

```php
use AlfacodeTeam\PhpIoCli\Depends\Input;

$input = new Input();

// Single key
$input->bind('ENTER', fn($s) => $this->stop());

// Multiple keys to same handler
$input->bind(['y', 'Y'], fn($s) => $s->confirmed = true);

// Fallback for unbound keys (typically used for character typing)
$input->fallback(function ($state, $key): void {
    if (Key::isPrintable($key)) {
        $state->value .= $key;
    }
});

// Stop propagation
$input->bind('UP', function ($state, $key): false {
    $state->decrement('index');
    return false;   // prevents any further handlers for this key
});

// Remove a binding
$input->unbind('ESC');
```

Normalized key names: `UP`, `DOWN`, `LEFT`, `RIGHT`, `HOME`, `END`, `ENTER`, `TAB`, `ESC`, `BACKSPACE`, `DELETE`, `CTRL_C`, `CTRL_D`, printable characters as-is.

### Hooks (Event Bus)

`Hooks` provides a lightweight pub/sub event bus wired into `AbstractPrompt`'s lifecycle. Standard lifecycle events: `mount`, `render`, `update`, `submit`, `destroy`.

```php
use AlfacodeTeam\PhpIoCli\Hooks;

$hooks = new Hooks();

// Subscribe
$hooks->on('submit', function (mixed $value, string $event, Hooks $hooks): void {
    Log::info("User submitted: {$value}");
});

// Subscribe once then auto-unsubscribe
$hooks->once('mount', fn() => $this->loadDefaults());

// Dispatch
$hooks->dispatch('submit', $resolvedValue);

// Chain of Responsibility — stops at first non-null return
$handled = $hooks->dispatchUntil('validate', $inputValue);

// Unsubscribe specific listener or all listeners for an event
$hooks->off('render', $specificHandler);
$hooks->off('render');   // remove all
```

### Colors

`Colors` manages ANSI output with automatic environment detection (`NO_COLOR`, `FORCE_COLOR`, Windows VT100, Unix TTY).

```php
use AlfacodeTeam\PhpIoCli\Depends\Colors;

// Wrap with style constants
Colors::wrap('Hello', Colors::BOLD);
Colors::wrap('Hello', [Colors::BOLD, Colors::CYAN]);

// Semantic helpers
Colors::success('Done');      // ✔ green bold
Colors::error('Failed');      // ✘ red bold
Colors::warning('Caution');   // ! yellow bold
Colors::info('Note');         // cyan
Colors::muted('Skipped');     // dim gray

// True-color hex
Colors::hex('#e94560', 'Alert!');

// Print line directly
Colors::line('Status: OK', [Colors::GREEN, Colors::BOLD]);

// Strip all ANSI sequences (for testing / width measurement)
$clean = Colors::strip($ansiString);

// Force on/off regardless of environment
Colors::enable();
Colors::disable();
```

### Terminal Driver

`Terminal` handles raw mode, input reading, and output cursor control. It is used internally by all components — you rarely need to call it directly.

```php
use AlfacodeTeam\PhpIoCli\Depends\Terminal;

Terminal::enableRaw();       // disable echo + canonical mode
Terminal::disableRaw();      // restore saved tty state

$key = Terminal::readKey();  // block until input; returns escape sequences as a single string

Terminal::hideCursor();
Terminal::showCursor();
Terminal::clearLine();       // \r\033[2K
Terminal::moveCursorUp(3);   // \033[3A
```

Raw mode is automatically restored on shutdown via `register_shutdown_function`. On Unix, `SIGINT` and `SIGTERM` are caught via `pcntl_signal` to ensure clean exit. On Windows, `sapi_windows_vt100_support()` is called to enable VT100 sequences in modern terminals.

Escape sequence reading uses a 10 ms settle window (`stream_set_blocking(STDIN, false)` + `microtime()` polling) to collect multi-byte sequences (arrows, Home, Delete) as a single string, preventing the classic "ghost character" problem.

---

## Testing

Use `BufferIO` to test commands without a TTY. Pre-set user inputs with `setUserInputs()` — each string maps to one prompt answer in order.

```php
use AlfacodeTeam\PhpIoCli\BufferIO;
use PHPUnit\Framework\TestCase;

class ModuleAddCommandTest extends TestCase
{
    public function test_adds_module_successfully(): void
    {
        $io = new BufferIO();
        $io->setUserInputs([
            'y',           // Confirm prompt
        ]);

        $command = new ModuleAddCommand();
        $exit    = $command->execute(
            ['my-module', 'git@github.com:acme/my-module.git', 'acme'],
            $io
        );

        $this->assertSame(AbstractCommand::SUCCESS, $exit);
        $this->assertStringContainsString('added successfully', $io->getOutput());
    }

    public function test_aborts_when_user_declines(): void
    {
        $io = new BufferIO();
        $io->setUserInputs(['n']);   // decline confirmation

        $command = new ModuleAddCommand();
        $exit    = $command->execute(['my-module', 'git@…', 'acme'], $io);

        $this->assertSame(AbstractCommand::SUCCESS, $exit);
        $this->assertStringContainsString('Aborted', $io->getOutput());
    }
}
```

`BufferIO::getOutput()` returns ANSI-stripped output with backspace sequences cleaned up, making plain-string assertions reliable regardless of terminal formatting.

For commands that must not produce any output (daemon processes, silent pipelines), use `NullIO` and assert only the return code.

---

## Requirements

| Requirement | Version |
|---|---|
| PHP | `^8.2` |
| `psr/log` | `^3.0` |
| `symfony/console` | `^6.0 \|\| ^7.0` *(dev, optional at runtime)* |
| OS | Linux, macOS, Windows (VT100 terminal) |

**PHP extensions used:** `pcntl` (signal handling, Unix only), `posix` (TTY detection, Unix only), `mbstring` (multibyte string operations). All extension calls are guarded with `function_exists()` — the library degrades gracefully when they are absent.

---

## License

MIT © 2026 AlfaCode Team. See [LICENSE](LICENSE) for full terms.
