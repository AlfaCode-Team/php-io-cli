<?php

declare(strict_types=1);

namespace AlfacodeTeam\PhpIoCli;

use AlfacodeTeam\PhpIoCli\Depends\Colors;
use AlfacodeTeam\PhpIoCli\Components\Autocomplete;
use AlfacodeTeam\PhpIoCli\Components\Confirm;
use AlfacodeTeam\PhpIoCli\Components\MultiSelect;
use AlfacodeTeam\PhpIoCli\Components\Password;
use AlfacodeTeam\PhpIoCli\Components\Select as CustomSelect;
use AlfacodeTeam\PhpIoCli\Components\TextInput;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;

/**
 * Enterprise bridge between Symfony Console and Alfacode reactive components.
 *
 * Strategy
 * ─────────
 * Every interactive method first checks isStdinTty(). When the process is
 * attached to a real terminal it delegates to the reactive component (raw-mode,
 * ANSI-animated). When running non-interactively (piped input, CI, test harness)
 * it falls back to the plain Symfony QuestionHelper so nothing breaks.
 */
class ConsoleIO extends BaseIO
{
    protected string $lastMessage    = '';
    protected string $lastMessageErr = '';
    private ?float   $startTime      = null;

    private array $verbosityMap = [
        self::QUIET        => OutputInterface::VERBOSITY_QUIET,
        self::NORMAL       => OutputInterface::VERBOSITY_NORMAL,
        self::VERBOSE      => OutputInterface::VERBOSITY_VERBOSE,
        self::VERY_VERBOSE => OutputInterface::VERBOSITY_VERY_VERBOSE,
        self::DEBUG        => OutputInterface::VERBOSITY_DEBUG,
    ];

    public function __construct(
        protected InputInterface  $input,
        protected OutputInterface $output,
        protected HelperSet       $helperSet
    ) {}

    public function enableDebugging(float $startTime): void
    {
        $this->startTime = $startTime;
    }

    /* =========================================================
       TTY detection
    ========================================================= */

    /**
     * Returns true only when STDIN is a real TTY.
     * Prevents Terminal::enableRaw() from conflicting with piped/memory streams.
     */
    private function isStdinTty(): bool
    {
        return $this->isInteractive()
            && function_exists('posix_isatty')
            && @posix_isatty(STDIN);
    }

    /* =========================================================
       INTERACTIVE — ask (plain text)
    ========================================================= */

    /**
     * Asks a free-text question.
     *
     * On a real TTY: uses the reactive TextInput component (inline validation,
     * virtual caret, placeholder support).
     * Otherwise: falls back to Symfony QuestionHelper.
     */
    public function ask(string $question, mixed $default = null): mixed
    {
        if ($this->isStdinTty()) {
            $input = new TextInput($question);

            if ($default !== null) {
                $input->default((string) $default);
            }

            return $input->run();
        }

        return $this->getQuestionHelper()->ask(
            $this->input,
            $this->output,
            new Question($question, $default)
        );
    }

    /* =========================================================
       INTERACTIVE — confirmation (yes/no)
    ========================================================= */

    /**
     * Asks a yes/no question.
     *
     * On a real TTY: uses the reactive Confirm component (toggle with ← →,
     * coloured button highlight).
     * Otherwise: falls back to Symfony ConfirmationQuestion.
     */
    public function askConfirmation(string $question, bool $default = true): bool
    {
        if ($this->isStdinTty()) {
            return (bool) (new Confirm($question, $default))->run();
        }

        return (bool) $this->getQuestionHelper()->ask(
            $this->input,
            $this->output,
            new ConfirmationQuestion($question, $default)
        );
    }

    /* =========================================================
       INTERACTIVE — ask with inline validation
    ========================================================= */

    /**
     * Asks a question and re-prompts until the validator passes.
     *
     * On a real TTY: uses TextInput with an inline validator that renders
     * the error message below the input without clearing the screen.
     * Otherwise: falls back to Symfony Question::setValidator().
     */
    public function askAndValidate(
        string   $question,
        callable $validator,
        ?int     $attempts = null,
        mixed    $default  = null
    ): mixed {
        if ($this->isStdinTty()) {
            $input = (new TextInput($question))
                ->validate(function (string $value) use ($validator): ?string {
                    try {
                        $validator($value);
                        return null;          // null = no error, validation passed
                    } catch (\Throwable $e) {
                        return $e->getMessage();
                    }
                });

            if ($default !== null) {
                $input->default((string) $default);
            }

            return $input->run();
        }

        $q = new Question($question, $default);
        $q->setValidator($validator);

        if ($attempts !== null) {
            $q->setMaxAttempts($attempts);
        }

        return $this->getQuestionHelper()->ask($this->input, $this->output, $q);
    }

    /* =========================================================
       INTERACTIVE — hidden answer (password)
    ========================================================= */

    /**
     * Asks a question whose answer is masked.
     *
     * On a real TTY: uses the reactive Password component (● masking,
     * TAB to toggle visibility, live strength meter).
     * Otherwise: falls back to Symfony Question::setHidden().
     */
    public function askAndHideAnswer(string $question): ?string
    {
        if ($this->isStdinTty()) {
            return (string) (new Password($question))->showStrength()->run();
        }

        $q = new Question($question);
        $q->setHidden(true);

        return $this->getQuestionHelper()->ask($this->input, $this->output, $q);
    }

    /* =========================================================
       INTERACTIVE — select (single / multi)
    ========================================================= */

    /**
     * Presents a list of choices.
     *
     * On a real TTY (single-select): uses the reactive Select component
     * (fuzzy search, scroll windowing, animated highlight).
     *
     * On a real TTY (multi-select): uses the reactive MultiSelect component
     * (spacebar toggle, checkbox display).
     *
     * Otherwise: falls back to Symfony ChoiceQuestion.
     *
     * @param string[] $choices
     * @phpstan-return ($multiselect is true ? list<string> : string|int|bool)
     */
    public function select(
        string     $question,
        array      $choices,
        mixed      $default,
        bool|int   $attempts     = false,
        string     $errorMessage = 'Value "%s" is invalid',
        bool       $multiselect  = false
    ): int|string|array|bool {
        if ($this->isStdinTty()) {
            if ($multiselect) {
                return (array) (new MultiSelect($question, $choices))->run();
            }

            return (string) (new CustomSelect($question, $choices))->run();
        }

        $q = new ChoiceQuestion($question, $choices, $default);
        $q->setMultiselect($multiselect);
        $q->setErrorMessage($errorMessage);

        if ($attempts !== false) {
            $q->setMaxAttempts((int) $attempts);
        }

        return $this->getQuestionHelper()->ask($this->input, $this->getErrorOutput(), $q);
    }

    /* =========================================================
       WRITING
    ========================================================= */

    public function write(mixed $messages, bool $newline = true, int $verbosity = self::NORMAL): void
    {
        $this->doWrite($messages, $newline, false, $verbosity);
    }

    public function writeError(mixed $messages, bool $newline = true, int $verbosity = self::NORMAL): void
    {
        $this->doWrite($messages, $newline, true, $verbosity);
    }

    public function writeRaw(mixed $messages, bool $newline = true, int $verbosity = self::NORMAL): void
    {
        $this->doWrite($messages, $newline, false, $verbosity, raw: true);
    }

    public function writeErrorRaw(mixed $messages, bool $newline = true, int $verbosity = self::NORMAL): void
    {
        $this->doWrite($messages, $newline, true, $verbosity, raw: true);
    }

    private function doWrite(
        mixed  $messages,
        bool   $newline,
        bool   $stderr,
        int    $verbosity,
        bool   $raw = false
    ): void {
        $sfVerbosity = $this->verbosityMap[$verbosity] ?? OutputInterface::VERBOSITY_NORMAL;

        if ($sfVerbosity > $this->output->getVerbosity()) {
            return;
        }

        $messages = (array) $messages;

        if ($this->startTime !== null) {
            $mem     = round(memory_get_usage() / 1024 / 1024, 1);
            $time    = round(microtime(true) - $this->startTime, 2);
            $prefix  = Colors::muted("[{$mem}MiB/{$time}s] ");
            $messages = array_map(fn($m) => $prefix . $m, $messages);
        }

        $target = $stderr ? $this->getErrorOutput() : $this->output;
        $target->write($messages, $newline, $raw ? OutputInterface::OUTPUT_RAW : $sfVerbosity);

        $log = implode($newline ? PHP_EOL : '', $messages);
        if ($stderr) {
            $this->lastMessageErr = $log;
        } else {
            $this->lastMessage = $log;
        }
    }

    /* =========================================================
       OVERWRITE
    ========================================================= */

    public function overwrite(mixed $messages, bool $newline = true, ?int $size = null, int $verbosity = self::NORMAL): void
    {
        $this->doOverwrite($messages, $newline, $size, false, $verbosity);
    }

    public function overwriteError(mixed $messages, bool $newline = true, ?int $size = null, int $verbosity = self::NORMAL): void
    {
        $this->doOverwrite($messages, $newline, $size, true, $verbosity);
    }

    private function doOverwrite(
        mixed  $messages,
        bool   $newline,
        ?int   $size,
        bool   $stderr,
        int    $verbosity
    ): void {
        $target = $stderr ? $this->getErrorOutput() : $this->output;
        $target->write("\r\033[K", false, OutputInterface::OUTPUT_RAW);
        $this->doWrite($messages, $newline, $stderr, $verbosity);
    }

    /* =========================================================
       UTILITIES
    ========================================================= */

    private function getQuestionHelper(): QuestionHelper
    {
        $helper = $this->helperSet->get('question');

        if (!$helper instanceof QuestionHelper) {
            throw new \RuntimeException('The Symfony QuestionHelper is missing from HelperSet.');
        }

        return $helper;
    }

    public function getErrorOutput(): OutputInterface
    {
        return ($this->output instanceof ConsoleOutputInterface)
            ? $this->output->getErrorOutput()
            : $this->output;
    }

    public function isInteractive(): bool
    {
        return $this->input->isInteractive();
    }
    public function isVerbose(): bool
    {
        return $this->output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE;
    }
    public function isVeryVerbose(): bool
    {
        return $this->output->getVerbosity() >= OutputInterface::VERBOSITY_VERY_VERBOSE;
    }
    public function isDebug(): bool
    {
        return $this->output->getVerbosity() >= OutputInterface::VERBOSITY_DEBUG;
    }
    public function isDecorated(): bool
    {
        return $this->output->isDecorated();
    }
}
