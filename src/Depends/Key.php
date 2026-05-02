<?php
declare(strict_types=1);

namespace AlfacodeTeam\PhpIoCli\Depends;

/**
 * Constants and normalization for Terminal Input sequences.
 */
final class Key
{
    // Navigation
    public const UP    = "\e[A";
    public const DOWN  = "\e[B";
    public const RIGHT = "\e[C";
    public const LEFT  = "\e[D";
    public const HOME  = "\e[H";
    public const END   = "\e[F";

    // Actions
    public const ENTER     = "\n";
    public const RETURN    = "\r";
    public const TAB       = "\t";
    public const ESC       = "\e";
    public const BACKSPACE = "\x7f";
    public const DELETE    = "\e[3~";

    // Common Ctrl sequences
    public const CTRL_C = "\x03";
    public const CTRL_D = "\x04";

    /**
     * Normalizes raw terminal bytes into readable command strings.
     */
    public static function normalize(string $key): string
    {
        return match ($key) {
            self::UP                => 'UP',
            self::DOWN              => 'DOWN',
            self::RIGHT             => 'RIGHT',
            self::LEFT              => 'LEFT',
            self::HOME              => 'HOME',
            self::END               => 'END',
            self::ENTER, self::RETURN => 'ENTER',
            self::TAB               => 'TAB',
            self::ESC               => 'ESC',
            self::BACKSPACE, "\x08" => 'BACKSPACE',
            self::DELETE            => 'DELETE',
            self::CTRL_C            => 'CTRL_C',
            self::CTRL_D            => 'CTRL_D',
            default                 => $key
        };
    }

    /**
     * Check if the key is a printable character (vs a control sequence).
     */
    public static function isPrintable(string $key): bool
    {
        // If it starts with an Escape character or is a control char, it's not printable.
        return mb_strlen($key) === 1 && ord($key) >= 32;
    }
}