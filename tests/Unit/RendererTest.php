<?php

declare(strict_types=1);

namespace AlfacodeTeam\PhpIoCli\Tests\Unit;

use AlfacodeTeam\PhpIoCli\Depends\Colors;
use AlfacodeTeam\PhpIoCli\Depends\RenderContext;
use AlfacodeTeam\PhpIoCli\Depends\Renderer;
use AlfacodeTeam\PhpIoCli\Depends\State;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 *
 * Strategy
 * ─────────
 * Renderer writes directly to stdout via `echo`. We wrap every assertion
 * that triggers output in ob_start() / ob_get_clean() so tests remain
 * hermetic and do not pollute the PHPUnit output stream.
 *
 * Terminal cursor-movement sequences (\033[…A, \033[?25l …) are stripped
 * via Colors::strip() before assertions so tests are not brittle against
 * ANSI details.
 */
#[CoversClass(\AlfacodeTeam\PhpIoCli\Depends\Renderer::class)]
final class RendererTest extends TestCase
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
    // Helpers
    // ---------------------------------------------------------------

    private function capture(callable $fn): string
    {
        ob_start();
        $fn();
        return Colors::strip((string) ob_get_clean());
    }

    private function makeState(array $data = []): State
    {
        return new State(array_merge([
            'question' => 'Choose an item',
            'search'   => '',
            'index'    => 0,
            'loading'  => false,
            'items'    => ['Alpha', 'Beta', 'Gamma', 'Delta'],
            'selected' => [],
            'multi'    => false,
        ], $data));
    }

    // ---------------------------------------------------------------
    // key()
    // ---------------------------------------------------------------

    public function test_key_returns_class_name(): void
    {
        $renderer = new Renderer();

        $this->assertSame(Renderer::class, $renderer->key());
    }

    // ---------------------------------------------------------------
    // render() — delegates to beforeRender + paint + afterRender
    // ---------------------------------------------------------------

    public function test_render_outputs_question(): void
    {
        $renderer = new Renderer();
        $state    = $this->makeState(['question' => 'Pick a region']);
        $ctx      = new RenderContext();

        $output = $this->capture(fn() => $renderer->render($state, $ctx));

        $this->assertStringContainsString('Pick a region', $output);
    }

    public function test_render_outputs_list_items(): void
    {
        $renderer = new Renderer();
        $state    = $this->makeState([
            'items' => ['PHP', 'Python', 'Go'],
        ]);
        $ctx = new RenderContext();

        $output = $this->capture(fn() => $renderer->render($state, $ctx));

        $this->assertStringContainsString('PHP', $output);
        $this->assertStringContainsString('Python', $output);
        $this->assertStringContainsString('Go', $output);
    }

    // ---------------------------------------------------------------
    // renderState() — convenience overload
    // ---------------------------------------------------------------

    public function test_render_state_produces_same_structure(): void
    {
        $renderer = new Renderer();
        $state    = $this->makeState(['question' => 'Pick one']);

        $output = $this->capture(fn() => $renderer->renderState($state));

        $this->assertStringContainsString('Pick one', $output);
    }

    // ---------------------------------------------------------------
    // Loading state
    // ---------------------------------------------------------------

    public function test_loading_state_shows_loading_indicator(): void
    {
        $renderer = new Renderer();
        $state    = $this->makeState(['loading' => true]);
        $ctx      = new RenderContext();

        $output = $this->capture(fn() => $renderer->render($state, $ctx));

        $this->assertStringContainsString('Loading', $output);
    }

    public function test_loading_state_hides_item_list(): void
    {
        $renderer = new Renderer();
        $state    = $this->makeState([
            'loading' => true,
            'items'   => ['Alpha', 'Beta'],
        ]);
        $ctx = new RenderContext();

        $output = $this->capture(fn() => $renderer->render($state, $ctx));

        // Items should not appear while loading
        $this->assertStringNotContainsString('Alpha', $output);
    }

    // ---------------------------------------------------------------
    // Empty items — no-match state
    // ---------------------------------------------------------------

    public function test_empty_items_shows_no_results_message(): void
    {
        $renderer = new Renderer();
        $state    = $this->makeState(['items' => [], 'loading' => false]);
        $ctx      = new RenderContext();

        $output = $this->capture(fn() => $renderer->render($state, $ctx));

        $this->assertStringContainsString('No results', $output);
    }

    // ---------------------------------------------------------------
    // Active index highlight
    // ---------------------------------------------------------------

    public function test_active_item_receives_highlight_marker(): void
    {
        $renderer = new Renderer();
        $state    = $this->makeState([
            'items' => ['Alpha', 'Beta', 'Gamma'],
            'index' => 1,
        ]);
        $ctx = new RenderContext();

        $output = $this->capture(fn() => $renderer->render($state, $ctx));

        // The active item pointer '›' should appear alongside 'Beta'
        $this->assertStringContainsString('Beta', $output);
        $this->assertStringContainsString('›', $output);
    }

    // ---------------------------------------------------------------
    // Multi-select mode — checkbox rendering
    // ---------------------------------------------------------------

    public function test_multi_mode_renders_checkboxes(): void
    {
        $renderer = new Renderer();
        $state    = $this->makeState([
            'items'    => ['Auth', 'API', 'Queue'],
            'multi'    => true,
            'selected' => ['Auth'],
        ]);
        $ctx = new RenderContext();

        $output = $this->capture(fn() => $renderer->render($state, $ctx));

        // Filled ⬢ for selected, empty ⬡ for unselected
        $this->assertStringContainsString('⬢', $output);
        $this->assertStringContainsString('⬡', $output);
    }

    public function test_multi_mode_marks_selected_item(): void
    {
        $renderer = new Renderer();
        $state    = $this->makeState([
            'items'    => ['Auth', 'API'],
            'multi'    => true,
            'selected' => ['Auth'],
        ]);
        $ctx = new RenderContext();

        $output = $this->capture(fn() => $renderer->render($state, $ctx));

        $this->assertStringContainsString('Auth', $output);
    }

    // ---------------------------------------------------------------
    // Scroll windowing — "more items" hints
    // ---------------------------------------------------------------

    public function test_scroll_indicator_shown_when_items_exceed_window(): void
    {
        $renderer = new Renderer();
        $items    = array_map(fn($i) => "Item-{$i}", range(1, 20));
        $state    = $this->makeState(['items' => $items, 'index' => 0]);
        $ctx      = new RenderContext();

        $output = $this->capture(fn() => $renderer->render($state, $ctx));

        // When there are more items than the window size (10), a "more" hint appears
        $this->assertStringContainsString('more items', $output);
    }

    // ---------------------------------------------------------------
    // Search query rendering
    // ---------------------------------------------------------------

    public function test_search_query_appears_in_output(): void
    {
        $renderer = new Renderer();
        $state    = $this->makeState(['search' => 'alph']);
        $ctx      = new RenderContext();

        $output = $this->capture(fn() => $renderer->render($state, $ctx));

        $this->assertStringContainsString('alph', $output);
    }

    public function test_search_label_appears_in_output(): void
    {
        $renderer = new Renderer();
        $state    = $this->makeState();
        $ctx      = new RenderContext();

        $output = $this->capture(fn() => $renderer->render($state, $ctx));

        $this->assertStringContainsString('Search', $output);
    }

    // ---------------------------------------------------------------
    // beforeRender / afterRender hooks
    // ---------------------------------------------------------------

    public function test_before_render_does_not_throw(): void
    {
        $renderer = new Renderer();
        $state    = $this->makeState();
        $ctx      = new RenderContext();

        // beforeRender emits cursor-hide escape codes — capture and discard
        $this->capture(fn() => $renderer->beforeRender($state, $ctx));

        $this->assertTrue(true);
    }

    public function test_after_render_does_not_throw(): void
    {
        $renderer = new Renderer();
        $state    = $this->makeState();
        $ctx      = new RenderContext();

        $this->capture(fn() => $renderer->afterRender($state, $ctx));

        $this->assertTrue(true);
    }

    // ---------------------------------------------------------------
    // Help text footer
    // ---------------------------------------------------------------

    public function test_single_select_footer_contains_nav_and_enter(): void
    {
        $renderer = new Renderer();
        $state    = $this->makeState(['multi' => false]);
        $ctx      = new RenderContext();

        $output = $this->capture(fn() => $renderer->render($state, $ctx));

        $this->assertStringContainsString('nav', $output);
        $this->assertStringContainsString('enter', $output);
    }

    public function test_multi_select_footer_contains_space_toggle(): void
    {
        $renderer = new Renderer();
        $state    = $this->makeState(['multi' => true, 'items' => ['A']]);
        $ctx      = new RenderContext();

        $output = $this->capture(fn() => $renderer->render($state, $ctx));

        $this->assertStringContainsString('space', $output);
    }
}
