<?php

declare(strict_types=1);

namespace AlfacodeTeam\PhpIoCli;

use AlfacodeTeam\PhpIoCli\Depends\Colors;
use AlfacodeTeam\PhpIoCli\Components\Alert;
use AlfacodeTeam\PhpIoCli\Components\Select as CustomSelect;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Throwable;

/**
 * CLIApplication — self-bootstrapping application runner.
 *
 * ────────────────────────────────────────────────────────────────
 * Minimal bootstrap (no manual IO setup needed)
 * ────────────────────────────────────────────────────────────────
 *   #!/usr/bin/env php
 *   <?php
 *   require __DIR__ . '/vendor/autoload.php';
 *
 *   (new CLIApplication('MyApp', '1.0.0'))
 *       ->discoverCommands(__DIR__ . '/composer.json')
 *       ->add(new SomeExtraCommand())
 *       ->run();
 *
 * ────────────────────────────────────────────────────────────────
 * composer.json — command discovery schema
 * ────────────────────────────────────────────────────────────────
 *   {
 *     "extra": {
 *       "php-io-cli": {
 *         "commands": [
 *           "App\\Commands\\ServeCommand",
 *           "App\\Commands\\MakeModelCommand"
 *         ]
 *       }
 *     }
 *   }
 *
 * ────────────────────────────────────────────────────────────────
 * Built-in commands
 * ────────────────────────────────────────────────────────────────
 *   list      — all registered commands, grouped by namespace
 *   help      — detailed help for a specific command
 *   version   — application name + version
 */
final class CLIApplication
{
    /** @var array<string, AbstractCommand> */
    private array $commands = [];

    /**
     * IO layer — built automatically on first access via io().
     * Can be replaced with withIO() for tests or custom environments.
     */
    private ?IOInterface $io = null;

    private bool $catchExceptions = true;
    private bool $debug           = false;

    public function __construct(
        private string $name    = 'CLI Application',
        private string $version = '1.0.0'
    ) {}

    /* =========================================================
       IO management — internal, automatic
    ========================================================= */

    /**
     * Swap the auto-built IO for a custom one.
     * Useful in tests:  ->withIO(new BufferIO())
     * Or for custom formatting:  ->withIO(new ConsoleIO(...))
     *
     * Must be called before run() to take effect.
     */
    public function withIO(IOInterface $io): self
    {
        $this->io = $io;
        return $this;
    }

    /**
     * Lazily builds and caches a ConsoleIO wired to the real terminal.
     * Application code never calls this directly.
     */
    private function io(): IOInterface
    {
        if ($this->io === null) {
            $this->io = new ConsoleIO(
                new ArgvInput(),
                new ConsoleOutput(),
                new HelperSet([new QuestionHelper()])
            );
        }

        return $this->io;
    }

    /* =========================================================
       Configuration
    ========================================================= */

    public function catchExceptions(bool $catch): self
    {
        $this->catchExceptions = $catch;
        return $this;
    }

    /* =========================================================
       Command registration
    ========================================================= */

    public function add(AbstractCommand ...$commands): self
    {
        foreach ($commands as $command) {
            $this->commands[$command->getName()] = $command;
        }
        return $this;
    }

    public function has(string $name): bool
    {
        return isset($this->commands[$name]);
    }

    public function get(string $name): AbstractCommand
    {
        if (!$this->has($name)) {
            throw new \InvalidArgumentException("Command not found: {$name}");
        }
        return $this->commands[$name];
    }

    /** @return array<string, AbstractCommand> */
    public function all(bool $includeHidden = false): array
    {
        $cmds = $this->commands;

        if (!$includeHidden) {
            $cmds = array_filter($cmds, fn($c) => !$c->isHidden());
        }

        ksort($cmds);
        return $cmds;
    }

    /* =========================================================
       Composer command discovery
    ========================================================= */

    /**
     * Reads the `extra.php-io-cli.commands` array from a composer.json
     * and auto-registers every listed class as a command.
     *
     * Classes that are absent, non-concrete, or not an AbstractCommand
     * subclass are silently skipped (or logged in --debug mode).
     *
     * @param string $composerJsonPath Absolute path to composer.json.
     *                                 Omit to auto-detect from the project root.
     */
    public function discoverCommands(string $composerJsonPath = ''): self
    {
        if ($composerJsonPath === '') {
            $composerJsonPath = $this->locateComposerJson();
        }

        if (!is_file($composerJsonPath)) {
            if ($this->debug) {
                $this->io()->writeError(Colors::warning(
                    "discoverCommands: composer.json not found at: {$composerJsonPath}"
                ));
            }
            return $this;
        }

        $raw = file_get_contents($composerJsonPath);
        if ($raw === false) {
            return $this;
        }

        /** @var array<string, mixed>|null $json */
        $json = json_decode($raw, associative: true);

        if (!is_array($json)) {
            $this->io()->writeError(Colors::warning(
                "discoverCommands: invalid JSON in {$composerJsonPath}"
            ));
            return $this;
        }

        /** @var string[] $classes */
        $classes = $json['extra']['php-io-cli']['commands'] ?? [];

        foreach ($classes as $fqcn) {
            if (!is_string($fqcn) || !class_exists($fqcn)) {
                if ($this->debug) {
                    $this->io()->writeError(Colors::muted(
                        "  [discover] Skipped '{$fqcn}': class not found. Did you run composer dump-autoload?"
                    ));
                }
                continue;
            }

            $ref = new \ReflectionClass($fqcn);

            if ($ref->isAbstract() || !$ref->isSubclassOf(AbstractCommand::class)) {
                if ($this->debug) {
                    $this->io()->writeError(Colors::muted(
                        "  [discover] Skipped '{$fqcn}': not a concrete AbstractCommand subclass."
                    ));
                }
                continue;
            }

            try {
                /** @var AbstractCommand $cmd */
                $cmd = $ref->newInstance();
                $this->add($cmd);
            } catch (Throwable $e) {
                if ($this->debug) {
                    $this->io()->writeError(Colors::muted(
                        "  [discover] Skipped '{$fqcn}': {$e->getMessage()}"
                    ));
                }
            }
        }

        return $this;
    }

    /**
     * Walks up the directory tree from this library file to find the
     * outermost composer.json (the project root, not the library's own).
     */
    private function locateComposerJson(): string
    {
        $dir = __DIR__;

        for ($i = 0; $i < 8; $i++) {
            $candidate = $dir . '/composer.json';

            if (is_file($candidate)) {
                // Keep walking up — we want the project root, not the library itself
                $decoded = json_decode((string) file_get_contents($candidate), true);
                $libName = $decoded['name'] ?? '';

                // Stop at the first composer.json that is NOT this library
                if ($libName !== 'alfacode-team/php-io-cli') {
                    return $candidate;
                }
            }

            $parent = dirname($dir);
            if ($parent === $dir) {
                break;
            } // filesystem root
            $dir = $parent;
        }

        // Final fallback: working directory
        return getcwd() . '/composer.json';
    }

    /* =========================================================
       Entry point
    ========================================================= */

    /**
     * Parse argv and run the matching command.
     *
     * @param string[]|null $argv Omit to use $_SERVER['argv'] automatically.
     * @return int POSIX exit code (0 = success)
     */
    public function run(?array $argv = null): int
    {
        $argv ??= array_slice($_SERVER['argv'] ?? [], 1);
        $token = $argv[0] ?? '';
        $rest  = array_slice($argv, 1);

        // ── Global flags ──────────────────────────────────────
        if (in_array('--no-ansi', $argv, true)) {
            Colors::disable();
        }

        if (in_array('--debug', $argv, true) || in_array('-d', $argv, true)) {
            $this->debug = true;
            if ($this->io() instanceof ConsoleIO) {
                /** @var ConsoleIO $consoleIo */
                $consoleIo = $this->io();
                $consoleIo->enableDebugging(microtime(true));
            }
        }

        // ── Dispatch ──────────────────────────────────────────
        try {
            return $this->dispatch($token, $rest);
        } catch (Throwable $e) {
            if (!$this->catchExceptions) {
                throw $e;
            }

            Alert::error('Fatal error', [$e->getMessage()]);

            if ($this->debug) {
                $this->io()->writeError(Colors::muted($e->getTraceAsString()));
            }

            return AbstractCommand::FAILURE;
        }
    }

    /* =========================================================
       Dispatch
    ========================================================= */

    private function dispatch(string $token, array $rest): int
    {
        // --help / -h attached to a known command
        if (
            $token !== ''
            && $this->has($token)
            && (in_array('--help', $rest, true) || in_array('-h', $rest, true))
        ) {
            $cmd = $this->commands[$token];
            $cmd->setRethrowExceptions(!$this->catchExceptions);
            $cmd->execute([], $this->io());
            $cmd->printHelp();
            return AbstractCommand::SUCCESS;
        }

        // version  /  --version  /  -V
        if (in_array($token, ['version', '--version', '-V'], true)) {
            return $this->cmdVersion();
        }

        // help [<command>]
        if ($token === 'help') {
            return $this->cmdHelp($rest[0] ?? '');
        }

        // list  /  bare invocation
        if ($token === '' || $token === 'list') {
            return $this->cmdList();
        }

        // Exact match
        if ($this->has($token)) {
            $cmd = $this->commands[$token];
            $cmd->setRethrowExceptions(!$this->catchExceptions);
            return $cmd->execute($rest, $this->io());
        }

        // ── Not found — fuzzy suggestions ─────────────────────
        $suggestions = $this->suggest($token);

        Alert::error("Command not found: {$token}");

        if (!empty($suggestions)) {
            // On a real TTY with multiple matches: interactive picker
            if ($this->isTty() && count($suggestions) > 1) {
                $this->io()->write(Colors::muted('  Did you mean one of these?') . PHP_EOL);
                $pick = (new CustomSelect('Run instead?', $suggestions))->run();
                if (is_string($pick) && $this->has($pick)) {
                    $cmd = $this->commands[$pick];
                    $cmd->setRethrowExceptions(!$this->catchExceptions);
                    return $cmd->execute($rest, $this->io());
                }
            } else {
                $this->io()->writeError('');
                $this->io()->writeError(Colors::muted('  Did you mean?'));
                foreach ($suggestions as $s) {
                    $this->io()->writeError(Colors::muted("    {$s}"));
                }
            }
        }

        $this->io()->writeError('');
        $this->io()->writeError(Colors::muted("  Run 'list' to see all available commands."));

        return AbstractCommand::INVALID;
    }

    /* =========================================================
       Built-in commands
    ========================================================= */

    private function cmdVersion(): int
    {
        $this->io()->write(
            Colors::wrap($this->name, [Colors::BOLD, Colors::CYAN])
            . '  '
            . Colors::wrap("v{$this->version}", Colors::GREEN)
        );
        return AbstractCommand::SUCCESS;
    }

    private function cmdHelp(string $commandName): int
    {
        if ($commandName === '' || !$this->has($commandName)) {
            return $this->cmdList();
        }

        $cmd = $this->commands[$commandName];
        $cmd->setRethrowExceptions(!$this->catchExceptions);
        $cmd->execute([], $this->io());
        $cmd->printHelp();
        return AbstractCommand::SUCCESS;
    }

    private function cmdList(): int
    {
        $this->printBanner();

        $commands = $this->all();

        if (empty($commands)) {
            $this->io()->write(Colors::muted('  No commands registered.'));
            return AbstractCommand::SUCCESS;
        }

        // Group by namespace (segment before first ':')
        /** @var array<string, array<string, AbstractCommand>> $groups */
        $groups = [];

        foreach ($commands as $name => $cmd) {
            $ns = str_contains($name, ':') ? explode(':', $name, 2)[0] : '';
            $groups[$ns][$name] = $cmd;
        }

        ksort($groups);

        foreach ($groups as $ns => $cmds) {
            if ($ns !== '') {
                $this->io()->write('');
                $this->io()->write(Colors::wrap("  {$ns}", [Colors::BOLD, Colors::YELLOW]));
            }

            // Align descriptions by padding command names to same width
            $maxLen = max(array_map(fn($n) => mb_strlen($n), array_keys($cmds)));

            foreach ($cmds as $name => $cmd) {
                $this->io()->write(sprintf(
                    '  %s  %s',
                    Colors::wrap(str_pad($name, $maxLen), Colors::GREEN),
                    Colors::muted($cmd->getDescription())
                ));
            }
        }

        $this->io()->write('');
        $this->io()->write(Colors::muted("  Run 'help <command>' for detailed usage."));
        $this->io()->write('');

        return AbstractCommand::SUCCESS;
    }

    /* =========================================================
       Helpers
    ========================================================= */

    private function printBanner(): void
    {
        $separator = str_repeat('─', mb_strlen($this->name) + mb_strlen($this->version) + 5);

        $this->io()->write('');
        $this->io()->write(
            Colors::wrap("  {$this->name}", [Colors::BOLD, Colors::CYAN])
            . '  '
            . Colors::muted("v{$this->version}")
        );
        $this->io()->write(Colors::muted("  {$separator}"));
        $this->io()->write('');
        $this->io()->write(Colors::wrap('  Available Commands', Colors::BOLD));
        $this->io()->write('');
    }

    /**
     * Levenshtein "did you mean?" — returns command names within edit-distance 3.
     *
     * @return string[]
     */
    private function suggest(string $input): array
    {
        $matches = [];

        foreach (array_keys($this->commands) as $name) {
            $dist = levenshtein($input, $name);
            if ($dist <= 3) {
                $matches[$name] = $dist;
            }
        }

        asort($matches);
        return array_keys($matches);
    }

    private function isTty(): bool
    {
        return function_exists('posix_isatty') && @posix_isatty(STDIN);
    }
}
