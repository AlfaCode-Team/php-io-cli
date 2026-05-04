<?php

declare(strict_types=1);

namespace AlfacodeTeam\PhpIoCli\Depends;

use AlfacodeTeam\PhpIoCli\KeyReaderInterface;

/**
 * Production KeyReader — delegates to the real terminal driver.
 *
 * This is the default implementation injected by AbstractPrompt when no
 * custom reader is supplied. It wraps the existing Terminal static methods
 * so all existing behaviour is preserved exactly.
 */
final class TerminalKeyReader implements KeyReaderInterface
{
    public function readKey(): string
    {
        return Terminal::readKey();
    }

    public function setUp(): void
    {
        Terminal::enableRaw();
    }

    public function tearDown(): void
    {
        Terminal::disableRaw();
    }
}
