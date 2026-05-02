<?php
declare(strict_types=1);

namespace AlfacodeTeam\PhpIoCli;

use AlfacodeTeam\PhpIoCli\Depends\Colors;
use AlfacodeTeam\PhpIoCli\Components\Select as CustomSelect;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;

/**
 * Enterprise bridge between Symfony Console and Alfacode components.
 */
class ConsoleIO extends BaseIO
{
    protected string $lastMessage    = '';
    protected string $lastMessageErr = '';
    private ?float $startTime        = null;

    private array $verbosityMap = [
        self::QUIET        => OutputInterface::VERBOSITY_QUIET,
        self::NORMAL       => OutputInterface::VERBOSITY_NORMAL,
        self::VERBOSE      => OutputInterface::VERBOSITY_VERBOSE,
        self::VERY_VERBOSE => OutputInterface::VERBOSITY_VERY_VERBOSE,
        self::DEBUG        => OutputInterface::VERBOSITY_DEBUG,
    ];

    public function __construct(
        protected InputInterface $input,
        protected OutputInterface $output,
        protected HelperSet $helperSet
    ) {}

    public function enableDebugging(float $startTime): void
    {
        $this->startTime = $startTime;
    }

    /* =========================================================
       INTERACTIVE SELECT
    ========================================================= */

    /**
     * Uses the custom reactive Select component when possible.
     *
     * Raw-mode conflict fix: we only hand off to CustomSelect when the
     * current process is actually attached to a TTY. When Symfony has
     * already opened STDIN as a stream (piped input, test harness) we
     * fall back to ChoiceQuestion to avoid corrupting the input stream.
     */
    public function select(
        string $question,
        array $choices,
        mixed $default,
        bool|int $attempts = false,
        string $errorMessage = 'Value "%s" is invalid',
        bool $multiselect = false
    ): int|string|array|bool {
        if ($this->isInteractive() && !$multiselect && $this->isStdinTty()) {
            return (new CustomSelect($question, $choices))->run();
        }

        $q = new ChoiceQuestion($question, $choices, $default);
        $q->setMultiselect($multiselect);
        $q->setErrorMessage($errorMessage);

        if ($attempts !== false) {
            $q->setMaxAttempts((int) $attempts);
        }

        return $this->getQuestionHelper()->ask($this->input, $this->getErrorOutput(), $q);
    }

    /**
     * Returns true when STDIN is a real TTY, not a pipe or memory stream.
     * Prevents Terminal::enableRaw() from conflicting with Symfony's input stream.
     */
    private function isStdinTty(): bool
    {
        return function_exists('posix_isatty') && @posix_isatty(STDIN);
    }

    /* =========================================================
       WRITING
    ========================================================= */

    public function write($messages, bool $newline = true, int $verbosity = self::NORMAL): void
    {
        $this->doWrite($messages, $newline, false, $verbosity);
    }

    public function writeError($messages, bool $newline = true, int $verbosity = self::NORMAL): void
    {
        $this->doWrite($messages, $newline, true, $verbosity);
    }

    private function doWrite(
        mixed $messages,
        bool $newline,
        bool $stderr,
        int $verbosity,
        bool $raw = false
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
            $messages = array_map(fn ($m) => $prefix . $m, $messages);
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

    public function overwrite($messages, bool $newline = true, ?int $size = null, int $verbosity = self::NORMAL): void
    {
        $this->doOverwrite($messages, $newline, $size, false, $verbosity);
    }

    public function overwriteError($messages, bool $newline = true, ?int $size = null, int $verbosity = self::NORMAL): void
    {
        $this->doOverwrite($messages, $newline, $size, true, $verbosity);
    }

    private function doOverwrite(
        mixed $messages,
        bool $newline,
        ?int $size,
        bool $stderr,
        int $verbosity
    ): void {
        $target = $stderr ? $this->getErrorOutput() : $this->output;
        $target->write("\r\033[K", false, OutputInterface::OUTPUT_RAW);
        $this->doWrite($messages, $newline, $stderr, $verbosity);
    }

    /* =========================================================
       QUESTION HELPERS
    ========================================================= */

    public function ask(string $question, mixed $default = null): mixed
    {
        return $this->getQuestionHelper()->ask($this->input, $this->output, new Question($question, $default));
    }

    public function askConfirmation(string $question, bool $default = true): bool
    {
        return (bool) $this->getQuestionHelper()->ask(
            $this->input,
            $this->output,
            new ConfirmationQuestion($question, $default)
        );
    }

    public function askAndValidate(string $question, callable $validator, ?int $attempts = null, mixed $default = null): mixed
    {
        $q = new Question($question, $default);
        $q->setValidator($validator);

        if ($attempts !== null) {
            $q->setMaxAttempts($attempts);
        }

        return $this->getQuestionHelper()->ask($this->input, $this->output, $q);
    }

    public function askAndHideAnswer(string $question): ?string
    {
        $q = new Question($question);
        $q->setHidden(true);

        return $this->getQuestionHelper()->ask($this->input, $this->output, $q);
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

    public function isInteractive(): bool  { return $this->input->isInteractive(); }
    public function isVerbose(): bool      { return $this->output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE; }
    public function isVeryVerbose(): bool  { return $this->output->getVerbosity() >= OutputInterface::VERBOSITY_VERY_VERBOSE; }
    public function isDebug(): bool        { return $this->output->getVerbosity() >= OutputInterface::VERBOSITY_DEBUG; }
    public function isDecorated(): bool    { return $this->output->isDecorated(); }
}