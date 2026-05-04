<?php

declare(strict_types=1);

namespace AlfacodeTeam\PhpIoCli\Tests\Unit;

use AlfacodeTeam\PhpIoCli\Depends\ArrayKeyReader;
use AlfacodeTeam\PhpIoCli\Depends\TerminalKeyReader;
use AlfacodeTeam\PhpIoCli\KeyReaderInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ArrayKeyReader::class)]
#[CoversClass(TerminalKeyReader::class)]
final class KeyReaderTest extends TestCase
{
    // ---------------------------------------------------------------
    // Interface contract — TerminalKeyReader
    // ---------------------------------------------------------------

    public function test_terminal_key_reader_implements_interface(): void
    {
        $this->assertInstanceOf(KeyReaderInterface::class, new TerminalKeyReader());
    }

    public function test_terminal_key_reader_setup_and_teardown_do_not_throw(): void
    {
        // We cannot call setUp() in a test (would block on stty),
        // but tearDown() must always be safe to call even without setUp().
        $reader = new TerminalKeyReader();
        $reader->tearDown(); // idempotent — must not throw
        $this->assertTrue(true);
    }

    // ---------------------------------------------------------------
    // ArrayKeyReader — basic replay
    // ---------------------------------------------------------------

    public function test_array_reader_implements_interface(): void
    {
        $this->assertInstanceOf(KeyReaderInterface::class, new ArrayKeyReader([]));
    }

    public function test_array_reader_returns_keys_in_order(): void
    {
        $reader = new ArrayKeyReader(['UP', 'DOWN', 'ENTER']);

        $this->assertSame('UP', $reader->readKey());
        $this->assertSame('DOWN', $reader->readKey());
        $this->assertSame('ENTER', $reader->readKey());
    }

    public function test_array_reader_returns_empty_string_when_exhausted(): void
    {
        $reader = new ArrayKeyReader(['a']);
        $reader->readKey(); // consume the only key

        $this->assertSame('', $reader->readKey());
        $this->assertSame('', $reader->readKey()); // subsequent calls also empty
    }

    public function test_array_reader_exhausted_returns_false_initially(): void
    {
        $reader = new ArrayKeyReader(['x', 'y']);

        $this->assertFalse($reader->exhausted());
    }

    public function test_array_reader_exhausted_returns_true_after_all_keys_consumed(): void
    {
        $reader = new ArrayKeyReader(['x']);
        $reader->readKey();

        $this->assertTrue($reader->exhausted());
    }

    public function test_array_reader_empty_queue_is_immediately_exhausted(): void
    {
        $reader = new ArrayKeyReader([]);

        $this->assertTrue($reader->exhausted());
        $this->assertSame('', $reader->readKey());
    }

    // ---------------------------------------------------------------
    // ArrayKeyReader — reset
    // ---------------------------------------------------------------

    public function test_array_reader_reset_replays_sequence_from_start(): void
    {
        $reader = new ArrayKeyReader(['A', 'B']);

        $this->assertSame('A', $reader->readKey());
        $this->assertSame('B', $reader->readKey());

        $reader->reset();

        $this->assertSame('A', $reader->readKey());
        $this->assertSame('B', $reader->readKey());
    }

    public function test_array_reader_reset_clears_exhausted_state(): void
    {
        $reader = new ArrayKeyReader(['Z']);
        $reader->readKey();
        $this->assertTrue($reader->exhausted());

        $reader->reset();
        $this->assertFalse($reader->exhausted());
    }

    // ---------------------------------------------------------------
    // ArrayKeyReader — setUp / tearDown are no-ops
    // ---------------------------------------------------------------

    public function test_array_reader_setup_does_not_throw(): void
    {
        $reader = new ArrayKeyReader(['ENTER']);
        $reader->setUp();
        $this->assertTrue(true);
    }

    public function test_array_reader_teardown_does_not_throw(): void
    {
        $reader = new ArrayKeyReader(['ENTER']);
        $reader->tearDown();
        $reader->tearDown(); // idempotent
        $this->assertTrue(true);
    }

    // ---------------------------------------------------------------
    // ArrayKeyReader — raw escape bytes also work
    // ---------------------------------------------------------------

    public function test_array_reader_accepts_raw_escape_sequences(): void
    {
        $reader = new ArrayKeyReader(["\e[A", "\e[B", "\n"]);

        $this->assertSame("\e[A", $reader->readKey());
        $this->assertSame("\e[B", $reader->readKey());
        $this->assertSame("\n", $reader->readKey());
    }

    // ---------------------------------------------------------------
    // AbstractPrompt integration — withKeyReader() / getKeyReader()
    // ---------------------------------------------------------------

    public function test_with_key_reader_injects_custom_reader(): void
    {
        $component = $this->makeMinimalComponent();
        $reader    = new ArrayKeyReader([]);

        $component->withKeyReader($reader);

        $this->assertSame($reader, $component->getKeyReader());
    }

    public function test_with_key_reader_returns_same_instance_for_chaining(): void
    {
        $component = $this->makeMinimalComponent();
        $reader    = new ArrayKeyReader([]);

        $returned = $component->withKeyReader($reader);

        $this->assertSame($component, $returned);
    }

    public function test_default_key_reader_is_terminal_key_reader(): void
    {
        $component = $this->makeMinimalComponent();

        $this->assertInstanceOf(TerminalKeyReader::class, $component->getKeyReader());
    }

    // ---------------------------------------------------------------
    // Full run() integration — ArrayKeyReader drives the loop
    // ---------------------------------------------------------------

    public function test_run_resolves_confirm_via_array_reader(): void
    {
        // Simulate: user presses RIGHT (toggle to No), then ENTER
        $reader = new ArrayKeyReader(['RIGHT', 'ENTER']);

        ob_start();
        $result = (new \AlfacodeTeam\PhpIoCli\Components\Confirm('Continue?', default: true))
            ->withKeyReader($reader)
            ->run();
        ob_end_clean();

        $this->assertFalse($result); // toggled from true → false
    }

    public function test_run_resolves_select_via_array_reader(): void
    {
        $choices = ['alpha', 'beta', 'gamma'];
        // Navigate down twice to land on 'gamma', then ENTER
        $reader  = new ArrayKeyReader(['DOWN', 'DOWN', 'ENTER']);

        ob_start();
        $result = (new \AlfacodeTeam\PhpIoCli\Components\Select('Pick', $choices))
            ->withKeyReader($reader)
            ->run();
        ob_end_clean();

        $this->assertSame('gamma', $result);
    }

    public function test_run_resolves_radio_group_via_array_reader(): void
    {
        $choices = ['xs', 'sm', 'md', 'lg'];
        // Press digit '3' to jump to 'md', then ENTER
        $reader  = new ArrayKeyReader(['3', 'ENTER']);

        ob_start();
        $result = (new \AlfacodeTeam\PhpIoCli\Components\RadioGroup('Size', $choices))
            ->withKeyReader($reader)
            ->run();
        ob_end_clean();

        $this->assertSame('md', $result);
    }

    public function test_exhausted_reader_stops_loop_and_returns_default(): void
    {
        // No keys at all — loop exits immediately, resolve() returns default
        $reader = new ArrayKeyReader([]);

        ob_start();
        $result = (new \AlfacodeTeam\PhpIoCli\Components\Confirm('Go?', default: true))
            ->withKeyReader($reader)
            ->run();
        ob_end_clean();

        // No ENTER was pressed, so state stays at default (true)
        $this->assertTrue($result);
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    /**
     * Returns a concrete minimal AbstractPrompt subclass for testing the
     * injection API without depending on any specific component.
     */
    private function makeMinimalComponent(): \AlfacodeTeam\PhpIoCli\Components\Confirm
    {
        return new \AlfacodeTeam\PhpIoCli\Components\Confirm('Test?');
    }
}
