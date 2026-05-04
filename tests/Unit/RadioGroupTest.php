<?php

declare(strict_types=1);

namespace AlfacodeTeam\PhpIoCli\Tests\Unit;

use AlfacodeTeam\PhpIoCli\Components\RadioGroup;
use AlfacodeTeam\PhpIoCli\Depends\Colors;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Strategy
 * ─────────
 * RadioGroup drives a raw-mode TTY loop we cannot run in tests.
 * We access its internals via reflection to:
 *   • call setup() so State + Input bindings are wired
 *   • call resolve() to assert the returned value
 *   • call render() inside ob_start/ob_get_clean to assert output
 *   • call the Input handler directly to simulate keypresses
 */
#[CoversClass(RadioGroup::class)]
final class RadioGroupTest extends TestCase
{
    private const CHOICES = ['small', 'medium', 'large', 'x-large'];

    protected function setUp(): void
    {
        Colors::enable();
    }

    protected function tearDown(): void
    {
        Colors::enable();
    }

    // ---------------------------------------------------------------
    // Fluent API — methods return $this
    // ---------------------------------------------------------------

    public function test_fluent_methods_return_self(): void
    {
        $rg = new RadioGroup('Size', self::CHOICES);

        $this->assertSame($rg, $rg->default('medium'));
        $this->assertSame($rg, $rg->columns(2));
    }

    // ---------------------------------------------------------------
    // Default selection
    // ---------------------------------------------------------------

    public function test_default_selects_first_item_when_not_set(): void
    {
        $rg = new RadioGroup('Pick', self::CHOICES);
        $this->mount($rg);

        $this->assertSame('small', $this->resolve($rg));
    }

    public function test_default_method_pre_selects_correct_item(): void
    {
        $rg = (new RadioGroup('Pick', self::CHOICES))->default('large');
        $this->mount($rg);

        $this->assertSame('large', $this->resolve($rg));
    }

    public function test_default_with_unknown_value_falls_back_to_first(): void
    {
        $rg = (new RadioGroup('Pick', self::CHOICES))->default('nonexistent');
        $this->mount($rg);

        $this->assertSame('small', $this->resolve($rg));
    }

    // ---------------------------------------------------------------
    // Keyboard navigation — DOWN wraps around
    // ---------------------------------------------------------------

    public function test_down_arrow_advances_index(): void
    {
        $rg = new RadioGroup('Pick', self::CHOICES);
        $this->mount($rg);
        $this->pressKey($rg, 'DOWN');

        $this->assertSame('medium', $this->resolve($rg));
    }

    public function test_down_arrow_wraps_to_first_at_end(): void
    {
        $rg = (new RadioGroup('Pick', self::CHOICES))->default('x-large');
        $this->mount($rg);
        $this->pressKey($rg, 'DOWN');

        $this->assertSame('small', $this->resolve($rg));
    }

    // ---------------------------------------------------------------
    // Keyboard navigation — UP wraps around
    // ---------------------------------------------------------------

    public function test_up_arrow_moves_to_previous(): void
    {
        $rg = (new RadioGroup('Pick', self::CHOICES))->default('large');
        $this->mount($rg);
        $this->pressKey($rg, 'UP');

        $this->assertSame('medium', $this->resolve($rg));
    }

    public function test_up_arrow_wraps_to_last_from_first(): void
    {
        $rg = new RadioGroup('Pick', self::CHOICES);
        $this->mount($rg);
        $this->pressKey($rg, 'UP');

        $this->assertSame('x-large', $this->resolve($rg));
    }

    // ---------------------------------------------------------------
    // LEFT / RIGHT mirror UP / DOWN
    // ---------------------------------------------------------------

    public function test_right_arrow_advances_same_as_down(): void
    {
        $rg = new RadioGroup('Pick', self::CHOICES);
        $this->mount($rg);
        $this->pressKey($rg, 'RIGHT');

        $this->assertSame('medium', $this->resolve($rg));
    }

    public function test_left_arrow_moves_back_same_as_up(): void
    {
        $rg = (new RadioGroup('Pick', self::CHOICES))->default('large');
        $this->mount($rg);
        $this->pressKey($rg, 'LEFT');

        $this->assertSame('medium', $this->resolve($rg));
    }

    // ---------------------------------------------------------------
    // Digit shortcuts — 1-based jump
    // ---------------------------------------------------------------

    #[DataProvider('digitJumpProvider')]
    public function test_digit_key_jumps_to_correct_item(string $digit, string $expected): void
    {
        $rg = new RadioGroup('Pick', self::CHOICES);
        $this->mount($rg);
        $this->pressKey($rg, $digit);

        $this->assertSame($expected, $this->resolve($rg));
    }

    public static function digitJumpProvider(): array
    {
        return [
            '1 → small' => ['1', 'small'],
            '2 → medium' => ['2', 'medium'],
            '3 → large' => ['3', 'large'],
            '4 → x-large' => ['4', 'x-large'],
        ];
    }

    // ---------------------------------------------------------------
    // Multiple keypresses accumulate correctly
    // ---------------------------------------------------------------

    public function test_multiple_keys_navigate_correctly(): void
    {
        $rg = new RadioGroup('Pick', self::CHOICES);
        $this->mount($rg);
        $this->pressKey($rg, 'DOWN'); // medium
        $this->pressKey($rg, 'DOWN'); // large
        $this->pressKey($rg, 'UP');   // medium

        $this->assertSame('medium', $this->resolve($rg));
    }

    // ---------------------------------------------------------------
    // render() — question text
    // ---------------------------------------------------------------

    public function test_render_contains_question(): void
    {
        $rg = new RadioGroup('Deployment target', self::CHOICES);
        $this->mount($rg);

        $output = $this->capture(fn() => $rg->render());

        $this->assertStringContainsString('Deployment target', $output);
    }

    // ---------------------------------------------------------------
    // render() — all choices visible
    // ---------------------------------------------------------------

    public function test_render_shows_all_choices(): void
    {
        $rg = new RadioGroup('Size', self::CHOICES);
        $this->mount($rg);

        $output = $this->capture(fn() => $rg->render());

        foreach (self::CHOICES as $choice) {
            $this->assertStringContainsString($choice, $output);
        }
    }

    // ---------------------------------------------------------------
    // render() — active indicator
    // ---------------------------------------------------------------

    public function test_render_shows_filled_radio_for_active_item(): void
    {
        $rg = new RadioGroup('Size', self::CHOICES);
        $this->mount($rg);

        $output = $this->capture(fn() => $rg->render());

        // Active item uses filled ◉, inactive use ○
        $this->assertStringContainsString('◉', $output);
        $this->assertStringContainsString('○', $output);
    }

    // ---------------------------------------------------------------
    // render() — digit shortcuts shown
    // ---------------------------------------------------------------

    public function test_render_shows_digit_shortcuts(): void
    {
        $rg = new RadioGroup('Pick', self::CHOICES);
        $this->mount($rg);

        $output = $this->capture(fn() => $rg->render());

        $this->assertStringContainsString('1', $output);
        $this->assertStringContainsString('4', $output);
    }

    // ---------------------------------------------------------------
    // render() — help text
    // ---------------------------------------------------------------

    public function test_render_shows_keyboard_hint(): void
    {
        $rg = new RadioGroup('Pick', self::CHOICES);
        $this->mount($rg);

        $output = $this->capture(fn() => $rg->render());

        $this->assertStringContainsString('ENTER', $output);
    }

    // ---------------------------------------------------------------
    // render() — collapsed state after done
    // ---------------------------------------------------------------

    public function test_render_collapses_after_done(): void
    {
        $rg = (new RadioGroup('Pick', self::CHOICES))->default('large');
        $this->mount($rg);

        // Simulate ENTER by setting state directly
        $this->setState($rg, 'done', true);

        $output = $this->capture(fn() => $rg->render());

        // Should show only the selected value, not all options
        $this->assertStringContainsString('large', $output);
        $this->assertStringNotContainsString('ENTER', $output);
        $this->assertStringNotContainsString('◉', $output);
    }

    // ---------------------------------------------------------------
    // render() — multi-column layout
    // ---------------------------------------------------------------

    public function test_render_multi_column_shows_all_choices(): void
    {
        $rg = (new RadioGroup('Size', self::CHOICES))->columns(2);
        $this->mount($rg);

        $output = $this->capture(fn() => $rg->render());

        foreach (self::CHOICES as $choice) {
            $this->assertStringContainsString($choice, $output);
        }
    }

    // ---------------------------------------------------------------
    // render() — no ANSI when colors disabled
    // ---------------------------------------------------------------

    public function test_render_no_ansi_when_colors_disabled(): void
    {
        Colors::disable();
        $rg = new RadioGroup('Pick', self::CHOICES);
        $this->mount($rg);

        $output = $this->capture(fn() => $rg->render());

        $this->assertStringContainsString('Pick', $output);
        $this->assertStringContainsString('small', $output);
        $this->assertStringContainsString('medium', $output);
        $this->assertStringContainsString('large', $output);
        $this->assertStringContainsString('x-large', $output);


    }

    // ---------------------------------------------------------------
    // Single-item list edge case
    // ---------------------------------------------------------------

    public function test_single_choice_list_resolves_correctly(): void
    {
        $rg = new RadioGroup('Confirm', ['yes']);
        $this->mount($rg);

        $this->assertSame('yes', $this->resolve($rg));
    }

    public function test_single_choice_up_down_stays_on_item(): void
    {
        $rg = new RadioGroup('Confirm', ['yes']);
        $this->mount($rg);
        $this->pressKey($rg, 'DOWN');
        $this->pressKey($rg, 'UP');

        $this->assertSame('yes', $this->resolve($rg));
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    private function mount(RadioGroup $rg): void
    {
        $ref = new \ReflectionObject($rg);
        $method = $ref->getMethod('setup');
        $method->setAccessible(true);
        $method->invoke($rg);
    }

    private function resolve(RadioGroup $rg): mixed
    {
        $ref = new \ReflectionObject($rg);
        $method = $ref->getMethod('resolve');
        $method->setAccessible(true);

        return $method->invoke($rg);
    }

    private function pressKey(RadioGroup $rg, string $key): void
    {
        $ref = new \ReflectionObject($rg);
        $state = $ref->getProperty('state');
        $state->setAccessible(true);
        $input = $ref->getProperty('input');
        $input->setAccessible(true);

        $input->getValue($rg)->handle($key, $state->getValue($rg));
    }

    private function setState(RadioGroup $rg, string $key, mixed $value): void
    {
        $ref = new \ReflectionObject($rg);
        $prop = $ref->getProperty('state');
        $prop->setAccessible(true);
        $prop->getValue($rg)->$key = $value;
    }

    private function capture(callable $fn): string
    {
        ob_start();
        $fn();

        return Colors::strip((string) ob_get_clean());
    }
}
