<?php

declare(strict_types=1);

namespace AlfacodeTeam\PhpIoCli;

use AlfacodeTeam\PhpIoCli\Depends\Colors;
use Psr\Log\LogLevel;

/**
 * Enterprise Base IO.
 * Bridges PSR-3 Logging with ANSI CLI Output.
 */
abstract class BaseIO implements IOInterface
{
    // FIX: $messages was untyped — PHPStan error "has parameter $messages with no type specified".
    // Typed as string|array to match the IOInterface contract for write/writeError.
    public function writeRaw(string|array $messages, bool $newline = true, int $verbosity = self::NORMAL): void
    {
        $this->write($messages, $newline, $verbosity);
    }

    public function writeErrorRaw(string|array $messages, bool $newline = true, int $verbosity = self::NORMAL): void
    {
        $this->writeError($messages, $newline, $verbosity);
    }

    /* =========================================================
       PSR-3 IMPLEMENTATION
    ========================================================= */

    public function emergency(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::EMERGENCY, $message, $context);
    }

    public function alert(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::ALERT, $message, $context);
    }

    public function critical(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::CRITICAL, $message, $context);
    }

    public function error(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::ERROR, $message, $context);
    }

    public function warning(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::WARNING, $message, $context);
    }

    public function notice(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::NOTICE, $message, $context);
    }

    public function info(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::INFO, $message, $context);
    }

    public function debug(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::DEBUG, $message, $context);
    }

    /**
     * Core logging logic with ANSI theming and safe JSON context serialisation.
     *
     * @param mixed|LogLevel::* $level
     */
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $output = (string) $message;

        if ($context !== []) {
            $json = Silencer::call(static fn() => json_encode(
                $context,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE,
            ));

            if ($json) {
                $output .= ' ' . Colors::muted($json);
            }
        }

        $formatted = match ($level) {
            LogLevel::EMERGENCY,
            LogLevel::ALERT,
            LogLevel::CRITICAL,
            LogLevel::ERROR => Colors::error($output),
            LogLevel::WARNING => Colors::warning($output),
            LogLevel::NOTICE,
            LogLevel::INFO => Colors::info($output),
            default => Colors::muted($output),
        };

        $targetVerbosity = match ($level) {
            LogLevel::NOTICE => self::VERBOSE,
            LogLevel::DEBUG => self::DEBUG,
            default => self::NORMAL,
        };

        if (in_array($level, [
            LogLevel::EMERGENCY,
            LogLevel::ALERT,
            LogLevel::CRITICAL,
            LogLevel::ERROR,
            LogLevel::WARNING,
        ], true)) {
            $this->writeError($formatted, true, $targetVerbosity);

            return;
        }

        $this->write($formatted, true, $targetVerbosity);
    }
}
