<?php declare(strict_types=1);

/*
 * This file is part of Composer.
 *
 * (c) Nils Adermann <naderman@naderman.de>
 *     Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace AlfacodeTeam\PhpIoCli;

use Psr\Log\LogLevel;

abstract class BaseIO implements IOInterface
{

    /**
     * @inheritDoc
     */
    public function writeRaw($messages, bool $newline = true, int $verbosity = self::NORMAL)
    {
        $this->write($messages, $newline, $verbosity);
    }

    /**
     * @inheritDoc
     */
    public function writeErrorRaw($messages, bool $newline = true, int $verbosity = self::NORMAL)
    {
        $this->writeError($messages, $newline, $verbosity);
    }
    /**
     * @param string|\Stringable $message
     */
    public function emergency($message, array $context = []): void
    {
        $this->log(LogLevel::EMERGENCY, $message, $context);
    }

    /**
     * @param string|\Stringable $message
     */
    public function alert($message, array $context = []): void
    {
        $this->log(LogLevel::ALERT, $message, $context);
    }

    /**
     * @param string|\Stringable $message
     */
    public function critical($message, array $context = []): void
    {
        $this->log(LogLevel::CRITICAL, $message, $context);
    }

    /**
     * @param string|\Stringable $message
     */
    public function error($message, array $context = []): void
    {
        $this->log(LogLevel::ERROR, $message, $context);
    }

    /**
     * @param string|\Stringable $message
     */
    public function warning($message, array $context = []): void
    {
        $this->log(LogLevel::WARNING, $message, $context);
    }

    /**
     * @param string|\Stringable $message
     */
    public function notice($message, array $context = []): void
    {
        $this->log(LogLevel::NOTICE, $message, $context);
    }

    /**
     * @param string|\Stringable $message
     */
    public function info($message, array $context = []): void
    {
        $this->log(LogLevel::INFO, $message, $context);
    }

    /**
     * @param string|\Stringable $message
     */
    public function debug($message, array $context = []): void
    {
        $this->log(LogLevel::DEBUG, $message, $context);
    }

    /**
     * @param mixed|LogLevel::* $level
     * @param string|\Stringable $message
     */
    public function log($level, $message, array $context = []): void
    {
        $message = (string) $message;

        if ($context !== []) {
            $json = Silencer::call('json_encode', $context, JSON_INVALID_UTF8_IGNORE | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($json !== false) {
                $message .= ' ' . $json;
            }
        }

        if (in_array($level,[LogLevel::EMERGENCY, LogLevel::ALERT, LogLevel::CRITICAL, LogLevel::ERROR], true)) {
            $this->writeError('<error>' . $message . '</error>');
        } elseif ($level === LogLevel::WARNING) {
            // Changed <warning> to <comment> so Symfony makes it yellow
            $this->writeError('<comment>' . $message . '</comment>'); 
        } elseif ($level === LogLevel::NOTICE) {
            $this->writeError('<info>' . $message . '</info>', true, self::VERBOSE);
        } elseif ($level === LogLevel::INFO) {
            // Changed self::VERY_VERBOSE to self::NORMAL so it prints by default
            $this->writeError('<info>' . $message . '</info>', true, self::NORMAL); 
        } else {
            $this->writeError($message, true, self::DEBUG);
        }
    }
}
