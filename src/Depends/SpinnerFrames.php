<?php
declare(strict_types=1);

namespace AlfacodeTeam\PhpIoCli\Depends;

/**
 * Collection of ANSI-compatible spinner frames.
 */
final class SpinnerFrames
{
    /**
     * Get a frame set by name.
     */
    public static function get(string $name = 'dots'): array
    {
        return match ($name) {
            'bars'    => [' ', '▃', '▄', '▅', '▆', '▇', '█', '▇', '▆', '▅', '▄', '▃'],
            'line'    => ['-', '\\', '|', '/'],
            'pulse'   => ['░', '▒', '▓', '█', '▓', '▒'],
            'arc'     => ['◜', '◠', '◝', '◞', '◡', '◟'],
            'bounce'  => ['(●          )', '( ●         )', '(  ●        )', '(   ●       )', '(    ●      )', '(     ●     )', '(      ●    )', '(       ●   )', '(        ●  )', '(         ● )', '(          ●)', '(         ● )', '(        ●  )', '(       ●   )', '(      ●    )', '(     ●     )', '(    ●      )', '(   ●       )', '(  ●        )', '( ●         )'],
            default   => ['⠋', '⠙', '⠹', '⠸', '⠼', '⠴', '⠦', '⠧', '⠇', '⠏'],
        };
    }

    public static function dots(): array
    {
        return self::get('dots');
    }

    public static function bars(): array
    {
        return self::get('bars');
    }

    public static function line(): array
    {
        return self::get('line');
    }

    public static function pulse(): array
    {
        return self::get('pulse');
    }
}