<?php

declare(strict_types=1);

namespace AlfacodeTeam\PhpIoCli\Components;

use AlfacodeTeam\PhpIoCli\Depends\Colors;
use AlfacodeTeam\PhpIoCli\Depends\Terminal;

/**
 * Horizontal bar slider for numeric ranges.
 *
 * Renders a live ASCII progress bar that the user moves with arrow keys.
 * Supports float and integer modes, configurable step, and an optional
 * snap-to-step enforcement on submit.
 *
 * Usage:
 *   $volume = (new SliderInput('Volume', min: 0, max: 100))
 *       ->step(5)
 *       ->default(50)
 *       ->integer()
 *       ->run(); // returns int
 *
 *   $rate = (new SliderInput('Tax rate', min: 0.0, max: 1.0))
 *       ->step(0.01)
 *       ->default(0.2)
 *       ->run(); // returns float
 */
final class SliderInput extends Component
{
    private float $min;

    private float $max;

    private float $step;

    private float $defaultValue;

    private bool $intOnly = false;

    private int $barWidth = 30;

    private int $lastLines = 0;

    public function __construct(
        private string $question,
        float $min = 0,
        float $max = 100,
    ) {
        $this->min = $min;
        $this->max = $max;
        $this->step = 1.0;
        $this->defaultValue = $min;
        parent::__construct();
    }

    /* =========================================================
       LIFECYCLE
    ========================================================= */

    protected function setup(): void
    {
        $this->state->batch([
            'value' => $this->defaultValue,
            'done' => false,
        ]);

        // Single step left / right
        $this->input->bind('LEFT', function ($s): void {
            $s->value = $this->clamp((float) $s->value - $this->step);
        });

        $this->input->bind('RIGHT', function ($s): void {
            $s->value = $this->clamp((float) $s->value + $this->step);
        });

        // Jump 10 % of range per page key / shift-arrow equivalent ([ and ])
        $this->input->bind(['[', 'PAGE_UP'], function ($s): void {
            $jump = ($this->max - $this->min) * 0.1;
            $s->value = $this->clamp((float) $s->value - $jump);
        });

        $this->input->bind([']', 'PAGE_DOWN'], function ($s): void {
            $jump = ($this->max - $this->min) * 0.1;
            $s->value = $this->clamp((float) $s->value + $jump);
        });

        // Home / End — jump to extremes
        $this->input->bind('HOME', function ($s): void {
            $s->value = $this->min;
        });

        $this->input->bind('END', function ($s): void {
            $s->value = $this->max;
        });

        // Submit
        $this->input->bind('ENTER', function ($s): void {
            // Snap to nearest step on submit
            $s->value = $this->snap((float) $s->value);
            $s->done = true;
            $this->stop();
        });
    }

    /* =========================================================
       FLUENT CONFIGURATION
    ========================================================= */

    public function min(float $min): self
    {
        $this->min = $min;

        return $this;
    }

    public function max(float $max): self
    {
        $this->max = $max;

        return $this;
    }

    public function step(float $step): self
    {
        $this->step = max($step, PHP_FLOAT_EPSILON);

        return $this;
    }

    public function default(float $value): self
    {
        $this->defaultValue = $this->clamp($value);

        return $this;
    }

    public function integer(): self
    {
        $this->intOnly = true;

        return $this;
    }

    public function width(int $chars): self
    {
        $this->barWidth = max(10, $chars);

        return $this;
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

        $value = (float) $this->state->value;
        $done = (bool) $this->state->done;
        $lines = [];

        // ── Line 1: question ──────────────────────────────────
        $mark = $done
            ? Colors::success('')
            : Colors::wrap('? ', Colors::CYAN);
        $lines[] = $mark . Colors::wrap($this->question, Colors::BOLD);

        if (!$done) {
            // ── Line 2: bar + value ───────────────────────────
            $lines[] = '  ' . $this->buildBar($value) . '  ' . Colors::wrap($this->format($value), [Colors::YELLOW, Colors::BOLD]);

            // ── Line 3: range hint ────────────────────────────
            $lo = $this->format($this->min);
            $hi = $this->format($this->max);
            $lines[] = Colors::muted("  {$lo}" . str_repeat(' ', $this->barWidth) . "{$hi}");

            // ── Line 4: help ──────────────────────────────────
            $lines[] = Colors::muted('  ← → step  •  [ ] jump 10%  •  HOME/END  •  ENTER confirm');
        } else {
            $lines[] = Colors::wrap('  › ', Colors::GRAY) . Colors::wrap($this->format($value), Colors::GREEN);
        }

        foreach ($lines as $line) {
            Terminal::clearLine();
            echo $line . PHP_EOL;
        }

        $this->lastLines = count($lines);
    }

    /* =========================================================
       CLEANUP & RESOLVE
    ========================================================= */

    public function destroy(): void
    {
        Terminal::showCursor();
        parent::destroy();
    }

    public function resolve(): mixed
    {
        $value = $this->snap((float) $this->state->value);

        return $this->intOnly ? (int) round($value) : $value;
    }

    /* =========================================================
       BAR BUILDER
    ========================================================= */

    private function buildBar(float $value): string
    {
        $range = $this->max - $this->min;
        $pct = $range > 0 ? ($value - $this->min) / $range : 0.0;
        $pct = max(0.0, min(1.0, $pct));

        $filled = (int) round($this->barWidth * $pct);
        $empty = $this->barWidth - $filled;

        // Thumb sits at the boundary between filled and empty
        $thumbPos = $filled > 0 ? $filled - 1 : 0;
        $filledStr = str_repeat('━', $thumbPos) . Colors::wrap('●', [Colors::CYAN, Colors::BOLD]) . str_repeat('━', max(0, $filled - $thumbPos - 1));
        $emptyStr = Colors::muted(str_repeat('─', $empty));

        $color = match (true) {
            $pct >= 0.75 => Colors::GREEN,
            $pct >= 0.40 => Colors::CYAN,
            $pct >= 0.15 => Colors::YELLOW,
            default => Colors::RED,
        };

        return Colors::wrap('[', Colors::GRAY)
            . Colors::wrap($filledStr, $color)
            . $emptyStr
            . Colors::wrap(']', Colors::GRAY);
    }

    /* =========================================================
       HELPERS
    ========================================================= */

    private function clamp(float $value): float
    {
        return max($this->min, min($this->max, $value));
    }

    private function snap(float $value): float
    {
        if ($this->step <= 0) {
            return $this->clamp($value);
        }

        $snapped = round(($value - $this->min) / $this->step) * $this->step + $this->min;

        return $this->clamp($snapped);
    }

    private function format(float $value): string
    {
        if ($this->intOnly) {
            return (string) (int) round($value);
        }

        // Auto-detect required decimal places from step
        $decimals = 0;
        $stepStr = mb_rtrim(mb_rtrim(number_format($this->step, 10, '.', ''), '0'), '.');
        if (str_contains($stepStr, '.')) {
            $decimals = mb_strlen(explode('.', $stepStr)[1]);
        }

        return number_format($value, $decimals, '.', '');
    }
}
