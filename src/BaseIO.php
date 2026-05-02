<?php
declare(strict_types=1);

namespace AlfacodeTeam\PhpIoCli;

use AlfacodeTeam\PhpIoCli\Depends\Colors;
use Psr\Log\LogLevel;
use Stringable;

/**
 * Enterprise Base IO
 * Bridges PSR-3 Logging with ANSI CLI Output.
 */
abstract class BaseIO implements IOInterface
{
    /**
     * @inheritDoc
     */
    public function writeRaw($messages, bool $newline = true, int $verbosity = self::NORMAL): void
    {
        $this->write($messages, $newline, $verbosity);
    }

    /**
     * @inheritDoc
     */
    public function writeErrorRaw($messages, bool $newline = true, int $verbosity = self::NORMAL): void
    {
        $this->writeError($messages, $newline, $verbosity);
    }

    /* =========================================================
       PSR-3 IMPLEMENTATION
    ========================================================= */

    public function emergency(string|Stringable $message, array $context = []): void 
    { $this->log(LogLevel::EMERGENCY, $message, $context); }

    public function alert(string|Stringable $message, array $context = []): void 
    { $this->log(LogLevel::ALERT, $message, $context); }

    public function critical(string|Stringable $message, array $context = []): void 
    { $this->log(LogLevel::CRITICAL, $message, $context); }

    public function error(string|Stringable $message, array $context = []): void 
    { $this->log(LogLevel::ERROR, $message, $context); }

    public function warning(string|Stringable $message, array $context = []): void 
    { $this->log(LogLevel::WARNING, $message, $context); }

    public function notice(string|Stringable $message, array $context = []): void 
    { $this->log(LogLevel::NOTICE, $message, $context); }

    public function info(string|Stringable $message, array $context = []): void 
    { $this->log(LogLevel::INFO, $message, $context); }

    public function debug(string|Stringable $message, array $context = []): void 
    { $this->log(LogLevel::DEBUG, $message, $context); }

    /**
     * Core logging logic with Silencer and ANSI themes.
     * 
     * @param mixed|LogLevel::* $level
     * @param string|Stringable $message
     */
    public function log($level, $message, array $context = []): void
    {
        $output = (string) $message;

        // Use the improved Silencer to safely encode context
        if ($context !== []) {
            $json = Silencer::call(static fn() => json_encode(
                $context, 
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE
            ));
            
            if ($json) {
                $output .= ' ' . Colors::muted($json);
            }
        }

        // Map levels using PHP 8 match for Enterprise performance
        $formatted = match ($level) {
            LogLevel::EMERGENCY,
            LogLevel::ALERT,
            LogLevel::CRITICAL,
            LogLevel::ERROR   => Colors::error($output),
            LogLevel::WARNING => Colors::warning($output),
            LogLevel::NOTICE,
            LogLevel::INFO    => Colors::info($output),
            default           => Colors::muted($output),
        };

        // Determine target and verbosity
        $targetVerbosity = match ($level) {
            LogLevel::NOTICE => self::VERBOSE,
            LogLevel::DEBUG  => self::DEBUG,
            default          => self::NORMAL,
        };

        // If it's a high-priority error level, use writeError
        if (in_array($level, [LogLevel::EMERGENCY, LogLevel::ALERT, LogLevel::CRITICAL, LogLevel::ERROR, LogLevel::WARNING], true)) {
            $this->writeError($formatted, true, $targetVerbosity);
            return;
        }

        $this->write($formatted, true, $targetVerbosity);
    }
}