<?php

declare(strict_types=1);

namespace AlfacodeTeam\PhpIoCli\Tests\Unit;

use AlfacodeTeam\PhpIoCli\Components\SliderInput;
use AlfacodeTeam\PhpIoCli\Depends\Colors;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Strategy
 * ─────────
 * SliderInput is an interactive component that drives a raw-mode TTY loop.
 * We cannot run the full reactive loop in unit tests, so we test the
 * behaviours that are accessible without a terminal:
 *
 *   • Fluent configuration (chaining returns $this, values are stored)
 *   • resolve() returns the correct type and respects integer mode
 *   • The rendered output contains the expected text elements
 *
 * render() writes directly to stdout, so we wrap those calls in
 * ob_start() / ob_get_clean() and strip ANSI before asserting.
 */
#[CoversClass(SliderInput::class)]
final class SliderInputTest extends TestCase
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
    // Construction & fluent API
    // ---------------------------------------------------------------

    public function test_fluent_methods_return_self(): void
    {
        $slider = new SliderInput('Volume', 0, 100);

        $this->assertSame($slider, $slider->min(0));
        $this->assertSame($slider, $slider->max(100));
        $this->assertSame($slider, $slider->step(5));
        $this->assertSame($slider, $slider->default(50));
        $this->assertSame($slider, $slider->integer());
        $this->assertSame($slider, $slider->width(40));
    }

    // ---------------------------------------------------------------
    // resolve() — float mode (default)
    // ---------------------------------------------------------------

    public function test_resolve_returns_float_by_default(): void
    {
        $slider = new SliderInput('Rate', 0.0, 1.0);
        $slider->step(0.1)->default(0.5);

        // Mount the component so state is initialised (setup() wires bindings)
        $this->mountWithoutLoop($slider);

        $result = $this->callResolve($slider);

        $this->assertIsFloat($result);
        $this->assertEqualsWithDelta(0.5, $result, 0.001);
    }

    // ---------------------------------------------------------------
    // resolve() — integer mode
    // ---------------------------------------------------------------

    public function test_resolve_returns_int_in_integer_mode(): void
    {
        $slider = new SliderInput('Port', 1000, 9999);
        $slider->step(1)->default(8080)->integer();

        $this->mountWithoutLoop($slider);

        $result = $this->callResolve($slider);

        $this->assertIsInt($result);
        $this->assertSame(8080, $result);
    }

    // ---------------------------------------------------------------
    // resolve() — clamps to min / max
    // ---------------------------------------------------------------

    public function test_resolve_clamps_value_within_range(): void
    {
        $slider = new SliderInput('Speed', 0, 10);
        $slider->step(1)->default(5);

        $this->mountWithoutLoop($slider);

        $result = $this->callResolve($slider);

        $this->assertGreaterThanOrEqual(0, $result);
        $this->assertLessThanOrEqual(10, $result);
    }

    // ---------------------------------------------------------------
    // resolve() — snap to step
    // ---------------------------------------------------------------

    public function test_resolve_snaps_float_value_to_nearest_step(): void
    {
        // step = 0.25; default = 0.3 → nearest is 0.25
        $slider = new SliderInput('Alpha', 0.0, 1.0);
        $slider->step(0.25)->default(0.3);

        $this->mountWithoutLoop($slider);

        $result = $this->callResolve($slider);

        $this->assertEqualsWithDelta(0.25, $result, 0.001);
    }

    // ---------------------------------------------------------------
    // render() — question appears in output
    // ---------------------------------------------------------------

    public function test_render_contains_question_text(): void
    {
        $slider = new SliderInput('Master volume', 0, 100);
        $slider->step(1)->default(50);
        $this->mountWithoutLoop($slider);

        $output = $this->capture(fn() => $slider->render());

        $this->assertStringContainsString('Master volume', $output);
    }

    // ---------------------------------------------------------------
    // render() — current value appears in output
    // ---------------------------------------------------------------

    public function test_render_shows_current_value(): void
    {
        $slider = new SliderInput('Brightness', 0, 100);
        $slider->step(1)->default(75)->integer();
        $this->mountWithoutLoop($slider);

        $output = $this->capture(fn() => $slider->render());

        $this->assertStringContainsString('75', $output);
    }

    // ---------------------------------------------------------------
    // render() — bar characters present
    // ---------------------------------------------------------------

    public function test_render_contains_bar_brackets(): void
    {
        $slider = new SliderInput('Level', 0, 10);
        $slider->step(1)->default(5);
        $this->mountWithoutLoop($slider);

        $output = $this->capture(fn() => $slider->render());

        $this->assertStringContainsString('[', $output);
        $this->assertStringContainsString(']', $output);
    }

    // ---------------------------------------------------------------
    // render() — range hints (min and max values)
    // ---------------------------------------------------------------

    public function test_render_shows_min_and_max_hints(): void
    {
        $slider = new SliderInput('Timeout', 1, 60);
        $slider->step(1)->default(30)->integer();
        $this->mountWithoutLoop($slider);

        $output = $this->capture(fn() => $slider->render());

        $this->assertStringContainsString('1', $output);
        $this->assertStringContainsString('60', $output);
    }

    // ---------------------------------------------------------------
    // render() — help text
    // ---------------------------------------------------------------

    public function test_render_contains_keyboard_hint(): void
    {
        $slider = new SliderInput('Value', 0, 100);
        $slider->step(1)->default(50);
        $this->mountWithoutLoop($slider);

        $output = $this->capture(fn() => $slider->render());

        $this->assertStringContainsString('ENTER', $output);
    }

    // ---------------------------------------------------------------
    // render() — ANSI disabled → plain output
    // ---------------------------------------------------------------

    public function test_render_works_with_colors_disabled(): void
    {
        Colors::disable();
        $slider = new SliderInput('Gain', 0, 10);
        $slider->step(1)->default(5);
        $this->mountWithoutLoop($slider);

        $output = $this->capture(fn() => $slider->render());

        // No ANSI escape sequences should appear
        $this->assertStringNotContainsString("\033[", $output);
        $this->assertStringContainsString('Gain', $output);
    }

    // ---------------------------------------------------------------
    // render() — thumb marker present (●)
    // ---------------------------------------------------------------

    public function test_render_contains_thumb_marker(): void
    {
        $slider = new SliderInput('Pan', -50, 50);
        $slider->step(1)->default(0)->integer();
        $this->mountWithoutLoop($slider);

        $output = $this->capture(fn() => $slider->render());

        // The slider thumb is rendered as ●
        $this->assertStringContainsString('●', $output);
    }

    // ---------------------------------------------------------------
    // Decimal formatting mirrors step precision
    // ---------------------------------------------------------------

    public function test_render_formats_value_with_correct_decimal_places(): void
    {
        $slider = new SliderInput('Rate', 0.0, 1.0);
        $slider->step(0.01)->default(0.75);
        $this->mountWithoutLoop($slider);

        $output = $this->capture(fn() => $slider->render());

        $this->assertStringContainsString('0.75', $output);
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    /**
     * Calls the protected setup() via mount() so state and bindings are ready,
     * without launching the blocking run() loop.
     */
    private function mountWithoutLoop(SliderInput $slider): void
    {
        $ref = new \ReflectionObject($slider);
        $method = $ref->getMethod('setup');
        $method->setAccessible(true);
        $method->invoke($slider);
    }

    /**
     * Calls the protected resolve() via reflection.
     */
    private function callResolve(SliderInput $slider): mixed
    {
        $ref = new \ReflectionObject($slider);
        $method = $ref->getMethod('resolve');
        $method->setAccessible(true);

        return $method->invoke($slider);
    }

    /**
     * Captures stdout output from a callable and strips ANSI codes.
     */
    private function capture(callable $fn): string
    {
        ob_start();
        $fn();

        return Colors::strip((string) ob_get_clean());
    }
}
