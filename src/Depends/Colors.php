<?php
declare(strict_types=1);

namespace AlfacodeTeam\PhpIoCli\Depends;

/* =========================================================
   ANSI + COLORS
========================================================= */

final class Colors
{
    private static bool $enabled = true;

    /* =========================================================
       TOGGLE (important for CI / logs / non-TTY)
    ========================================================= */

    public static function enable(): void
    {
        self::$enabled = true;
    }

    public static function disable(): void
    {
        self::$enabled = false;
    }

    public static function isEnabled(): bool
    {
        return self::$enabled;
    }

    /* =========================================================
       BASIC STYLES
    ========================================================= */

    public const RESET = "\033[0m";

    public const BOLD = "\033[1m";
    public const DIM = "\033[2m";
    public const ITALIC = "\033[3m";
    public const UNDERLINE = "\033[4m";

    /* =========================================================
       FOREGROUND COLORS (16-color)
    ========================================================= */

    public const BLACK = "\033[30m";
    public const RED = "\033[31m";
    public const GREEN = "\033[32m";
    public const YELLOW = "\033[33m";
    public const BLUE = "\033[34m";
    public const MAGENTA = "\033[35m";
    public const CYAN = "\033[36m";
    public const WHITE = "\033[37m";
    public const GRAY = "\033[90m";

    /* =========================================================
       BACKGROUND COLORS
    ========================================================= */

    public const BG_RED = "\033[41m";
    public const BG_GREEN = "\033[42m";
    public const BG_YELLOW = "\033[43m";
    public const BG_BLUE = "\033[44m";
    public const BG_MAGENTA = "\033[45m";
    public const BG_CYAN = "\033[46m";
    public const BG_GRAY = "\033[100m";

    /* =========================================================
       WRAP (multi-style support)
    ========================================================= */

    public static function wrap(string $text, string|array $styles): string
    {
        if (!self::$enabled) {
            return $text;
        }

        $prefix = is_array($styles)
            ? implode('', $styles)
            : $styles;

        return $prefix . $text . self::RESET;
    }

    /* =========================================================
       RGB / TRUECOLOR SUPPORT
    ========================================================= */

    public static function rgb(int $r, int $g, int $b): string
    {
        return "\033[38;2;{$r};{$g};{$b}m";
    }

    public static function bgRgb(int $r, int $g, int $b): string
    {
        return "\033[48;2;{$r};{$g};{$b}m";
    }

    /* =========================================================
       256 COLOR SUPPORT
    ========================================================= */

    public static function color256(int $code): string
    {
        return "\033[38;5;{$code}m";
    }

    public static function bgColor256(int $code): string
    {
        return "\033[48;5;{$code}m";
    }

    /* =========================================================
       SAFE NESTING (important)
    ========================================================= */

    public static function line(string $text, string|array $styles): string
    {
        return self::wrap($text, $styles) . PHP_EOL;
    }

    /* =========================================================
       STRIP COLORS (for logs / tests)
    ========================================================= */

    public static function strip(string $text): string
    {
        return preg_replace('/\033\[[0-9;]*m/', '', $text);
    }

    /* =========================================================
       THEMES (important for large apps)
    ========================================================= */

    public static function success(string $text): string
    {
        return self::wrap($text, [self::GREEN, self::BOLD]);
    }

    public static function error(string $text): string
    {
        return self::wrap($text, [self::RED, self::BOLD]);
    }

    public static function warning(string $text): string
    {
        return self::wrap($text, [self::YELLOW, self::BOLD]);
    }

    public static function info(string $text): string
    {
        return self::wrap($text, [self::CYAN]);
    }

    public static function muted(string $text): string
    {
        return self::wrap($text, [self::DIM]);
    }
}