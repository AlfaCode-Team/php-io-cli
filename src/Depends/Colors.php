<?php
declare(strict_types=1);

namespace AlfacodeTeam\PhpIoCli\Depends;

/**
 * Modern ANSI Color & Style Handler
 */
final class Colors
{
    private static ?bool $enabled = null;

    /**
     * Determine if the current environment supports/allows colors.
     */
    public static function isEnabled(): bool
    {
        if (self::$enabled !== null) {
            return self::$enabled;
        }

        // 1. Check for NO_COLOR environment variable (Standard)
        if (getenv('NO_COLOR') !== false) {
            return self::$enabled = false;
        }

        // 2. Check for FORCE_COLOR
        if (getenv('FORCE_COLOR') !== false) {
            return self::$enabled = true;
        }

        // 3. Check for Windows VT100 support
        if (PHP_OS_FAMILY === 'Windows') {
            return self::$enabled = function_exists('sapi_windows_vt100_support') 
                && @sapi_windows_vt100_support(STDOUT);
        }

        // 4. Check if we are in a TTY (terminal) or being piped
        return self::$enabled = function_exists('posix_isatty') && @posix_isatty(STDOUT);
    }

    public static function enable(): void { self::$enabled = true; }
    public static function disable(): void { self::$enabled = false; }

    /* --- Constants --- */
    public const RESET     = "\033[0m";
    public const BOLD      = "\033[1m";
    public const DIM       = "\033[2m";
    public const ITALIC    = "\033[3m";
    public const UNDERLINE = "\033[4m";

    // Standard 16-color palette
    public const RED     = "\033[31m";
    public const GREEN   = "\033[32m";
    public const YELLOW  = "\033[33m";
    public const BLUE    = "\033[34m";
    public const MAGENTA = "\033[35m";
    public const CYAN    = "\033[36m";
    public const GRAY    = "\033[90m";

    /* --- Methods --- */

    public static function wrap(string $text, string|array $styles): string
    {
        if (!self::isEnabled()) {
            return $text;
        }

        $prefix = is_array($styles) ? implode('', $styles) : $styles;
        return $prefix . $text . self::RESET;
    }

    /**
     * Hex to ANSI TrueColor
     * Usage: Colors::hex('#ff5733', 'Alert!')
     */
    public static function hex(string $hex, string $text = ''): string
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        
        [$r, $g, $b] = sscanf($hex, "%02x%02x%02x");
        $code = "\033[38;2;{$r};{$g};{$b}m";
        
        return $text ? self::wrap($text, $code) : $code;
    }

    public static function line(string $text, string|array $styles = []): void
    {
        echo self::wrap($text, $styles) . PHP_EOL;
    }

    /* --- Theme Methods (Improved) --- */

    public static function success(string $text): string
    {
        return self::wrap(" ✔ {$text} ", [self::GREEN, self::BOLD]);
    }

    public static function error(string $text): string
    {
        // Adding a prefix like [ERROR] or a cross makes it accessible for color-blind users
        return self::wrap(" ✘ {$text} ", [self::RED, self::BOLD]);
    }

    public static function strip(string $text): string
    {
        return preg_replace('/\033\[[0-9;]*m/', '', $text);
    }
     /* --- Theme Methods (Synchronized with BaseIO) --- */


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

}