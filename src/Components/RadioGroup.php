<?php

declare(strict_types=1);

namespace AlfacodeTeam\PhpIoCli\Components;

use AlfacodeTeam\PhpIoCli\Depends\Colors;
use AlfacodeTeam\PhpIoCli\Depends\Terminal;

/**
 * Radio-button group — renders all options at once with no scroll.
 *
 * Best for short, mutually exclusive choice lists (≤ 5 items). For longer
 * lists use {@see Select}, which adds fuzzy search and scroll windowing.
 *
 * Keys
 * ────
 *   ↑ / ↓  — move focus
 *   ← / →  — same as ↑ / ↓ (horizontal feel for short lists)
 *   1-9    — jump directly to option by position
 *   ENTER  — confirm focused option
 *
 * Usage:
 *   $size = (new RadioGroup('T-shirt size', ['S', 'M', 'L', 'XL', 'XXL']))
 *       ->default('M')
 *       ->run(); // returns string
 *
 *   $priority = (new RadioGroup('Priority', ['low', 'medium', 'high']))
 *       ->columns(3)   // render options side-by-side
 *       ->run();
 */
final class RadioGroup extends Component
{
    private int $lastLines = 0;

    private string $defaultValue = '';

    private int $columns = 1;

    public function __construct(
        private string $question,
        private array $choices,
    ) {
        parent::__construct();
    }

    /* =========================================================
       FLUENT CONFIGURATION
    ========================================================= */

    /**
     * Pre-select a choice by value.
     */
    public function default(string $value): self
    {
        $this->defaultValue = $value;

        return $this;
    }

    /**
     * Render options in multiple columns (side-by-side).
     * Useful when choices are short words and screen width allows it.
     */
    public function columns(int $count): self
    {
        $this->columns = max(1, $count);

        return $this;
    }

    /* =========================================================
       LIFECYCLE
    ========================================================= */

    protected function setup(): void
    {
        // Resolve the default index
        $defaultIndex = 0;
        if ($this->defaultValue !== '') {
            $found = array_search($this->defaultValue, $this->choices, strict: true);
            if ($found !== false) {
                $defaultIndex = (int) $found;
            }
        }

        $this->state->batch([
            'index' => $defaultIndex,
            'done'  => false,
        ]);

        $total = count($this->choices);

        // ↑ / ← — move up/left
        $this->input->bind(['UP', 'LEFT'], static function ($s) use ($total): void {
            $s->index = ((int) $s->index - 1 + $total) % $total;
        });

        // ↓ / → — move down/right
        $this->input->bind(['DOWN', 'RIGHT'], static function ($s) use ($total): void {
            $s->index = ((int) $s->index + 1) % $total;
        });

        // Digit shortcuts: 1-9 jump directly to that position
        foreach (range(1, min(9, $total)) as $digit) {
            $idx = $digit - 1;
            $this->input->bind((string) $digit, static function ($s) use ($idx): void {
                $s->index = $idx;
            });
        }

        // ENTER — confirm
        $this->input->bind('ENTER', function ($s): void {
            $s->done = true;
            $this->stop();
        });
    }

    /* =========================================================
       RENDER
    ========================================================= */

    public function render(): void
    {
        if ($this->lastLines > 0) {
            Terminal::moveCursorUp($this->lastLines);
        }

        Terminal::hideCursor();

        $index = (int) $this->state->index;
        $done  = (bool) $this->state->done;
        $lines = [];

        // ── Line 1: question ──────────────────────────────────
        $mark = $done
            ? Colors::success('')
            : Colors::wrap('? ', Colors::CYAN);
        $lines[] = $mark . Colors::wrap($this->question, Colors::BOLD);

        if (!$done) {
            $lines = array_merge($lines, $this->renderOptions($index));
            $lines[] = Colors::muted('  ↑↓ move  •  1-9 jump  •  ENTER confirm');
        } else {
            $selected = $this->choices[$index] ?? '';
            $lines[]  = Colors::wrap('  › ', Colors::GRAY) . Colors::wrap($selected, Colors::GREEN);
        }

        foreach ($lines as $line) {
            Terminal::clearLine();
            echo $line . PHP_EOL;
        }

        $this->lastLines = count($lines);
    }

    /* =========================================================
       RESOLVE
    ========================================================= */

    public function resolve(): mixed
    {
        return $this->choices[(int) $this->state->index] ?? null;
    }

    /* =========================================================
       CLEANUP
    ========================================================= */

    public function destroy(): void
    {
        Terminal::showCursor();
        parent::destroy();
    }

    /* =========================================================
       PRIVATE RENDERING HELPERS
    ========================================================= */

    /**
     * Returns rendered option lines, respecting the column layout.
     *
     * @return string[]
     */
    private function renderOptions(int $activeIndex): array
    {
        if ($this->columns === 1) {
            return $this->renderSingleColumn($activeIndex);
        }

        return $this->renderMultiColumn($activeIndex);
    }

    /**
     * Classic vertical list — one option per line.
     *
     * @return string[]
     */
    private function renderSingleColumn(int $activeIndex): array
    {
        $lines = [];
        $lines[] = '';

        foreach ($this->choices as $i => $choice) {
            $lines[] = $this->renderOption((int) $i, (string) $choice, $i === $activeIndex);
        }

        $lines[] = '';

        return $lines;
    }

    /**
     * Multi-column layout — groups choices into rows of $this->columns.
     *
     * @return string[]
     */
    private function renderMultiColumn(int $activeIndex): array
    {
        // Compute max visual width of any option label (for uniform column padding)
        $maxLen = 0;
        foreach ($this->choices as $choice) {
            $maxLen = max($maxLen, mb_strlen((string) $choice));
        }
        // Each cell: "  ◉ label" or "  ○ label" — radio (2) + space (1) + label + 2 pad
        $cellWidth = $maxLen + 5;

        $lines   = [];
        $lines[] = '';

        $chunks = array_chunk($this->choices, max(1, $this->columns), preserve_keys: true);

        foreach ($chunks as $row) {
            $parts = [];
            foreach ($row as $i => $choice) {
                $parts[] = $this->renderOption((int) $i, (string) $choice, $i === $activeIndex, $cellWidth);
            }
            $lines[] = implode('  ', $parts);
        }

        $lines[] = '';

        return $lines;
    }

    /**
     * Renders a single radio option with its indicator, label, and digit shortcut.
     */
    private function renderOption(int $index, string $choice, bool $active, int $padWidth = 0): string
    {
        $digit = ($index < 9) ? Colors::muted((string) ($index + 1)) : ' ';

        if ($active) {
            $radio = Colors::wrap('◉', Colors::CYAN);
            $label = Colors::wrap($choice, [Colors::YELLOW, Colors::BOLD]);
            $prefix = Colors::wrap('› ', Colors::CYAN);
        } else {
            $radio = Colors::muted('○');
            $label = Colors::wrap($choice, Colors::GRAY);
            $prefix = '  ';
        }

        $cell = "{$prefix}{$radio} {$label}";

        // Pad to uniform column width if rendering multi-column
        if ($padWidth > 0) {
            $visualLen = mb_strlen(Colors::strip($cell));
            $cell .= str_repeat(' ', max(0, $padWidth - $visualLen));
        }

        return $digit . ' ' . $cell;
    }
}
