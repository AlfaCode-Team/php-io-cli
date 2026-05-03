<?php

declare(strict_types=1);

namespace AlfacodeTeam\PhpIoCli\Depends;

/**
 * Immutable result of a shell command execution.
 */
final class ShellResult
{
    /** @param string[] $stdout @param string[] $stderr */
    public function __construct(
        public readonly int   $exitCode,
        public readonly array $stdout,
        public readonly array $stderr,
    ) {}

    public function ok(): bool
    {
        return $this->exitCode === 0;
    }
    public function failed(): bool
    {
        return $this->exitCode !== 0;
    }

    /** All stdout lines joined with newlines. */
    public function output(): string
    {
        return implode(PHP_EOL, $this->stdout);
    }

    /** All stderr lines joined with newlines. */
    public function errors(): string
    {
        return implode(PHP_EOL, $this->stderr);
    }

    /**
     * Returns a filtered, non-empty subset of stderr lines.
     * Useful for surfacing only meaningful error messages.
     *
     * @return string[]
     */
    public function meaningfulErrors(): array
    {
        return array_values(array_filter(
            $this->stderr,
            fn(string $l) => trim($l) !== ''
        ));
    }
}
