# TODO — php-io-cli Roadmap

This file tracks known gaps, planned improvements, and long-term goals for `php-io-cli`. It is written for contributors who want to pick up meaningful work and for maintainers planning future releases.

Status icons: 🔴 Not started · 🟡 In progress · 🟢 Done · ⚪ Deferred

---

## 1. Test Coverage

| Item | Priority | Status | Notes |
|---|---|---|---|
| Unit tests for `State` | P0 | 🟢 | Done in `tests/Unit/StateTest.php` |
| Unit tests for `Colors` | P0 | 🟢 | Done |
| Unit tests for `Fuzzy` | P0 | 🟢 | Done |
| Unit tests for `Key` | P0 | 🟢 | Done |
| Unit tests for `Hooks` | P0 | 🟢 | Done |
| Unit tests for `Input` | P0 | 🟢 | Done |
| Unit tests for `ShellResult` | P0 | 🟢 | Done |
| Unit tests for `RenderContext` | P0 | 🟢 | Done |
| Unit tests for `Table` | P0 | 🟢 | Done |
| Unit tests for `NullIO` | P0 | 🟢 | Done |
| Integration: `AbstractCommand` | P0 | 🟢 | Done |
| Integration: `CLIApplication` | P0 | 🟢 | Done |
| Integration: `BufferIO` | P0 | 🟢 | Done |
| Unit tests for `Alert` | P1 | 🔴 | Test output string contains expected borders + content |
| Unit tests for `SpinnerFrames` | P1 | 🔴 | Verify all named frame sets return non-empty arrays |
| Unit tests for `Spinner` | P1 | 🔴 | Tick advances frame; stop returns empty string |
| Unit tests for `Renderer` | P1 | 🔴 | Tricky — requires capturing stdout; mock Terminal |
| Integration: `BufferIO::setUserInputs` + commands | P1 | 🔴 | Test confirm/select prompts with pre-set inputs |
| Integration: `Shell::run` (echo command) | P2 | 🔴 | Use `echo` / `printf` — safe cross-platform |
| Integration: `Shell::capture` | P2 | 🔴 | Capture `php --version` or similar |
| Mutation testing via Infection | P2 | 🔴 | Add `infection/infection` dev dep; configure `infection.json5` |
| Coverage badge > 80% target | P2 | 🔴 | Depends on above items |

---

## 2. New Components

| Component | Priority | Status | Description |
|---|---|---|---|
| `SliderInput` | P1 | 🔴 | Horizontal bar slider for float/int ranges. Arrow keys ± step. |
| `RadioGroup` | P1 | 🔴 | Like `Select` but renders all options at once (no scroll). Good for short lists ≤ 5. |
| `SearchableTreeSelect` | P2 | 🔴 | Nested tree navigation. `parent > child > grandchild` grouping. |
| `TagInput` | P2 | 🔴 | Free-form comma-delimited tags with fuzzy autocomplete. |
| `CodeEditor` | P3 | 🔴 | Minimal inline code block with basic syntax highlighting. |
| `FilePathInput` | P2 | 🔴 | TextInput with Tab-completion from the filesystem. |
| `TimePicker` | P2 | 🔴 | Companion to `DatePicker`. HH:MM[:SS] with arrow-key stepping. |
| `ColorPicker` | P3 | 🔴 | TrueColor swatch grid, hex output. |

---

## 3. Core / Architecture Improvements

| Item | Priority | Status | Notes |
|---|---|---|---|
| **Abstract `AbstractPrompt`** — decouple `Terminal::readKey()` | P1 | 🔴 | Inject a `KeyReader` interface so components can be tested without a real terminal |
| **`Component` base** — remove direct `echo` from `render()` | P1 | 🔴 | Components should write to an `OutputInterface` buffer, not `STDOUT` directly. Enables headless rendering. |
| **Windows support** — full VT100 parity | P1 | 🔴 | `Terminal::readKey()` on Windows needs a separate implementation (no `stty`, use `ReadConsoleInput` via FFI or `sapi_windows_*`). Currently usable only in Windows Terminal / modern CMD. |
| **Async / non-blocking loop** | P2 | 🔴 | Optional event loop hook (e.g. Swoole / ReactPHP / Revolt) so components can run inside coroutines without blocking the main thread |
| **`IRenderer` diffing** | P2 | 🔴 | Implement dirty-region diffing in `Renderer` to only repaint changed lines, reducing flicker on slow terminals |
| **`State` serialization** | P2 | 🔴 | `State::toArray()` / `State::fromArray()` for save/restore across TTY sessions |
| **Component composition** | P2 | 🔴 | Allow embedding one component inside another (e.g. `TextInput` inside `Autocomplete` without copy/paste) |
| **Global `$this->ask()` shortcuts** return typed values | P2 | 🔴 | `AbstractCommand::askNumber()`, `askDate()`, `askPassword()` factory methods |
| **PSR-14 event dispatcher** | P3 | 🔴 | Replace `Hooks` with a PSR-14 compatible dispatcher, keep `Hooks` as a lightweight default |

---

## 4. Developer Experience

| Item | Priority | Status | Notes |
|---|---|---|---|
| PHP CS Fixer config (`.php-cs-fixer.php`) | P1 | 🔴 | PER-CS style; add `composer cs-fix` and `composer cs-check` scripts |
| `composer.json` scripts | P1 | 🔴 | `test`, `test:unit`, `test:integration`, `test:coverage`, `phpstan`, `cs-fix`, `cs-check` |
| Rector config for upgrade automation | P2 | 🔴 | `rector.php` targeting PHP 8.2+ idioms |
| Dev container / GitHub Codespaces | P2 | 🔴 | `.devcontainer/devcontainer.json` with PHP 8.3, Xdebug, Composer |
| Makefile for common tasks | P2 | 🔴 | `make test`, `make stan`, `make fix`, `make example` |
| Interactive demo script | P1 | 🔴 | `php examples/demo.php` — a menu-driven tour of all components |

---

## 5. Documentation

| Item | Priority | Status | Notes |
|---|---|---|---|
| Per-component `@example` docblocks | P1 | 🔴 | Every component class should have a self-contained usage example in its docblock |
| Architecture diagram (Mermaid) | P1 | 🔴 | Add `docs/architecture.md` with a Mermaid class/sequence diagram |
| Video demo / GIF | P2 | 🔴 | Record a terminal session showing the interactive components; embed in README |
| API reference (phpDocumentor) | P2 | 🔴 | Auto-generate and publish to GitHub Pages |
| "Building your first command" tutorial | P2 | 🔴 | Step-by-step guide: create a command, add inputs, test it |
| Migration guide from Symfony Console | P3 | 🔴 | Show how to replace `QuestionHelper` / `ChoiceQuestion` with reactive equivalents |

---

## 6. CI / Publishing

| Item | Priority | Status | Notes |
|---|---|---|---|
| PHPUnit CI matrix | P0 | 🟢 | PHP 8.2/8.3/8.4 × latest/lowest |
| PHPStan level 8 CI gate | P0 | 🟢 | Done |
| Security audit (`composer audit`) | P0 | 🟢 | Done |
| Release workflow | P0 | 🟢 | Auto-changelog from git log |
| PR template | P0 | 🟢 | Done |
| Issue templates | P0 | 🟢 | Bug + Feature templates done |
| Codecov integration | P1 | 🔴 | Add `CODECOV_TOKEN` secret; upload coverage report |
| Coverage badge in README | P1 | 🔴 | Depends on Codecov |
| PHPStan badge | P1 | 🔴 | Add static badge once baseline is locked |
| Packagist publish | P1 | 🔴 | Register on packagist.org; add `packagist` webhook to repo |
| `SECURITY.md` | P1 | 🔴 | Responsible disclosure policy |
| Dependabot for Composer | P2 | 🔴 | `.github/dependabot.yml` — weekly updates to dev deps |
| Branch protection rules | P2 | 🔴 | Require CI + review before merge to `main` |
| `CODEOWNERS` | P2 | 🔴 | Auto-assign reviewers by area |

---

## 7. Long-Term Goals

These are aspirational goals for when the library has a stable user base.

### v1.x — Stabilization

- Lock the public API (no breaking changes)
- > 80% test coverage with mutation score
- All P1 items above resolved
- Windows Terminal fully supported
- Listed on Packagist with > 100 installs/month

### v2.0 — Architecture Evolution

- `KeyReader` interface — fully testable components, no `STDIN` dependency
- `OutputBuffer` abstraction — components write to a buffer, not `STDOUT` directly
- PSR-14 event dispatcher
- Optional async/event-loop integration (Revolt / Swoole)
- Support for multi-column layouts (side-by-side components)

### Community Goals

- [ ] Published on Packagist (`alfacode-team/php-io-cli`)
- [ ] `CONTRIBUTING.md` includes "good first issue" labels guide
- [ ] At least 3 external contributors
- [ ] Listed in [Awesome PHP](https://github.com/ziadoz/awesome-php)
- [ ] Compared favorably to Laravel Prompts / Symfony Console in benchmarks
- [ ] Documented integration with Laravel Zero, Symfony, and standalone PHP CLI apps

---

## How to Pick Up a Task

1. Search for open [issues](https://github.com/alfacode-team/php-io-cli/issues) tagged `good first issue` or `help wanted`
2. Comment on the issue to claim it
3. Read `CONTRIBUTING.md` for the setup guide
4. Open a PR referencing the issue

If no issue exists for something in this file, open one first to discuss the approach before implementing.
