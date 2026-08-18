<?php

declare(strict_types=1);

namespace AlfacodeTeam\PhpIoCli;

use AlfacodeTeam\PhpIoCli\Components\Alert;
use AlfacodeTeam\PhpIoCli\Components\Confirm;
use AlfacodeTeam\PhpIoCli\Components\ProgressBar;
use AlfacodeTeam\PhpIoCli\Components\Select;
use AlfacodeTeam\PhpIoCli\Components\SpinnerComponent;
use AlfacodeTeam\PhpIoCli\Components\Table;
use AlfacodeTeam\PhpIoCli\Components\TextInput;
use AlfacodeTeam\PhpIoCli\Depends\Colors;

abstract class AbstractCommand
{
    public const SUCCESS = 0;

    public const FAILURE = 1;

    public const INVALID = 2;

    protected string $name = '';

    protected string $description = '';

    protected string $help = '';

    protected bool $hidden = false;

    /** @var array<string, array{description: string, required: bool, default: mixed}> */
    private array $argumentDefs = [];

    /** @var array<string, array{short: string, description: string, acceptsValue: bool, default: mixed}> */
    private array $optionDefs = [];

    private array $arguments = [];

    private array $options = [];

    /**
     * Options the parser saw but no addOption() registered — used to fail loudly
     * on a typo (e.g. `--tsl=both`) instead of silently ignoring it, or to offer a
     * correction on a TTY. `spelling` is the user-facing form (`--long` / `-x`),
     * `key` the bare name, `value` any attached `=value` (null for a bare flag).
     *
     * @var list<array{spelling: string, key: string, value: ?string}>
     */
    private array $unknownOptions = [];

    /**
     * Global flags injected by the launcher / CLIApplication that every command
     * tolerates even though it does not register them itself. Without this an
     * otherwise-valid `--no-ansi` or `--debug` would be reported as "unknown".
     *
     * @var array<string, true>
     */
    private const GLOBAL_OPTIONS = [
        'no-ansi' => true, 'ansi' => true, 'debug' => true, 'help' => true,
        'version' => true, 'quiet' => true, 'no-interaction' => true, 'verbose' => true,
    ];

    /** Short forms of the global flags above (see GLOBAL_OPTIONS). */
    private const GLOBAL_SHORT = ['h' => true, 'V' => true, 'q' => true, 'n' => true, 'v' => true];

    // FIX 1: $rawTokens was assigned in execute() but never read anywhere — removed.

    private IOInterface $io;

    private bool $rethrowExceptions = false;

    /**
     * Options the user passed that this command never registered.
     *
     * @var list<array{token: string, name: string, value: string|bool}>
     */
    // private array $unknownOptions = [];

    /**
     * Flags the launcher / CLIApplication inject into every command. A command
     * that never registers them must still tolerate them.
     */
    private const GLOBAL_FLAGS = [
        'help', 'quiet', 'verbose', 'version', 'ansi', 'no-ansi',
        'no-interaction', 'debug', 'profile', 'working-dir',
    ];

    public function __construct()
    {
        $this->configure();

        if ($this->name === '') {
            throw new \LogicException(static::class . '::configure() must set $this->name.');
        }
    }

    /**
     * @internal Entry point called by CLIApplication
     */
    final public function execute(array $tokens, IOInterface $io): int
    {
        $this->io = $io;
        $this->parseTokens($tokens);

        // A misspelled flag used to be accepted silently: `--tsl=both` simply
        // set an option nobody read, the command ran with its default, and the
        // user got a wrong result with a zero exit code. Fail loudly instead.
        if (($unknownExit = $this->rejectUnknownOptions()) !== null) {
            return $unknownExit;
        }

        // FIX 2: The old condition was:
        //   !isset($this->arguments[$argName]) || $this->arguments[$argName] === null
        // isset() already returns false for null-valued keys, so after !isset() is false
        // (i.e. the key IS set and IS NOT null), the === null comparison is always false.
        // PHPStan level 8 correctly flags it. Fix: use only !isset(), which covers both
        // "key absent" and "key set to null".
        foreach ($this->argumentDefs as $argName => $def) {
            if ($def['required'] && !isset($this->arguments[$argName])) {
                $this->error("Missing required argument: <{$argName}>");

                return self::INVALID;
            }
        }

        try {
            return $this->handle();
        } catch (\Throwable $e) {
            // If catch-exceptions mode is enabled (via marker), rethrow
            if ($this->rethrowExceptions) {
                throw $e;
            }

            $this->io->error('Command Error: ' . $e->getMessage());
            if ($io->isDebug()) {
                $this->io->write(Colors::muted($e->getTraceAsString()));
            }

            return self::FAILURE;
        }
    }


    /**
     * Record a long option the command never registered.
     *
     * Global launcher flags are exempt: CLIApplication injects --no-ansi,
     * --debug and friends into every command, and a command that does not
     * declare them must not choke on them.
     */
    private function noteIfUnknown(string $token, string $name, string|bool $value): void
    {
        if (isset($this->optionDefs[$name]) || \in_array($name, self::GLOBAL_FLAGS, true)) {
            return;
        }

        $this->unknownOptions[] = ['token' => '--' . $name, 'name' => $name, 'value' => $value];
    }

    /**
     * Fail — or, at a terminal, offer to autocorrect — on unknown options.
     *
     * @return int|null INVALID to abort, or null to continue into handle()
     */
    private function rejectUnknownOptions(): ?int
    {
        if ($this->unknownOptions === []) {
            return null;
        }

        $invalid = false;

        foreach ($this->unknownOptions as $unknown) {
            $suggestion = $this->suggestOption($unknown['name']);

            // At a real terminal a confident suggestion is applied rather than
            // thrown back at the user — but it is always announced, never silent.
            if ($suggestion !== null && $this->io->isInteractive()) {
                $this->options[$suggestion] = $unknown['value'];
                unset($this->options[$unknown['name']]);
                $this->io->write(Colors::warning(
                    "Unknown option: {$unknown['token']} — interpreting {$unknown['token']} as --{$suggestion}"
                ));
                // NOTE: token is the bare flag ("--tsl"), never the raw argv
                // entry ("--tsl=both") — the value is carried onto the corrected
                // option above, so echoing it back would be misleading.

                continue;
            }

            $this->error("Unknown option: {$unknown['token']}");
            if ($suggestion !== null) {
                $this->error("  did you mean --{$suggestion}?");
            }
            $invalid = true;
        }

        return $invalid ? self::INVALID : null;
    }

    /**
     * The closest registered option name, or null when nothing is close enough.
     *
     * Plain Levenshtein scores a TRANSPOSITION as two edits, so the very typo
     * this exists to catch (--tsl for --tls) would tie with, or lose to, an
     * unrelated option that happens to be two edits away. Treat a pure
     * rearrangement of the same characters as a single edit so it ranks first.
     */
    private function suggestOption(string $name): ?string
    {
        $best         = null;
        $bestDistance = PHP_INT_MAX;

        foreach (\array_keys($this->optionDefs) as $candidate) {
            $distance = \levenshtein($name, (string) $candidate);

            if ($this->isTransposition($name, (string) $candidate)) {
                $distance = 1;
            }

            if ($distance < $bestDistance) {
                $bestDistance = $distance;
                $best         = (string) $candidate;
            }
        }

        if ($best === null) {
            return null;
        }

        // Be conservative: only suggest when the edit is small relative to the
        // word. "--totally-unrelated-flag" must produce no suggestion at all.
        $threshold = (int) \max(1, \min(3, \intdiv(\mb_strlen($name), 3)));

        return $bestDistance <= $threshold ? $best : null;
    }

    /** Same characters, different order — one mistyped keystroke, not two. */
    private function isTransposition(string $a, string $b): bool
    {
        if ($a === $b || \mb_strlen($a) !== \mb_strlen($b)) {
            return false;
        }

        $left  = \mb_str_split($a);
        $right = \mb_str_split($b);
        \sort($left);
        \sort($right);

        return $left === $right;
    }

    /**
     * Set whether exceptions should be rethrown instead of caught.
     * Called by CLIApplication when catchExceptions(false).
     *
     * @internal For use by CLIApplication only.
     */
    final public function setRethrowExceptions(bool $rethrow): void
    {
        $this->rethrowExceptions = $rethrow;
    }

    /* =========================================================
       Help Generation
    ========================================================= */

    final public function printHelp(): void
    {
        $this->section('Command: ' . $this->name);
        $this->io->write($this->description . "\n");

        if (!empty($this->argumentDefs)) {
            $this->io->write(Colors::wrap('Arguments:', Colors::YELLOW));
            foreach ($this->argumentDefs as $name => $def) {
                $label = mb_str_pad("<{$name}>", 20);
                $this->io->write('  ' . Colors::info($label) . $def['description']);
            }
        }

        if (!empty($this->optionDefs)) {
            $this->io->write("\n" . Colors::wrap('Options:', Colors::YELLOW));
            foreach ($this->optionDefs as $key => $def) {
                $shortcut = $def['short'] ? "-{$def['short']}, " : '    ';
                $label = mb_str_pad($shortcut . "--{$key}", 20);
                $this->io->write('  ' . Colors::info($label) . $def['description']);
            }
        }
        $this->io->write('');
    }

    // Standard Getters
    final public function getName(): string
    {
        return $this->name;
    }

    final public function getDescription(): string
    {
        return $this->description;
    }

    final public function isHidden(): bool
    {
        return $this->hidden;
    }

    abstract protected function configure(): void;

    abstract protected function handle(): int;

    /* =========================================================
       Registration
    ========================================================= */

    protected function addArgument(string $name, string $description = '', bool $required = false, mixed $default = null): static
    {
        $this->argumentDefs[$name] = compact('description', 'required', 'default');

        return $this;
    }

    protected function addOption(string $long, string $short = '', string $description = '', bool $acceptsValue = false, mixed $default = null): static
    {
        $key = mb_ltrim($long, '-');
        $this->optionDefs[$key] = [
            'short' => mb_ltrim($short, '-'),
            'description' => $description,
            'acceptsValue' => $acceptsValue,
            'default' => $default,
        ];

        return $this;
    }

    /* =========================================================
       Proxies & Helpers
    ========================================================= */

    protected function option(string $name, mixed $default = null): mixed
    {
        return $this->options[mb_ltrim($name, '-')] ?? $default;
    }

    protected function argument(string $name, mixed $default = null): mixed
    {
        return $this->arguments[$name] ?? $default;
    }

    protected function hasOption(string $name): bool
    {
        return (bool) ($this->options[mb_ltrim($name, '-')] ?? false);
    }

    protected function info(string $message): void
    {
        $this->io->write(Colors::info($message));
    }

    protected function success(string $message): void
    {
        $this->io->write(Colors::success($message));
    }

    protected function warning(string $message): void
    {
        $this->io->writeError(Colors::warning($message));
    }

    protected function error(string $message): void
    {
        $this->io->writeError(Colors::error($message));
    }

    protected function muted(string $message): void
    {
        $this->io->write(Colors::muted($message));
    }

    protected function newLine(int $count = 1): void
    {
        for ($i = 0; $i < $count; $i++) {
            $this->io->write('');
        }
    }

    protected function section(string $title): void
    {
        $this->newLine();
        $this->io->write(Colors::wrap($title, [Colors::BOLD, Colors::CYAN]));
        $this->io->write(Colors::muted(str_repeat('─', mb_strlen(Colors::strip($title)))));
    }

    /* =========================================================
       Alert Components
    ========================================================= */

    protected function alertSuccess(string $title, string|array $body = []): void
    {
        Alert::success($title, $body);
    }

    protected function alertError(string $title, string|array $body = []): void
    {
        Alert::error($title, $body);
    }

    protected function alertWarning(string $title, string|array $body = []): void
    {
        Alert::warning($title, $body);
    }

    protected function alertInfo(string $title, string|array $body = []): void
    {
        Alert::info($title, $body);
    }

    /* =========================================================
       Component Factory Methods
    ========================================================= */

    protected function ask(string $q, string $default = ''): string
    {
        return (string) (new TextInput($q))->default($default)->run();
    }

    protected function select(string $q, array $c): string
    {
        return (string) (new Select($q, $c))->run();
    }

    protected function confirm(string $question, bool $default = true): bool
    {
        return (bool) (new Confirm($question, $default))->run();
    }

    protected function table(): Table
    {
        return Table::make();
    }

    protected function progressBar(string $label, int $total = 0): ProgressBar
    {
        return new ProgressBar($label, $total);
    }

    protected function spinner(string $label, string $style = 'dots'): SpinnerComponent
    {
        return new SpinnerComponent($label, $style);
    }

    /* =========================================================
       The "No-Headache" Parser
    ========================================================= */

    private function parseTokens(array $tokens): void
    {
        // Set Defaults
        foreach ($this->argumentDefs as $name => $def) {
            $this->arguments[$name] = $def['default'];
        }
        foreach ($this->optionDefs as $key => $def) {
            $this->options[$key] = $def['default'];
        }

        $positional = [];
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];

            // 1. Long Options (--option or --option=value)
            if (str_starts_with($token, '--')) {
                $bare = mb_ltrim($token, '-');
                [$key, $value] = str_contains($bare, '=') ? explode('=', $bare, 2) : [$bare, null];

                $known = array_key_exists($key, $this->optionDefs) || isset(self::GLOBAL_OPTIONS[$key]);
                if (!$known) {
                    // Flag the offending option (keeping any =value) so execute()
                    // can fail with a hint, or — on a TTY — offer to auto-correct.
                    $this->unknownOptions[] = ['token' => '--' . $key, 'name' => $key, 'value' => $value];
                    continue;
                }

                if ($value !== null) {
                    $this->options[$key] = $value;
                    $this->noteIfUnknown($token, $key, $value);
                } else {
                    $this->options[$key] = true;
                    if (($this->optionDefs[$key]['acceptsValue'] ?? false) && isset($tokens[$i + 1]) && !str_starts_with($tokens[$i + 1], '-')) {
                        $this->options[$key] = $tokens[++$i];
                    }
                    $this->noteIfUnknown($token, $bare, $this->options[$bare]);
                }

                continue;
            }

            // 2. Short Options Cluster (-vfa)
            if (str_starts_with($token, '-') && mb_strlen($token) > 1) {
                $chars = mb_str_split(mb_substr($token, 1));
                foreach ($chars as $char) {
                    $matched = false;
                    foreach ($this->optionDefs as $key => $def) {
                        if ($def['short'] === $char) {
                            $matched = true;
                            $this->options[$key] = true;
                            if ($def['acceptsValue'] && isset($tokens[$i + 1]) && !str_starts_with($tokens[$i + 1], '-')) {
                                $this->options[$key] = $tokens[++$i];
                            }
                            $matched = true;

                            break;
                        }
                    }
                    if (!$matched) {
                        // No long-name suggestion is possible for a single char,
                        // so this always fails rather than autocorrecting.
                        $this->unknownOptions[] = ['token' => '-' . $char, 'name' => $char, 'value' => true];
                    }
                }

                continue;
            }

            // 3. Positional Arguments
            $positional[] = $token;
        }

        $argNames = array_keys($this->argumentDefs);
        foreach ($positional as $idx => $value) {
            if (isset($argNames[$idx])) {
                $this->arguments[$argNames[$idx]] = $value;
            }
        }
    }

    /**
     * Decide what to do with the options no addOption() registered. On an
     * INTERACTIVE terminal, offer to apply an obvious correction (`--tsl` →
     * `--tls`, keeping any =value) and continue — returning true. In a
     * non-interactive context (script / CI), or when the user declines / there is
     * no confident suggestion, print the error(s) and return false so execute()
     * exits INVALID. This keeps a typo'd DEPLOY flag from ever passing silently
     * while sparing a human at the keyboard a re-type.
     */
    private function resolveUnknownOptions(): bool
    {
        $interactive = $this->io->isInteractive();
        $ok = true;

        foreach ($this->unknownOptions as $unknown) {
            $suggestKey = $this->suggestOptionKey($unknown['key']);

            // Interactive + a confident correction → apply it and carry on, with a
            // visible notice so the human sees what was interpreted. Never silent,
            // never blocking.
            if ($interactive && $suggestKey !== null) {
                $this->options[$suggestKey] = $unknown['value'] ?? true;
                $this->warning("Note: interpreting {$unknown['spelling']} as --{$suggestKey}.");
                continue;
            }

            // Non-interactive (script / CI) or no confident suggestion → hard fail,
            // so a typo'd deploy flag can never slip through silently.
            $hint = $suggestKey !== null ? " — did you mean --{$suggestKey}?" : '';
            $this->error("Unknown option: {$unknown['spelling']}{$hint}");
            $ok = false;
        }

        if (!$ok) {
            $this->muted('Run `' . $this->name . ' --help` to see the available options.');
        }

        return $ok;
    }

    /**
     * The closest registered option KEY to an unknown one (`tsl` → `tls`), or null
     * when nothing is near enough. Damerau-Levenshtein distance (so an adjacent
     * transposition scores 1) capped relative to the token length, so only genuine
     * typos are suggested.
     */
    private function suggestOptionKey(string $key): ?string
    {
        $needle = mb_ltrim($key, '-');
        if ($needle === '') {
            return null;
        }

        $best = null;
        $bestDistance = PHP_INT_MAX;
        foreach (array_keys($this->optionDefs) as $candidate) {
            $distance = $this->damerauLevenshtein($needle, (string) $candidate);
            if ($distance < $bestDistance) {
                $bestDistance = $distance;
                $best = (string) $candidate;
            }
        }

        // Only suggest a close match (≤ a third of the length, min 2 edits).
        $threshold = max(2, (int) floor(mb_strlen($needle) / 3));

        return ($best !== null && $bestDistance <= $threshold) ? $best : null;
    }

    /**
     * Optimal string alignment distance: like Levenshtein but a swap of two
     * ADJACENT characters costs 1 edit. Enough to rank real typos (transpositions,
     * a dropped/added/changed letter) ahead of coincidental same-length matches.
     */
    private function damerauLevenshtein(string $a, string $b): int
    {
        $la = mb_strlen($a);
        $lb = mb_strlen($b);
        if ($la === 0) {
            return $lb;
        }
        if ($lb === 0) {
            return $la;
        }

        $ca = mb_str_split($a);
        $cb = mb_str_split($b);
        $d  = [];
        for ($i = 0; $i <= $la; $i++) {
            $d[$i][0] = $i;
        }
        for ($j = 0; $j <= $lb; $j++) {
            $d[0][$j] = $j;
        }

        for ($i = 1; $i <= $la; $i++) {
            for ($j = 1; $j <= $lb; $j++) {
                $cost = $ca[$i - 1] === $cb[$j - 1] ? 0 : 1;
                $d[$i][$j] = min(
                    $d[$i - 1][$j] + 1,        // deletion
                    $d[$i][$j - 1] + 1,        // insertion
                    $d[$i - 1][$j - 1] + $cost, // substitution
                );
                if ($i > 1 && $j > 1 && $ca[$i - 1] === $cb[$j - 2] && $ca[$i - 2] === $cb[$j - 1]) {
                    $d[$i][$j] = min($d[$i][$j], $d[$i - 2][$j - 2] + 1); // transposition
                }
            }
        }

        return $d[$la][$lb];
    }
}
