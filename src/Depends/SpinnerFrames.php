<?php
declare(strict_types=1);

namespace AlfacodeTeam\PhpIoCli\Depends;
final class SpinnerFrames
{
    public static function dots(): array
    {
        return ['⠋','⠙','⠹','⠸','⠼','⠴','⠦','⠧','⠇','⠏'];
    }

    public static function bars(): array
    {
        return ['▁','▃','▄','▅','▆','▇','█'];
    }

    public static function line(): array
    {
        return ['.', '..', '...', '....'];
    }

    public static function default(): array
    {
        return ['-', '\\', '|', '/'];
    }
}