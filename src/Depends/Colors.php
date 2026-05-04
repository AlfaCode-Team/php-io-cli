<?php

declare(strict_types=1);

namespace AlfacodeTeam\PhpIoCli\Depends;

/**
 * Modern ANSI Color & Style Handler
 */
final class Colors
{
    /* --- Constants --- */
    public const RESET = "\033[0m";

    public const BOLD = "\033[1m";

    public const DIM = "\033[2m";

    public const ITALIC = "\033[3m";

    public const UNDERLINE = "\033[4m";

    public const RED = "\033[31m";

    public const GREEN = "\033[32m";

    public const YELLOW = "\033[33m";

    public const BLUE = "\033[34m";

    public const MAGENTA = "\033[35m";

    public const CYAN = "\033[36m";

    public const WHITE = "\033[37m";

    public const GRAY = "\033[90m";

    public const BLACK = "\033[30m";

    public const BG_CYAN = "\033[46m";

    private static bool|null $enabled = null;

    /**
     * Determine if the current environment supports/allows colors.
     */
    public static function isEnabled(): bool
    {
        if (self::$enabled !== null) {
            return self::$enabled;
        }

        // 1. NO_COLOR standard (https://no-color.org)
        if (getenv('NO_COLOR') !== false) {
            return self::$enabled = false;
        }

        // 2. FORCE_COLOR override
        if (getenv('FORCE_COLOR') !== false) {
            return self::$enabled = true;
        }

        // 3. Windows VT100
        if (PHP_OS_FAMILY === 'Windows') {
            return self::$enabled = function_exists('sapi_windows_vt100_support')
                && @sapi_windows_vt100_support(STDOUT);
        }

        // 4. Unix TTY
        return self::$enabled = function_exists('posix_isatty') && @posix_isatty(STDOUT);
    }

    public static function enable(): void
    {
        self::$enabled = true;
    }

    public static function disable(): void
    {
        self::$enabled = false;
    }

    /* --- Core --- */

    public static function wrap(string $text, string|array $styles): string
    {
        if (!self::isEnabled()) {
            return $text;
        }

        $prefix = is_array($styles) ? implode('', $styles) : $styles;

        return $prefix . $text . self::RESET;
    }

    /**
     * Hex to ANSI TrueColor.
     * Usage: Colors::hex('#ff5733', 'Alert!')
     */
    public static function hex(string $hex, string $text = ''): string
    {
        $hex = mb_ltrim($hex, '#');
        if (mb_strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        /** @var array{int,int,int} $rgb */
        $rgb = sscanf($hex, '%02x%02x%02x');
        [$r, $g, $b] = $rgb;
        $code = "\033[38;2;{$r};{$g};{$b}m";

        return $text ? self::wrap($text, $code) : $code;
    }

    public static function line(string $text, string|array $styles = []): void
    {
        echo self::wrap($text, $styles) . PHP_EOL;
    }

    /* --- Theme --- */

    public static function success(string $text): string
    {
        return self::wrap(" ✔ {$text} ", [self::GREEN, self::BOLD]);
    }

    public static function error(string $text): string
    {
        return self::wrap(" ✘ {$text} ", [self::RED, self::BOLD]);
    }

    public static function warning(string $text): string
    {
        return self::wrap(" ! {$text}", [self::YELLOW, self::BOLD]);
    }

    public static function info(string $text): string
    {
        return self::wrap($text, self::CYAN);
    }

    public static function muted(string $text): string
    {
        return self::wrap($text, self::GRAY);
    }

    /**
     * Strip ALL ANSI/VT100 control sequences from a string, including:
     *   - SGR color/style codes  \033[0m  \033[1;32m  etc.
     *   - Cursor/erase sequences \033[2K  \033[3A     etc.
     *   - Bare carriage returns  \r
     *
     * Previously only SGR sequences were matched, which left \033[2K and \r
     * artefacts in BufferIO::getOutput() test snapshots.
     */
    public static function strip(string $text): string
    {
        // \033[ … <any letter>  covers every CSI sequence (SGR, erase, cursor moves …)
        // \r                   covers bare carriage returns emitted by Renderer::display()
        return (string) preg_replace('/\033\[[0-9;]*[A-Za-z]|\r/', '', $text);
    }
}
