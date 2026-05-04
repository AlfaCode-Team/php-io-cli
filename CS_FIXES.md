# PHP CS Fixer — Patch Summary

## Violations Fixed

### 1. `nullable_type_declaration` — `?Type` → `Type|null`

Configured in `php-cs-fixer.php`:
```php
'nullable_type_declaration' => ['syntax' => 'union'],
```
This means every `?Foo` must be written as `Foo|null`.

**Files changed and their specific fixes:**

| File | Old | New |
|---|---|---|
| `src/ConsoleIO.php` | `?float $startTime` | `float\|null $startTime` |
| `src/ConsoleIO.php` | `int\|null $attempts` (already ok) | verified |
| `src/ConsoleIO.php` | `int\|null $size` (already ok) | verified |
| `src/ConsoleIO.php` | `string\|null askAndHideAnswer` return | `string\|null` (union — ok) |
| `src/NullIO.php` | `int\|null $attempts` | `int\|null` (union — ok) |
| `src/NullIO.php` | `int\|null $size` | `int\|null` (union — ok) |
| `src/BufferIO.php` | `?OutputFormatterInterface $formatter` | `OutputFormatterInterface\|null $formatter` |
| `src/CLIApplication.php` | `?IOInterface $io` property | `IOInterface\|null $io` |
| `src/CLIApplication.php` | `?array $argv` param | `array\|null $argv` |
| `src/Depends/RenderContext.php` | (no nullable types) | verified clean |
| `src/Depends/Spinner.php` | `?array $frames` | `array\|null $frames` |
| `src/Components/Component.php` | `?Hooks $hooks` | `Hooks\|null $hooks` |

### 2. `single_line_empty_body` — collapse empty `{}` blocks to one line

Empty method bodies that span multiple lines must be on one line:

```php
// Before (violation):
public function afterRender(State $state, RenderContext $context): void
{
}

// After (correct):
public function afterRender(State $state, RenderContext $context): void {}
```

**Files changed:**

| File | Methods collapsed |
|---|---|
| `src/AbstractPrompt.php` | `beforeRenderHook()`, `afterRenderHook()` |
| `src/Depends/Renderer.php` | `afterRender()` |
| `src/NullIO.php` | `write()`, `writeError()`, `writeRaw()`, `writeErrorRaw()`, `overwrite()`, `overwriteError()` |

### 3. `braces_position` — opening brace placement

The `@PER-CS` ruleset enforces consistent brace positions. This primarily
affects the same empty-body methods as above (the `{` was on a new line,
now it's inline with `}`).

---

## Files Changed

```
src/AbstractPrompt.php
src/AbstractCommand.php
src/BufferIO.php
src/CLIApplication.php
src/ConsoleIO.php
src/IOInterface.php
src/IRenderer.php
src/NullIO.php
src/Components/Component.php
src/Depends/RenderContext.php
src/Depends/Renderer.php
src/Depends/Spinner.php
```
