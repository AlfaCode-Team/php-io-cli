<?php
declare(strict_types=1);

namespace AlfacodeTeam\PhpIoCli\Depends;

/**
 * Renders the CLI UI with scroll windowing, state management, and ANSI optimization.
 */
final class Renderer
{
    private int $lastLines = 0;
    private Spinner $spinner;
    private bool $cursorHidden = false;

    public function __construct()
    {
        $this->spinner = new Spinner();
    }

    public function render(State $state): void
    {
        // 1. Hide cursor on first render
        if (!$this->cursorHidden) {
            echo "\033[?25l"; 
            $this->cursorHidden = true;
        }

        // 2. Move cursor back to the start of our component block
        if ($this->lastLines > 0) {
            echo "\033[{$this->lastLines}A";
        }

        $lines = [];

        // HEADER
        $lines[] = Colors::wrap($state->question, [Colors::BOLD, Colors::CYAN]);

        // SEARCH (Add a blinking-style cursor representation)
        $searchQuery = $state->search ?: Colors::wrap('...', Colors::GRAY);
        $lines[] = Colors::wrap('Search: ', Colors::GRAY) . Colors::wrap($searchQuery, Colors::YELLOW);
        $lines[] = ''; // Spacer

        // LOADING STATE
        if ($state->loading) {
            $lines[] = Colors::wrap("  " . $this->spinner->tick() . " Loading...", Colors::CYAN);
            $this->display($lines);
            return;
        }

        $filtered = $state->filtered();

        // EMPTY STATE
        if (empty($filtered)) {
            $lines[] = Colors::wrap("  ✘ No results found.", Colors::RED);
            $this->display($lines);
            return;
        }

        /* =====================================================
           SCROLL WINDOWING
        ===================================================== */
        $windowSize = 10;
        $totalItems = count($filtered);
        
        $start = (int) max(0, min($state->index - floor($windowSize / 2), $totalItems - $windowSize));
        $end = (int) min($totalItems, $start + $windowSize);

        // Scroll Indicators
        $lines[] = ($start > 0) ? Colors::wrap("   ↑ more items", Colors::GRAY) : " ";

        foreach (array_slice($filtered, $start, $windowSize) as $i => $label) {
            $realIndex = $start + $i;
            $isActive = $realIndex === $state->index;
            $isSelected = in_array($label, $state->selected, true);

            $pointer = $isActive ? Colors::wrap('›', Colors::GREEN) : ' ';
            
            $checkbox = '';
            if ($state->multi) {
                $checkbox = $isSelected 
                    ? Colors::wrap('⬢', Colors::GREEN) // Use modern symbols
                    : Colors::wrap('⬡', Colors::GRAY);
            }

            $text = $label;
            if ($isActive) {
                $text = Colors::wrap($text, [Colors::YELLOW, Colors::BOLD]);
            } elseif ($isSelected) {
                $text = Colors::wrap($text, Colors::GREEN);
            } else {
                $text = Colors::wrap($text, Colors::DIM);
            }

            $lines[] = " {$pointer} {$checkbox} {$text}";
        }

        $lines[] = ($end < $totalItems) ? Colors::wrap("   ↓ more items", Colors::GRAY) : " ";

        // FOOTER
        $lines[] = '';
        $help = $state->multi
            ? "↑↓ nav • space toggle • enter confirm"
            : "↑↓ nav • enter confirm";
        $lines[] = Colors::wrap($help, Colors::GRAY);

        $this->display($lines);
    }

    /**
     * Final output to terminal
     */
    private function display(array $lines): void
    {
        $output = "";
        foreach ($lines as $line) {
            // \033[2K = Clear line, \r = Carriage return to start
            $output .= "\033[2K\r" . $line . PHP_EOL;
        }

        echo $output;
        $this->lastLines = count($lines);
    }

    /**
     * Restore terminal state on exit
     */
    public function __destruct()
    {
        // Show cursor again
        echo "\033[?25h";
    }
}