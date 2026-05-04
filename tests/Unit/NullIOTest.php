<?php

declare(strict_types=1);

namespace AlfacodeTeam\PhpIoCli\Tests\Unit;

use AlfacodeTeam\PhpIoCli\NullIO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(NullIO::class)]
final class NullIOTest extends TestCase
{
    private NullIO $io;

    protected function setUp(): void
    {
        $this->io = new NullIO();
    }

    // ---------------------------------------------------------------
    // State flags
    // ---------------------------------------------------------------

    public function test_is_not_interactive(): void
    {
        $this->assertFalse($this->io->isInteractive());
    }

    public function test_is_not_verbose(): void
    {
        $this->assertFalse($this->io->isVerbose());
        $this->assertFalse($this->io->isVeryVerbose());
        $this->assertFalse($this->io->isDebug());
        $this->assertFalse($this->io->isDecorated());
    }

    // ---------------------------------------------------------------
    // Write methods — all no-ops (no exception = pass)
    // ---------------------------------------------------------------

    public function test_write_does_not_throw(): void
    {
        $this->io->write('output');
        $this->io->writeError('error');
        $this->io->writeRaw('raw');
        $this->io->writeErrorRaw('rawError');
        $this->io->overwrite('overwrite');
        $this->io->overwriteError('overwriteError');

        $this->assertTrue(true);
    }

    // ---------------------------------------------------------------
    // Interactive methods — return defaults
    // ---------------------------------------------------------------

    public function test_ask_returns_default(): void
    {
        $this->assertSame('fallback', $this->io->ask('Question?', 'fallback'));
        $this->assertNull($this->io->ask('Question?'));
    }

    public function test_ask_confirmation_returns_default(): void
    {
        $this->assertTrue($this->io->askConfirmation('Sure?', true));
        $this->assertFalse($this->io->askConfirmation('Sure?', false));
    }

    public function test_ask_and_validate_returns_default(): void
    {
        $result = $this->io->askAndValidate('Name?', static fn($v) => $v, null, 'myDefault');

        $this->assertSame('myDefault', $result);
    }

    public function test_ask_and_hide_answer_returns_null(): void
    {
        $this->assertNull($this->io->askAndHideAnswer('Password?'));
    }

    public function test_select_returns_default(): void
    {
        $result = $this->io->select('Choose', ['a', 'b', 'c'], 'b');

        $this->assertSame('b', $result);
    }

    // ---------------------------------------------------------------
    // PSR-3 methods (inherited via BaseIO) — no throws
    // ---------------------------------------------------------------

    public function test_psr3_methods_do_not_throw(): void
    {
        $this->io->emergency('msg');
        $this->io->alert('msg');
        $this->io->critical('msg');
        $this->io->error('msg');
        $this->io->warning('msg');
        $this->io->notice('msg');
        $this->io->info('msg');
        $this->io->debug('msg');

        $this->assertTrue(true);
    }
}
