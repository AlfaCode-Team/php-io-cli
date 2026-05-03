<?php
declare(strict_types=1);

namespace AlfacodeTeam\PhpIoCli;

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
use AlfacodeTeam\PhpIoCli\Depends\Terminal;
use DateTimeImmutable;
use LogicException;
use Throwable;

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
    private array $rawTokens = [];
    private IOInterface $io;

    final public function __construct()
    {
        $this->configure();

        if ($this->name === '') {
            throw new LogicException(static::class . '::configure() must set $this->name.');
        }
    }

    abstract protected function configure(): void;
    abstract protected function handle(): int;

    /**
     * @internal Entry point called by CLIApplication
     */
    final public function execute(array $tokens, IOInterface $io): int
    {
        $this->io = $io;
        $this->rawTokens = $tokens;
        $this->parseTokens($tokens);

        // Validate required arguments
        foreach ($this->argumentDefs as $argName => $def) {
            if ($def['required'] && (!isset($this->arguments[$argName]) || $this->arguments[$argName] === null)) {
                $this->error("Missing required argument: <{$argName}>");
                return self::INVALID;
            }
        }

        try {
            return $this->handle();
        } catch (Throwable $e) {
            $this->io->error("Command Error: " . $e->getMessage());
            if ($io->isDebug()) {
                $this->io->write(Colors::muted($e->getTraceAsString()));
            }
            return self::FAILURE;
        }
    }

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
        $key = ltrim($long, '-');
        $this->optionDefs[$key] = [
            'short' => ltrim($short, '-'),
            'description' => $description,
            'acceptsValue' => $acceptsValue,
            'default' => $default,
        ];
        return $this;
    }

    /* =========================================================
       The "No-Headache" Parser
    ========================================================= */

    private function parseTokens(array $tokens): void
    {
        // Set Defaults
        foreach ($this->argumentDefs as $name => $def)
            $this->arguments[$name] = $def['default'];
        foreach ($this->optionDefs as $key => $def)
            $this->options[$key] = $def['default'];

        $positional = [];
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];

            // 1. Long Options (--option or --option=value)
            if (str_starts_with($token, '--')) {
                $bare = ltrim($token, '-');
                if (str_contains($bare, '=')) {
                    [$key, $value] = explode('=', $bare, 2);
                    $this->options[$key] = $value;
                } else {
                    $this->options[$bare] = true;
                    // Check if next token is a value
                    if (($this->optionDefs[$bare]['acceptsValue'] ?? false) && isset($tokens[$i + 1]) && !str_starts_with($tokens[$i + 1], '-')) {
                        $this->options[$bare] = $tokens[++$i];
                    }
                }
                continue;
            }

            // 2. Short Options Cluster (-vfa)
            if (str_starts_with($token, '-') && mb_strlen($token) > 1) {
                $chars = mb_str_split(mb_substr($token, 1));
                foreach ($chars as $char) {
                    foreach ($this->optionDefs as $key => $def) {
                        if ($def['short'] === $char) {
                            $this->options[$key] = true;
                            if ($def['acceptsValue'] && isset($tokens[$i + 1]) && !str_starts_with($tokens[$i + 1], '-')) {
                                $this->options[$key] = $tokens[++$i];
                            }
                            break;
                        }
                    }
                }
                continue;
            }

            // 3. Positional Arguments
            $positional[] = $token;
        }

        $argNames = array_keys($this->argumentDefs);
        foreach ($positional as $idx => $value) {
            if (isset($argNames[$idx]))
                $this->arguments[$argNames[$idx]] = $value;
        }
    }

    /* =========================================================
       Proxies & Helpers
    ========================================================= */

    protected function option(string $name, mixed $default = null): mixed
    {
        return $this->options[ltrim($name, '-')] ?? $default;
    }
    // protected function hasOption(string $name): bool { return (bool)$this->option($name); }

    protected function argument(string $name, mixed $default = null): mixed
    {
        return $this->arguments[$name] ?? $default;
    }
    protected function hasOption(string $name): bool
    {
        return (bool) ($this->options[ltrim($name, '-')] ?? false);
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
         Alert Components (Restored)
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
       Help Generation
    ========================================================= */

    final public function printHelp(): void
    {
        $this->section("Command: " . $this->name);
        $this->io->write($this->description . "\n");

        if (!empty($this->argumentDefs)) {
            $this->io->write(Colors::wrap("Arguments:", Colors::YELLOW));
            foreach ($this->argumentDefs as $name => $def) {
                $label = str_pad("<$name>", 20);
                $this->io->write("  " . Colors::info($label) . $def['description']);
            }
        }

        if (!empty($this->optionDefs)) {
            $this->io->write("\n" . Colors::wrap("Options:", Colors::YELLOW));
            foreach ($this->optionDefs as $key => $def) {
                $shortcut = $def['short'] ? "-{$def['short']}, " : "    ";
                $label = str_pad($shortcut . "--$key", 20);
                $this->io->write("  " . Colors::info($label) . $def['description']);
            }
        }
        $this->io->write("");
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
}