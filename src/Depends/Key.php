<?php
declare(strict_types=1);

namespace AlfacodeTeam\PhpIoCli\Depends;

final class Key
{
    public const UP = "\033[A";
    public const DOWN = "\033[B";
    public const ENTER = "\n";
    public const RETURN = "\r";
    public const BACKSPACE = "\177";
    public const BACKSPACE_ALT = "\x08";

    public static function normalize(string $key): string
    {
        return match ($key) {
            self::UP => 'UP',
            self::DOWN => 'DOWN',
            self::ENTER, self::RETURN => 'ENTER',
            self::BACKSPACE, self::BACKSPACE_ALT => 'BACKSPACE',
            default => $key
        };
    }
}