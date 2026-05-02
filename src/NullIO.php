<?php 
declare(strict_types=1);

namespace AlfacodeTeam\PhpIoCli;

/**
 * A silent, non-interactive IO implementation.
 * Perfect for CI environments and unit tests.
 */
class NullIO extends BaseIO
{
    public function isInteractive(): bool { return false; }
    public function isVerbose(): bool     { return false; }
    public function isVeryVerbose(): bool { return false; }
    public function isDebug(): bool       { return false; }
    public function isDecorated(): bool   { return false; }

    public function write($messages, bool $newline = true, int $verbosity = self::NORMAL): void {}
    public function writeError($messages, bool $newline = true, int $verbosity = self::NORMAL): void {}
    public function overwrite($messages, bool $newline = true, ?int $size = null, int $verbosity = self::NORMAL): void {}
    public function overwriteError($messages, bool $newline = true, ?int $size = null, int $verbosity = self::NORMAL): void {}

    public function ask(string $question, mixed $default = null): mixed 
    {
        return $default;
    }

    public function askConfirmation(string $question, bool $default = true): bool 
    {
        return $default;
    }

    public function askAndValidate(string $question, callable $validator, ?int $attempts = null, mixed $default = null): mixed 
    {
        return $default;
    }

    public function askAndHideAnswer(string $question): ?string 
    {
        return null;
    }

    public function select(string $question, array $choices, mixed $default, bool|int $attempts = false, string $errorMessage = 'Value "%s" is invalid', bool $multiselect = false): int|string|array|bool
    {
        return $default;
    }
}