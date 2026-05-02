<?php
declare(strict_types=1);

namespace AlfacodeTeam\PhpIoCli;

use Psr\Log\LoggerInterface;

/**
 * The unified Input/Output helper interface.
 */
interface IOInterface extends LoggerInterface
{
    public const QUIET = 1;
    public const NORMAL = 2;
    public const VERBOSE = 4;
    public const VERY_VERBOSE = 8;
    public const DEBUG = 16;

    public function isInteractive(): bool;
    public function isVerbose(): bool;
    public function isVeryVerbose(): bool;
    public function isDebug(): bool;
    public function isDecorated(): bool;

    /**
     * @param string|string[] $messages
     */
    public function write($messages, bool $newline = true, int $verbosity = self::NORMAL): void;

    /**
     * @param string|string[] $messages
     */
    public function writeError($messages, bool $newline = true, int $verbosity = self::NORMAL): void;

    public function writeRaw($messages, bool $newline = true, int $verbosity = self::NORMAL): void;

    public function writeErrorRaw($messages, bool $newline = true, int $verbosity = self::NORMAL): void;

    /**
     * @param string|string[] $messages
     */
    public function overwrite($messages, bool $newline = true, ?int $size = null, int $verbosity = self::NORMAL): void;

    /**
     * @param string|string[] $messages
     */
    public function overwriteError($messages, bool $newline = true, ?int $size = null, int $verbosity = self::NORMAL): void;

    /* =========================================================
       INTERACTIVE METHODS
       Note: These can be implemented by calling your 
       reactive components (e.g. Select.php) internally.
    ========================================================= */

    public function ask(string $question, mixed $default = null): mixed;

    public function askConfirmation(string $question, bool $default = true): bool;

    public function askAndValidate(string $question, callable $validator, ?int $attempts = null, mixed $default = null): mixed;

    public function askAndHideAnswer(string $question): ?string;

    /**
     * @param string[] $choices
     * @phpstan-return ($multiselect is true ? list<string> : string|int|bool)
     */
    public function select(
        string $question, 
        array $choices, 
        mixed $default, 
        bool|int $attempts = false, 
        string $errorMessage = 'Value "%s" is invalid', 
        bool $multiselect = false
    ): int|string|array|bool;
}