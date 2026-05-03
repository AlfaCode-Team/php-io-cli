<?php
declare(strict_types=1);

namespace AlfacodeTeam\PhpIoCli;

/**
 * A completely silent, non-interactive IO implementation.
 *
 * Every interactive method returns its $default value.
 * Every write method is a no-op.
 *
 * Ideal for:
 *   • CI / automated pipelines (no TTY)
 *   • Unit tests that don't care about output
 *   • Background/daemon processes
 */
class NullIO extends BaseIO
{
    /* =========================================================
       State
    ========================================================= */

    public function isInteractive(): bool  { return false; }
    public function isVerbose(): bool      { return false; }
    public function isVeryVerbose(): bool  { return false; }
    public function isDebug(): bool        { return false; }
    public function isDecorated(): bool    { return false; }

    /* =========================================================
       Writing — all no-ops
       Explicitly overriding every signature here so that NullIO
       can never accidentally inherit a version that does real I/O.
    ========================================================= */

    public function write($messages, bool $newline = true, int $verbosity = self::NORMAL): void {}

    public function writeError($messages, bool $newline = true, int $verbosity = self::NORMAL): void {}

    /**
     * Explicitly overriding writeRaw so it does NOT delegate to write()
     * via BaseIO (which would also be a no-op here, but the override makes
     * the intent crystal-clear and avoids any future accidental coupling).
     */
    public function writeRaw($messages, bool $newline = true, int $verbosity = self::NORMAL): void {}

    public function writeErrorRaw($messages, bool $newline = true, int $verbosity = self::NORMAL): void {}

    public function overwrite($messages, bool $newline = true, ?int $size = null, int $verbosity = self::NORMAL): void {}

    public function overwriteError($messages, bool $newline = true, ?int $size = null, int $verbosity = self::NORMAL): void {}

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
        ?int     $attempts = null,
        mixed    $default  = null
    ): mixed {
        return $default;
    }

    public function askAndHideAnswer(string $question): ?string
    {
        return null;
    }

    public function select(
        string   $question,
        array    $choices,
        mixed    $default,
        bool|int $attempts     = false,
        string   $errorMessage = 'Value "%s" is invalid',
        bool     $multiselect  = false
    ): int|string|array|bool {
        return $default;
    }
}