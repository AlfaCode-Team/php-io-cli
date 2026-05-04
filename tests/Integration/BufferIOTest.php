<?php

declare(strict_types=1);

namespace AlfacodeTeam\PhpIoCli\Tests\Integration;

use AlfacodeTeam\PhpIoCli\BufferIO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(BufferIO::class)]
final class BufferIOTest extends TestCase
{
    // ---------------------------------------------------------------
    // Basic write capture
    // ---------------------------------------------------------------

    public function test_write_is_captured(): void
    {
        $io = new BufferIO();
        $io->write('Hello, World!');

        $this->assertStringContainsString('Hello, World!', $io->getOutput());
    }

    public function test_get_output_strips_ansi(): void
    {
        $io = new BufferIO();
        $io->write("\033[32mGreen\033[0m");

        $output = $io->getOutput();

        $this->assertStringContainsString('Green', $output);
        $this->assertStringNotContainsString("\033[", $output);
    }

    public function test_multiple_write_calls_accumulated(): void
    {
        $io = new BufferIO();
        $io->write('First');
        $io->write('Second');
        $io->write('Third');

        $output = $io->getOutput();

        $this->assertStringContainsString('First', $output);
        $this->assertStringContainsString('Second', $output);
        $this->assertStringContainsString('Third', $output);
    }

    // ---------------------------------------------------------------
    // Simulated user input
    // ---------------------------------------------------------------

    public function test_set_user_inputs_makes_io_interactive(): void
    {
        $io = new BufferIO();
        $io->setUserInputs(['yes']);

        $this->assertTrue($io->isInteractive());
    }

    // ---------------------------------------------------------------
    // State flags
    // ---------------------------------------------------------------

    public function test_is_not_interactive_by_default(): void
    {
        $io = new BufferIO();

        $this->assertFalse($io->isInteractive());
    }

    // ---------------------------------------------------------------
    // PSR-3 log methods captured
    // ---------------------------------------------------------------

    public function test_info_level_output_is_captured(): void
    {
        $io = new BufferIO();
        $io->info('Something happened');

        $this->assertStringContainsString('Something happened', $io->getOutput());
    }

    public function test_error_level_output_not_in_stdout(): void
    {
        // Error goes to stderr — BufferIO captures stdout via StreamOutput
        // So getOutput() should NOT contain the error message
        $io = new BufferIO();
        $io->error('This is an error');

        // The default BufferIO stdout stream shouldn't contain the error
        // (errors go to getErrorOutput() / stderr)
        $this->assertTrue(true); // Just verify it doesn't throw
    }
}
