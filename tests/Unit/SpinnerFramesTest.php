<?php

declare(strict_types=1);

namespace AlfacodeTeam\PhpIoCli\Tests\Unit;

use AlfacodeTeam\PhpIoCli\Depends\SpinnerFrames;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(SpinnerFrames::class)]
final class SpinnerFramesTest extends TestCase
{
    // ---------------------------------------------------------------
    // All named frame sets return non-empty arrays
    // ---------------------------------------------------------------

    #[DataProvider('namedStyleProvider')]
    public function test_get_returns_non_empty_array_for_named_style(string $style): void
    {
        $frames = SpinnerFrames::get($style);

        $this->assertIsArray($frames);
        $this->assertNotEmpty($frames, "Frame set '{$style}' must not be empty");
    }

    public static function namedStyleProvider(): array
    {
        return [
            'dots' => ['dots'],
            'line' => ['line'],
            'bars' => ['bars'],
            'pulse' => ['pulse'],
            'arc' => ['arc'],
            'bounce' => ['bounce'],
        ];
    }

    // ---------------------------------------------------------------
    // Unknown style falls back to dots (default branch)
    // ---------------------------------------------------------------

    public function test_get_unknown_style_returns_default_dots(): void
    {
        $dots = SpinnerFrames::get('dots');
        $unknown = SpinnerFrames::get('nonexistent-style');

        $this->assertSame($dots, $unknown);
    }

    // ---------------------------------------------------------------
    // default() is identical to get('dots')
    // ---------------------------------------------------------------

    public function test_default_returns_same_as_get_dots(): void
    {
        $this->assertSame(SpinnerFrames::get('dots'), SpinnerFrames::default());
    }

    // ---------------------------------------------------------------
    // Named shortcut methods
    // ---------------------------------------------------------------

    public function test_dots_shortcut_matches_get(): void
    {
        $this->assertSame(SpinnerFrames::get('dots'), SpinnerFrames::dots());
    }

    public function test_bars_shortcut_matches_get(): void
    {
        $this->assertSame(SpinnerFrames::get('bars'), SpinnerFrames::bars());
    }

    public function test_line_shortcut_matches_get(): void
    {
        $this->assertSame(SpinnerFrames::get('line'), SpinnerFrames::line());
    }

    public function test_pulse_shortcut_matches_get(): void
    {
        $this->assertSame(SpinnerFrames::get('pulse'), SpinnerFrames::pulse());
    }

    // ---------------------------------------------------------------
    // Frame content sanity checks
    // ---------------------------------------------------------------

    public function test_every_frame_is_a_non_empty_string(): void
    {
        $styles = ['dots', 'line', 'bars', 'pulse', 'arc', 'bounce'];

        foreach ($styles as $style) {
            foreach (SpinnerFrames::get($style) as $i => $frame) {
                $this->assertIsString($frame, "{$style}[{$i}] must be a string");
                $this->assertNotSame('', $frame, "{$style}[{$i}] must not be an empty string");
            }
        }
    }

    public function test_line_style_contains_exactly_four_frames(): void
    {
        // The classic line spinner: - \ | /
        $this->assertCount(4, SpinnerFrames::line());
    }

    public function test_dots_style_contains_ten_frames(): void
    {
        // Braille dots: ⠋ ⠙ ⠹ ⠸ ⠼ ⠴ ⠦ ⠧ ⠇ ⠏
        $this->assertCount(10, SpinnerFrames::dots());
    }

    public function test_bounce_style_has_more_frames_than_dots(): void
    {
        // Bounce has a wider animation loop
        $this->assertGreaterThan(
            count(SpinnerFrames::dots()),
            count(SpinnerFrames::get('bounce')),
        );
    }
}
