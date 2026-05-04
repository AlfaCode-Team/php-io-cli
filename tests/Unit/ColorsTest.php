<?php

declare(strict_types=1);

namespace AlfacodeTeam\PhpIoCli\Tests\Unit;

use AlfacodeTeam\PhpIoCli\Depends\Colors;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Colors::class)]
final class ColorsTest extends TestCase
{
    protected function setUp(): void
    {
        // Force colors ON so output is predictable in all environments
        Colors::enable();
    }

    protected function tearDown(): void
    {
        // Reset to auto-detect after each test
        Colors::enable();
    }

    // ---------------------------------------------------------------
    // wrap
    // ---------------------------------------------------------------

    public function test_wrap_with_single_style(): void
    {
        $result = Colors::wrap('hello', Colors::GREEN);

        $this->assertStringContainsString('hello', $result);
        $this->assertStringContainsString(Colors::GREEN, $result);
        $this->assertStringContainsString(Colors::RESET, $result);
    }

    public function test_wrap_with_multiple_styles(): void
    {
        $result = Colors::wrap('hi', [Colors::BOLD, Colors::RED]);

        $this->assertStringContainsString(Colors::BOLD, $result);
        $this->assertStringContainsString(Colors::RED, $result);
        $this->assertStringContainsString('hi', $result);
    }

    public function test_wrap_returns_plain_text_when_disabled(): void
    {
        Colors::disable();
        $result = Colors::wrap('plain', Colors::CYAN);

        $this->assertSame('plain', $result);
    }

    // ---------------------------------------------------------------
    // Semantic helpers
    // ---------------------------------------------------------------

    public function test_success_contains_checkmark_and_text(): void
    {
        $result = Colors::strip(Colors::success('Done'));

        $this->assertStringContainsString('✔', $result);
        $this->assertStringContainsString('Done', $result);
    }

    public function test_error_contains_x_and_text(): void
    {
        $result = Colors::strip(Colors::error('Failed'));

        $this->assertStringContainsString('✘', $result);
        $this->assertStringContainsString('Failed', $result);
    }

    public function test_warning_contains_exclamation_and_text(): void
    {
        $result = Colors::strip(Colors::warning('Caution'));

        $this->assertStringContainsString('!', $result);
        $this->assertStringContainsString('Caution', $result);
    }

    public function test_info_returns_wrapped_text(): void
    {
        $result = Colors::strip(Colors::info('Note'));

        $this->assertStringContainsString('Note', $result);
    }

    public function test_muted_returns_wrapped_text(): void
    {
        $result = Colors::strip(Colors::muted('Quiet'));

        $this->assertStringContainsString('Quiet', $result);
    }

    // ---------------------------------------------------------------
    // strip
    // ---------------------------------------------------------------

    public function test_strip_removes_ansi_color_codes(): void
    {
        $input = "\033[32mGreen\033[0m";
        $result = Colors::strip($input);

        $this->assertSame('Green', $result);
    }

    public function test_strip_removes_cursor_sequences(): void
    {
        $input = "\033[2K\rSome text";
        $result = Colors::strip($input);

        $this->assertSame('Some text', $result);
    }

    public function test_strip_removes_carriage_returns(): void
    {
        $input = "line1\rline2";
        $result = Colors::strip($input);

        $this->assertSame('line1line2', $result);
    }

    public function test_strip_leaves_plain_text_unchanged(): void
    {
        $input = 'Hello, World!';

        $this->assertSame($input, Colors::strip($input));
    }

    public function test_strip_handles_complex_ansi_string(): void
    {
        $input = Colors::wrap('bold cyan', [Colors::BOLD, Colors::CYAN]);
        $result = Colors::strip($input);

        $this->assertSame('bold cyan', $result);
    }

    // ---------------------------------------------------------------
    // hex
    // ---------------------------------------------------------------

    public function test_hex_produces_truecolor_sequence(): void
    {
        $result = Colors::hex('#ff5733', 'Alert');

        $this->assertStringContainsString('38;2;255;87;51', $result);
        $this->assertStringContainsString('Alert', $result);
    }

    public function test_hex_handles_shorthand_notation(): void
    {
        // #f00 → #ff0000 → rgb(255, 0, 0)
        $result = Colors::hex('#f00', 'Red');

        $this->assertStringContainsString('38;2;255;0;0', $result);
    }

    // ---------------------------------------------------------------
    // enable / disable / isEnabled
    // ---------------------------------------------------------------

    public function test_enable_and_disable_toggle_colors(): void
    {
        Colors::enable();
        $this->assertTrue(Colors::isEnabled());

        Colors::disable();
        $this->assertFalse(Colors::isEnabled());

        // Restore
        Colors::enable();
    }
}
