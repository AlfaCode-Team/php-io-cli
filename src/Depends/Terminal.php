<?php
declare(strict_types=1);

namespace AlfacodeTeam\PhpIoCli\Depends;

/**
 * Battle-hardened Terminal Driver.
 * Handles raw mode, escape sequences, and cross-platform VT100 support.
 */
final class Terminal
{
    private static bool $rawEnabled = false;
    private static ?string $originalMode = null;

    public static function isWindows(): bool
    {
        return PHP_OS_FAMILY === 'Windows';
    }

    /* =========================================================
       RAW MODE (Reliable + Clean Cleanup)
    ========================================================= */

    public static function enableRaw(): void
    {
        if (self::$rawEnabled)
            return;

        if (self::isWindows()) {
            // Enable ANSI/VT100 support for modern Windows Terminal/CMD
            if (function_exists('sapi_windows_vt100_support')) {
                @sapi_windows_vt100_support(STDOUT, true);
                @sapi_windows_vt100_support(STDIN, true);
            }
        } else {
            // Unix: Save current state and disable canonical mode/echo
            self::$originalMode = shell_exec('stty -g');
            // Enable raw mode
            system('stty -icanon -echo min 1 time 0');

            // Signal Handling: Restore terminal if user hits Ctrl+C
            if (function_exists('pcntl_signal')) {
                pcntl_async_signals(true);
                pcntl_signal(SIGINT, fn() => self::exitGracefully());
                pcntl_signal(SIGTERM, fn() => self::exitGracefully());
            }
        }

        self::$rawEnabled = true;
        register_shutdown_function([self::class, 'disableRaw']);
    }

    private static function exitGracefully(): void
    {
        self::disableRaw();
        echo PHP_EOL . Colors::error(" Process interrupted.") . PHP_EOL;
        exit(1);
    }

    public static function disableRaw(): void
    {
        if (!self::$rawEnabled)
            return;

        if (!self::isWindows() && self::$originalMode) {
            system('stty ' . self::$originalMode);
        }

        // Always show the cursor again on exit
        echo "\033[?25h";

        self::$rawEnabled = false;
    }

    /* =========================================================
       INPUT HANDLING (No-Ghosting Logic)
    ========================================================= */

    public static function readKey(): string
    {
        $char = fgetc(STDIN);

        // Check for Escape character (\e or \033)
        if ($char === "\e") {
            return self::readEscapeSequence($char);
        }

        return (string) $char;
    }

    /**
     * Fixes the "Headache": 
     * Uses a tiny 10ms settle-time to ensure multi-byte keys (Arrows, Home)
     * are captured as a single string instead of being fragmented.
     */
    private static function readEscapeSequence(string $first): string
    {
        $sequence = $first;
        stream_set_blocking(STDIN, false);

        $start = microtime(true);
        while ((microtime(true) - $start) < 0.01) { // 10ms window
            $char = fgetc(STDIN);
            if ($char !== false) {
                $sequence .= $char;
                $start = microtime(true); // reset window if we're still getting bytes
            }
        }

        stream_set_blocking(STDIN, true);
        return $sequence;
    }

    /* =========================================================
       OUTPUT HELPERS
    ========================================================= */

    public static function clearLine(): void
    {
        echo "\r\033[2K";
    }

    public static function moveCursorUp(int $lines = 1): void
    {
        echo "\033[{$lines}A";
    }

    public static function hideCursor(): void
    {
        echo "\033[?25l";
    }

    public static function showCursor(): void
    {
        echo "\033[?25h";
    }
}