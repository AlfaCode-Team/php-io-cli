# Changelog

All notable changes to `php-io-cli` are documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).
This project follows [Semantic Versioning](https://semver.org/).

---

## [Unreleased]

### Planned
- SliderInput component
- RadioGroup component
- Searchable tree component
- Windows VT100 full test coverage
- PHP CS Fixer config + CI gate
- Mutation testing (Infection)

---

## [1.0.0] — 2026-05-03

### Added

**Interactive Components**
- `TextInput` — free-text input with virtual block cursor, inline validation, placeholder, default value, HOME/END navigation
- `Password` — masked input (● bullets) with TAB toggle to plaintext and live 5-point strength meter
- `NumberInput` — numeric input with arrow-key stepping, min/max clamping, integer mode, and inline range hint
- `Confirm` — boolean toggle with highlighted Yes/No buttons; keyboard toggle (← →) and y/n shortcuts
- `Select` — single-selection list with fuzzy search (via `Fuzzy::filter`), scroll windowing (8 items), and type-to-filter
- `MultiSelect` — checkbox list with spacebar toggle; resolves to `string[]`
- `Autocomplete` — text input with live fuzzy-search dropdown; TAB to fill, ↑↓ to navigate suggestions
- `DatePicker` — interactive calendar grid with day/week/month navigation; resolves to `DateTimeImmutable`

**Display Components**
- `Table` — Unicode box-drawing table with ANSI-safe column width calculation, 4 border styles (box/bold/compact/minimal), alignment, and alternating row shading
- `Alert` — bordered notification boxes in 4 variants (success/error/warning/info) plus solid-background `block()` style
- `ProgressBar` — determinate (fill + %) and indeterminate (bounce) modes; accepts `$tick` callback for shell integration
- `SpinnerComponent` — non-blocking animated spinner with 6 frame styles; `start()` / `tick()` / `stop()` / `fail()` API

**Application Layer**
- `AbstractCommand` — base class with argument/option parser (long/short/cluster flags, `--opt=val`), output helpers, component factory methods, and auto-generated `printHelp()`
- `CLIApplication` — self-bootstrapping runner with Composer-based command discovery, `list`/`help`/`version` built-ins, Levenshtein "did you mean?" suggestions, and TTY-aware interactive picker on ambiguous match
- `ConsoleIO` — Symfony Console bridge; delegates to reactive components on real TTY, falls back to `QuestionHelper` on pipes/CI
- `BufferIO` — in-memory IO for testing; captures output (ANSI-stripped), simulates user input via `setUserInputs()`
- `NullIO` — completely silent IO; all writes are no-ops, all interactive methods return `$default`

**Internals**
- `State` — reactive key-value container with `__get`/`__set`, `batch()`, `increment()`/`decrement()`, `toggle()`, and `watch()` reactivity
- `Input` — key binding dispatcher with fallback, multi-key binding, and `return false` propagation stop
- `Hooks` — pub/sub event bus with `on()`/`once()`/`off()`/`dispatch()`/`dispatchUntil()`
- `Terminal` — raw mode driver with 10ms escape-sequence settling window, signal handling (SIGINT/SIGTERM), Windows VT100 support
- `Colors` — ANSI color/style helper with `NO_COLOR`/`FORCE_COLOR` env support, hex true-color, and `strip()` for test assertions
- `Fuzzy` — fuzzy search engine with prefix/substring/abbreviation/Levenshtein scoring
- `Shell` — `proc_open` wrapper with `stream_select()` streaming (no deadlocks), `$tick` callback for animation integration, and `ShellResult` value object
- `Renderer` — scroll windowing and in-place frame repainting with `beforeRender`/`afterRender` hooks
- `SpinnerFrames` — 6 built-in frame sets: `dots`, `line`, `bars`, `pulse`, `arc`, `bounce`

**Testing & CI**
- PHPUnit 11 test suite with Unit and Integration test suites
- PHPStan level 8 configuration
- GitHub Actions CI matrix: PHP 8.2/8.3/8.4 × latest/lowest dependencies
- GitHub Actions release workflow with auto-generated changelog
- PR template, bug report and feature request issue templates

**Examples**
- `examples/01-inputs.php` — all 8 interactive input components
- `examples/02-display.php` — Table, Alert, ProgressBar, Spinner
- `examples/03-application.php` — full CLIApplication with 4 commands
- `examples/04-shell.php` — Shell::run patterns with spinner and progress bar

---

[Unreleased]: https://github.com/alfacode-team/php-io-cli/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/alfacode-team/php-io-cli/releases/tag/v1.0.0
