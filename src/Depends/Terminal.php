<?php
declare(strict_types=1);

namespace AlfacodeTeam\PhpIoCli\Depends;

/* =========================================================
   WINDOWS COMPATIBILITY LAYER
========================================================= */

final class Terminal
{
    private static bool $rawEnabled = false;
    private static ?string $originalMode = null;

    /* =========================================================
       ENV DETECTION
    ========================================================= */

    public static function isWindows(): bool
    {
        return PHP_OS_FAMILY === 'Windows';
    }

    public static function isTty(): bool
    {
        return function_exists('stream_isatty')
            ? stream_isatty(STDIN)
            : posix_isatty(STDIN);
    }

    /* =========================================================
       RAW MODE (SAFE + RESTORABLE)
    ========================================================= */

    public static function enableRaw(): void
    {
        if (self::$rawEnabled) {
            return;
        }

        if (self::isWindows()) {
            // Windows 10+ supports ANSI, but raw mode is limited
            // fallback: no-op (still usable)
            self::$rawEnabled = true;
            return;
        }

        // Save current terminal mode
        self::$originalMode = shell_exec('stty -g');

        // Enable raw mode
        system('stty -icanon -echo min 1 time 0');

        self::$rawEnabled = true;

        // Ensure restore on shutdown
        register_shutdown_function([self::class, 'disableRaw']);
    }

    public static function disableRaw(): void
    {
        if (!self::$rawEnabled) {
            return;
        }

        if (!self::isWindows() && self::$originalMode) {
            system('stty ' . self::$originalMode);
        }

        self::$rawEnabled = false;
    }

    /* =========================================================
       INPUT HANDLING
    ========================================================= */

    public static function readKey(): string
    {
        $char = fgetc(STDIN);

        if ($char === "\033") {
            return self::readEscapeSequence($char);
        }

        return $char;
    }

    private static function readEscapeSequence(string $first): string
    {
        $sequence = $first;

        // Read next bytes (non-blocking style)
        stream_set_blocking(STDIN, false);

        while (($char = fgetc(STDIN)) !== false) {
            $sequence .= $char;
        }

        stream_set_blocking(STDIN, true);

        return $sequence;
    }

    /* =========================================================
       OUTPUT HELPERS (USED BY RENDERER)
    ========================================================= */

    public static function clearScreen(): void
    {
        echo "\033[2J\033[H";
    }

    public static function clearLine(): void
    {
        echo "\033[2K\r";
    }

    public static function moveCursorUp(int $lines = 1): void
    {
        echo "\033[{$lines}A";
    }

    public static function moveCursorDown(int $lines = 1): void
    {
        echo "\033[{$lines}B";
    }

    public static function moveCursorToStart(): void
    {
        echo "\r";
    }

    /* =========================================================
       BUFFER CONTROL
    ========================================================= */

    public static function flush(): void
    {
        fflush(STDOUT);
    }
}