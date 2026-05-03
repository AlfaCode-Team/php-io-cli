# Contributing to php-io-cli

Thank you for your interest in contributing! This document covers everything you need to get from zero to a merged pull request.

---

## Table of Contents

- [Code of Conduct](#code-of-conduct)
- [Development Setup](#development-setup)
- [Running Tests](#running-tests)
- [Static Analysis](#static-analysis)
- [Project Structure](#project-structure)
- [Writing Tests](#writing-tests)
- [Adding a New Component](#adding-a-new-component)
- [Commit Convention](#commit-convention)
- [Pull Request Process](#pull-request-process)

---

## Code of Conduct

Be kind, constructive, and respectful. We enforce the [Contributor Covenant](https://www.contributor-covenant.org/version/2/1/code_of_conduct/).

---

## Development Setup

```bash
git clone https://github.com/alfacode-team/php-io-cli.git
cd php-io-cli
composer install
```

**Requirements:**
- PHP 8.2+
- Composer 2.x
- Extensions: `mbstring`, `pcntl` (Unix), `posix` (Unix)

---

## Running Tests

```bash
# All tests
composer test

# Unit tests only
composer test:unit

# Integration tests only
composer test:integration

# With coverage (requires Xdebug or PCOV)
composer test:coverage
```

Tests use `BufferIO` for capturing output and `NullIO` for silent execution — no TTY or raw-mode involvement in tests.

---

## Static Analysis

```bash
# PHPStan level 8
composer phpstan

# If you have php-cs-fixer installed:
composer cs-check     # dry-run
composer cs-fix       # apply fixes
```

PHPStan is the hard gate — all PRs must pass at level 8. The only allowed ignoreError is for `State::$*` magic property access (by design — the reactive store uses `__get`/`__set`).

---

## Project Structure

```
src/
├── AbstractCommand.php       # Base class for all commands
├── AbstractPrompt.php        # Base class for all interactive components
├── CLIApplication.php        # Application runner + dispatcher
├── Components/
│   ├── Component.php         # Base for reactive components
│   ├── Alert.php             # Static banner rendering
│   ├── Autocomplete.php      # Text + fuzzy dropdown
│   ├── Confirm.php           # Boolean toggle
│   ├── DatePicker.php        # Calendar grid
│   ├── MultiSelect.php       # Checkbox list
│   ├── NumberInput.php       # Numeric input with stepping
│   ├── Password.php          # Masked input + strength meter
│   ├── ProgressBar.php       # Determinate + indeterminate bar
│   ├── Select.php            # Single-selection with fuzzy search
│   ├── SpinnerComponent.php  # Non-blocking spinner wrapper
│   ├── Table.php             # Unicode box-drawing table
│   └── TextInput.php         # Free-text input
├── Depends/
│   ├── Colors.php            # ANSI color / style helper
│   ├── Fuzzy.php             # Fuzzy search + scoring
│   ├── Input.php             # Key binding dispatcher
│   ├── Key.php               # Key constants + normalizer
│   ├── RenderContext.php     # Render cycle metadata
│   ├── Renderer.php          # Scroll windowing + cursor management
│   ├── Shell.php             # proc_open wrapper with streaming
│   ├── ShellResult.php       # Immutable shell result value object
│   ├── Spinner.php           # Frame-based spinner engine
│   ├── SpinnerFrames.php     # Frame set definitions
│   ├── State.php             # Reactive key-value store
│   └── Terminal.php          # Raw mode + escape sequences
├── BaseIO.php                # PSR-3 bridge + shared IO base
├── BufferIO.php              # In-memory IO for testing
├── ConsoleIO.php             # Symfony Console + reactive component bridge
├── Hooks.php                 # Pub/sub event bus
├── IOInterface.php           # Unified I/O contract
├── ILifecycle.php            # Component lifecycle contract
├── IPromptComponent.php      # run() contract
├── IRenderer.php             # Renderer contract
├── NullIO.php                # Silent no-op IO
└── Silencer.php              # PHP error suppression utility

tests/
├── Unit/                     # Pure unit tests (no I/O, no TTY)
└── Integration/              # Command + application integration tests

examples/
├── 01-inputs.php             # All interactive input components
├── 02-display.php            # Table, Alert, ProgressBar, Spinner
├── 03-application.php        # Full CLIApplication with commands
└── 04-shell.php              # Shell::run integration patterns
```

---

## Writing Tests

### Unit tests (`tests/Unit/`)

Test a single class in isolation. No I/O, no TTY, no disk.

```php
final class MyClassTest extends TestCase
{
    public function test_something_specific(): void
    {
        $obj = new MyClass();
        $this->assertSame('expected', $obj->method());
    }
}
```

### Integration tests (`tests/Integration/`)

Test how components interact — commands through `BufferIO`, application dispatch, etc.

```php
final class MyCommandTest extends TestCase
{
    public function test_command_outputs_correctly(): void
    {
        $io  = new BufferIO();
        $cmd = new MyCommand();

        $exit = $cmd->execute(['arg1', '--flag'], $io);

        $this->assertSame(AbstractCommand::SUCCESS, $exit);
        $this->assertStringContainsString('expected text', $io->getOutput());
    }
}
```

**Key testing utilities:**

| Class | Use for |
|---|---|
| `BufferIO` | Capture command output, simulate user input |
| `NullIO` | Silent execution, test return codes only |
| `Colors::disable()` | Strip ANSI from output for assertion clarity |

---

## Adding a New Component

1. Create `src/Components/MyComponent.php` extending `Component`
2. Implement `setup()`, `render()`, and `resolve()`
3. Follow the `$lastLines` / `Terminal::moveCursorUp()` pattern for flicker-free redraws
4. Add a factory method in `AbstractCommand` if it's a common prompt type
5. Write unit tests for state mutations and a smoke-test for rendering
6. Add a usage example in `examples/` or update an existing one
7. Document the component in `README.md`

**Minimal component template:**

```php
final class MyComponent extends Component
{
    private int $lastLines = 0;

    public function __construct(private string $question)
    {
        parent::__construct();
    }

    protected function setup(): void
    {
        $this->state->batch(['value' => '', 'done' => false]);

        $this->input->bind('ENTER', function ($state): void {
            $state->done = true;
            $this->stop();
        });
    }

    public function render(): void
    {
        if ($this->lastLines > 0) {
            Terminal::moveCursorUp($this->lastLines);
        }

        $lines   = [];
        $lines[] = Colors::wrap('? ', Colors::CYAN) . $this->question;
        // ... more lines

        foreach ($lines as $line) {
            Terminal::clearLine();
            echo $line . PHP_EOL;
        }

        $this->lastLines = count($lines);
    }

    public function resolve(): mixed
    {
        return $this->state->value;
    }
}
```

---

## Commit Convention

We follow [Conventional Commits](https://www.conventionalcommits.org/):

```
<type>(<scope>): <short description>

[optional body]
[optional footer]
```

| Type | When to use |
|---|---|
| `feat` | New component or feature |
| `fix` | Bug fix |
| `refactor` | Internal restructuring, no user-visible change |
| `test` | Adding or improving tests |
| `docs` | Documentation only |
| `chore` | CI, build config, tooling |
| `perf` | Performance improvement |

Examples:
```
feat(components): add SliderInput component
fix(password): correct strength-meter index out-of-bounds edge case
test(state): add watcher notification tests
docs(readme): document Shell::capture() return type
```

---

## Pull Request Process

1. Fork the repository and create a branch: `feat/my-feature` or `fix/issue-123`
2. Write your code + tests
3. Run `composer test` and `composer phpstan` — both must pass
4. Fill out the PR template completely
5. Request a review from a maintainer

PRs that are missing tests or break PHPStan will not be merged until fixed. Small, focused PRs are preferred over large all-in-one changes.
