<?php

declare(strict_types=1);

namespace AlfacodeTeam\PhpIoCli\Depends;

use AlfacodeTeam\PhpIoCli\IRenderer;

/**
 * Renders the CLI UI with scroll windowing, spinner, and ANSI optimisation.
 * Implements IRenderer so the interface contract is actually enforced.
 */
final class Renderer implements IRenderer
{
    private int $lastLines = 0;

    private Spinner $spinner;

    private bool $cursorHidden = false;

    public function __construct()
    {
        $this->spinner = new Spinner();
    }

    public function __destruct()
    {
        echo "\033[?25h";
    }

    /* =========================================================
       IRenderer — lifecycle hooks
    ========================================================= */

    /**
     * Called before the main render. Hides the cursor on the first frame.
     */
    public function beforeRender(State $state, RenderContext $context): void
    {
        if (!$this->cursorHidden) {
            echo "\033[?25l";
            $this->cursorHidden = true;
        }

        // Move cursor back to the start of our component block so we paint
        // over the previous frame in-place (no flicker).
        if ($this->lastLines > 0) {
            echo "\033[{$this->lastLines}A";
        }
    }

    /**
     * Called after the main render. Nothing extra required right now,
     * but the hook is here for subclasses / future cursor positioning.
     */
    public function afterRender(State $state, RenderContext $context): void {}

    /**
     * Main render entry point. Delegates to beforeRender → paint → afterRender.
     */
    public function render(State $state, RenderContext $context): void
    {
        $this->beforeRender($state, $context);
        $this->paint($state);
        $this->afterRender($state, $context);
    }

    /**
     * Convenience overload that accepts a bare State without a RenderContext.
     * Used by Prompt.php-era callers and the legacy Renderer::render(State) API.
     *
     * @internal Use render(State, RenderContext) when possible.
     */
    public function renderState(State $state): void
    {
        $this->render($state, new RenderContext());
    }

    /* =========================================================
       IRenderer — cache key
    ========================================================= */

    public function key(): string
    {
        return self::class;
    }

    /* =========================================================
       Painting
    ========================================================= */

    private function paint(State $state): void
    {
        $lines = [];

        // HEADER
        $lines[] = Colors::wrap($state->question ?? '', [Colors::BOLD, Colors::CYAN]);

        // SEARCH
        $searchQuery = $state->search ?: Colors::wrap('...', Colors::GRAY);
        $lines[] = Colors::wrap('Search: ', Colors::GRAY) . Colors::wrap($searchQuery, Colors::YELLOW);
        $lines[] = '';

        // LOADING
        if ($state->loading) {
            $this->spinner->start();
            $lines[] = Colors::wrap('  ' . $this->spinner->tick() . ' Loading...', Colors::CYAN);
            $this->display($lines);

            return;
        }

        $this->spinner->stop();

        $filtered = is_callable([$state, 'filtered']) ? $state->filtered() : [];

        // EMPTY STATE
        if (empty($filtered)) {
            $lines[] = Colors::wrap('  ✘ No results found.', Colors::RED);
            $this->display($lines);

            return;
        }

        /* =====================================================
           SCROLL WINDOWING
        ===================================================== */
        $windowSize = 10;
        $totalItems = count($filtered);
        $index = (int) ($state->index ?? 0);

        $start = (int) max(0, min($index - (int) floor($windowSize / 2), $totalItems - $windowSize));
        $end = (int) min($totalItems, $start + $windowSize);

        $lines[] = ($start > 0) ? Colors::wrap('   ↑ more items', Colors::GRAY) : ' ';

        foreach (array_values(array_slice($filtered, $start, $windowSize)) as $i => $label) {
            $realIndex = $start + $i;
            $isActive = $realIndex === $index;
            $isSelected = in_array($label, (array) ($state->selected ?? []), true);

            $pointer = $isActive ? Colors::wrap('›', Colors::GREEN) : ' ';
            $checkbox = '';

            if ($state->multi ?? false) {
                $checkbox = $isSelected
                    ? Colors::wrap('⬢', Colors::GREEN)
                    : Colors::wrap('⬡', Colors::GRAY);
            }

            $text = $isActive
                ? Colors::wrap($label, [Colors::YELLOW, Colors::BOLD])
                : ($isSelected
                    ? Colors::wrap($label, Colors::GREEN)
                    : Colors::wrap($label, Colors::DIM));

            $lines[] = " {$pointer} {$checkbox} {$text}";
        }

        $lines[] = ($end < $totalItems) ? Colors::wrap('   ↓ more items', Colors::GRAY) : ' ';

        // FOOTER
        $lines[] = '';
        $help = ($state->multi ?? false)
            ? '↑↓ nav • space toggle • enter confirm'
            : '↑↓ nav • enter confirm';
        $lines[] = Colors::wrap($help, Colors::GRAY);

        $this->display($lines);
    }

    private function display(array $lines): void
    {
        $output = '';
        foreach ($lines as $line) {
            $output .= "\033[2K\r" . $line . PHP_EOL;
        }

        echo $output;
        $this->lastLines = count($lines);
    }
}
