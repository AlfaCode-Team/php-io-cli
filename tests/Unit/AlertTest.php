<?php

declare(strict_types=1);

namespace AlfacodeTeam\PhpIoCli\Tests\Unit;

use AlfacodeTeam\PhpIoCli\Components\Alert;
use AlfacodeTeam\PhpIoCli\Depends\Colors;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Alert::class)]
final class AlertTest extends TestCase
{
    protected function setUp(): void
    {
        Colors::enable();
    }

    protected function tearDown(): void
    {
        Colors::enable();
    }

    // ---------------------------------------------------------------
    // success
    // ---------------------------------------------------------------

    public function test_success_contains_title(): void
    {
        $output = $this->capture(static fn() => Alert::success('Deployment complete!'));

        $this->assertStringContainsString('Deployment complete!', $output);
    }

    public function test_success_contains_checkmark_icon(): void
    {
        $output = $this->capture(static fn() => Alert::success('Done'));

        $this->assertStringContainsString('✔', $output);
    }

    public function test_success_renders_body_lines(): void
    {
        $output = $this->capture(
            static fn() => Alert::success('Deployed!', ['Version: 2.4.1', 'Region: eu-west-1']),
        );

        $this->assertStringContainsString('Version: 2.4.1', $output);
        $this->assertStringContainsString('Region: eu-west-1', $output);
    }

    public function test_success_renders_unicode_box_borders(): void
    {
        $output = $this->capture(static fn() => Alert::success('OK'));

        // The alert draws a box with at least one of these border chars
        $hasBorder = str_contains($output, '┌') || str_contains($output, '─') || str_contains($output, '└');
        $this->assertTrue($hasBorder, 'Expected Unicode box border characters in output');
    }

    // ---------------------------------------------------------------
    // error
    // ---------------------------------------------------------------

    public function test_error_contains_title(): void
    {
        $output = $this->capture(static fn() => Alert::error('Build failed'));

        $this->assertStringContainsString('Build failed', $output);
    }

    public function test_error_contains_x_icon(): void
    {
        $output = $this->capture(static fn() => Alert::error('Build failed'));

        $this->assertStringContainsString('✘', $output);
    }

    public function test_error_renders_body(): void
    {
        $output = $this->capture(
            static fn() => Alert::error('Build failed', ['Exit code: 1', 'Check logs']),
        );

        $this->assertStringContainsString('Exit code: 1', $output);
        $this->assertStringContainsString('Check logs', $output);
    }

    // ---------------------------------------------------------------
    // warning
    // ---------------------------------------------------------------

    public function test_warning_contains_title(): void
    {
        $output = $this->capture(static fn() => Alert::warning('API quota at 80%'));

        $this->assertStringContainsString('API quota at 80%', $output);
    }

    public function test_warning_contains_exclamation_icon(): void
    {
        $output = $this->capture(static fn() => Alert::warning('Watch out'));

        $this->assertStringContainsString('!', $output);
    }

    public function test_warning_renders_body(): void
    {
        $output = $this->capture(
            static fn() => Alert::warning('Low memory', ['Used: 95%', 'Free: 200MB']),
        );

        $this->assertStringContainsString('Used: 95%', $output);
        $this->assertStringContainsString('Free: 200MB', $output);
    }

    // ---------------------------------------------------------------
    // info
    // ---------------------------------------------------------------

    public function test_info_contains_title(): void
    {
        $output = $this->capture(static fn() => Alert::info('New version available: 3.0.0'));

        $this->assertStringContainsString('New version available: 3.0.0', $output);
    }

    public function test_info_contains_i_icon(): void
    {
        $output = $this->capture(static fn() => Alert::info('Note'));

        $this->assertStringContainsString('i', $output);
    }

    public function test_info_renders_body(): void
    {
        $output = $this->capture(
            static fn() => Alert::info('Heads up', ['Maintenance tonight 02:00 UTC']),
        );

        $this->assertStringContainsString('Maintenance tonight 02:00 UTC', $output);
    }

    // ---------------------------------------------------------------
    // String body (not array)
    // ---------------------------------------------------------------

    public function test_body_as_string_renders_correctly(): void
    {
        $output = $this->capture(
            static fn() => Alert::success('Done', 'Single line body'),
        );

        $this->assertStringContainsString('Single line body', $output);
    }

    // ---------------------------------------------------------------
    // Empty body
    // ---------------------------------------------------------------

    public function test_empty_body_renders_without_separator(): void
    {
        $output = $this->capture(static fn() => Alert::success('Title only'));

        $this->assertStringContainsString('Title only', $output);
        // No body separator (├) should appear when body is empty
        $this->assertStringNotContainsString('├', $output);
    }

    // ---------------------------------------------------------------
    // block()
    // ---------------------------------------------------------------

    public function test_block_contains_uppercased_title(): void
    {
        $output = $this->capture(static fn() => Alert::block('critical error'));

        $this->assertStringContainsString('CRITICAL ERROR', $output);
    }

    public function test_block_renders_body_lines(): void
    {
        $output = $this->capture(
            static fn() => Alert::block('Fatal', ['Check /var/log/app.log']),
        );

        $this->assertStringContainsString('Check /var/log/app.log', $output);
    }

    // ---------------------------------------------------------------
    // ANSI-safe width: long body lines don't crash
    // ---------------------------------------------------------------

    public function test_long_body_line_renders_without_error(): void
    {
        $longLine = str_repeat('x', 120);

        $output = $this->capture(static fn() => Alert::info('Wide box', [$longLine]));

        $this->assertStringContainsString($longLine, $output);
    }

    // ---------------------------------------------------------------
    // ANSI codes in body cells don't corrupt borders
    // ---------------------------------------------------------------

    public function test_ansi_colored_body_line_is_included(): void
    {
        $colored = Colors::wrap('healthy', Colors::GREEN);

        $output = $this->capture(static fn() => Alert::success('Status', [$colored]));

        $this->assertStringContainsString('healthy', Colors::strip($output));
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    private function capture(callable $fn): string
    {
        ob_start();
        $fn();

        return Colors::strip((string) ob_get_clean());
    }
}
