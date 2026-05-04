<?php

declare(strict_types=1);

namespace AlfacodeTeam\PhpIoCli;

/**
 * A completely silent, non-interactive IO implementation.
 * Every interactive method returns its $default value.
 * Every write method is a no-op.
 */
class NullIO extends BaseIO
{
    /* =========================================================
       State
    ========================================================= */

    public function isInteractive(): bool
    {
        return false;
    }

    public function isVerbose(): bool
    {
        return false;
    }

    public function isVeryVerbose(): bool
    {
        return false;
    }

    public function isDebug(): bool
    {
        return false;
    }

    public function isDecorated(): bool
    {
        return false;
    }

    /* =========================================================
       Writing — all no-ops
    ========================================================= */

    public function write($messages, bool $newline = true, int $verbosity = self::NORMAL): void {}

    public function writeError($messages, bool $newline = true, int $verbosity = self::NORMAL): void {}

    // FIX: $messages was untyped — PHPStan error. Typed as string|array to match IOInterface.
    public function writeRaw(string|array $messages, bool $newline = true, int $verbosity = self::NORMAL): void {}

    // FIX: same as above
    public function writeErrorRaw(string|array $messages, bool $newline = true, int $verbosity = self::NORMAL): void {}

    public function overwrite($messages, bool $newline = true, int|null $size = null, int $verbosity = self::NORMAL): void {}

    public function overwriteError($messages, bool $newline = true, int|null $size = null, int $verbosity = self::NORMAL): void {}

    /* =========================================================
       Interactive — all return defaults
    ========================================================= */

    public function ask(string $question, mixed $default = null): mixed
    {
        return $default;
    }

    public function askConfirmation(string $question, bool $default = true): bool
    {
        return $default;
    }

    public function askAndValidate(
        string   $question,
        callable $validator,
        int|null     $attempts = null,
        mixed    $default = null,
    ): mixed {
        return $default;
    }

    public function askAndHideAnswer(string $question): string|null
    {
        return null;
    }

    public function select(
        string   $question,
        array    $choices,
        mixed    $default,
        bool|int $attempts = false,
        string   $errorMessage = 'Value "%s" is invalid',
        bool     $multiselect = false,
    ): int|string|array|bool {
        return $default;
    }
}
