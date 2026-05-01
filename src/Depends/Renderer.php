<?php
declare(strict_types=1);

namespace AlfacodeTeam\PhpIoCli\Depends;

/* =========================================================
   RENDERER (scroll windowing + spinner)
========================================================= */

final class Renderer
{
    private int $lastLines = 0;
    private Spinner $spinner;

    public function __construct()
    {
        $this->spinner = new Spinner();
    }

    public function render(State $state): void
    {
        if ($this->lastLines > 0) {
            echo "\033[{$this->lastLines}A";
        }

        $lines = [];

        // HEADER
        $lines[] = Colors::wrap($state->question, Colors::BOLD . Colors::CYAN);

        // SEARCH
        $lines[] = Colors::wrap('Search: ', Colors::GRAY)
            . Colors::wrap($state->search ?: '...', Colors::YELLOW);

        // LOADING STATE (async simulation)
        if ($state->loading) {
            $lines[] = Colors::wrap(
                "Loading " . $this->spinner->tick(),
                Colors::CYAN
            );

            $this->print($lines);
            return;
        }

        $filtered = $state->filtered();

        /* =====================================================
           SCROLL WINDOWING (important for 1000+ items)
        ===================================================== */

        $windowSize = 10;
        $half = (int) floor($windowSize / 2);

        $start = max(0, $state->index - $half);
        $end = min(count($filtered), $start + $windowSize);

        if ($end - $start < $windowSize) {
            $start = max(0, $end - $windowSize);
        }

        $visible = array_slice($filtered, $start, $windowSize);

        foreach ($visible as $i => $label) {

            $realIndex = $start + $i;

            $isActive = $realIndex === $state->index;
            $isSelected = in_array($label, $state->selected, true);

            $pointer = $isActive ? Colors::wrap('›', Colors::GREEN) : ' ';

            $checkbox = $state->multi
                ? ($isSelected
                    ? Colors::wrap('[x]', Colors::GREEN)
                    : Colors::wrap('[ ]', Colors::GRAY))
                : '  ';

            $text = $label;

            if ($isActive) {
                $text = Colors::wrap($text, Colors::YELLOW);
            } elseif ($isSelected) {
                $text = Colors::wrap($text, Colors::GREEN);
            } else {
                $text = Colors::wrap($text, Colors::DIM);
            }

            $lines[] = "{$pointer} {$checkbox} {$text}";
        }

        $lines[] = '';
        $lines[] = Colors::wrap(
            $state->multi
                ? "↑↓/j k nav | space toggle | enter confirm"
                : "↑↓/j k nav | enter confirm",
            Colors::GRAY
        );

        $this->print($lines);
    }

    private function print(array $lines): void
    {
        foreach ($lines as $line) {
            echo "\033[2K\r" . $line . PHP_EOL;
        }

        $this->lastLines = count($lines);
    }
}