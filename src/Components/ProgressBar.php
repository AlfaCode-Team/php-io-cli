<?php
declare(strict_types=1);

namespace AlfacodeTeam\PhpIoCli\Components;

use AlfacodeTeam\PhpIoCli\Depends\Colors;
use AlfacodeTeam\PhpIoCli\Depends\Terminal;

/**
 * Determinate & indeterminate progress bar.
 *
 * Usage (determinate):
 *   $bar = new ProgressBar('Uploading', 100);
 *   $bar->start();
 *   foreach ($items as $item) { process($item); $bar->advance(); }
 *   $bar->finish();
 *
 * Usage (indeterminate / spinner):
 *   $bar = new ProgressBar('Processing');
 *   $bar->start();
 *   // ... do work ...
 *   $bar->finish('Done!');
 */
final class ProgressBar
{
    private int $current = 0;
    private int $lastLines = 0;
    private float $startTime = 0.0;
    private bool $started = false;
    private bool $finished = false;

    private int $width = 40;
    private string $fillChar = '█';
    private string $emptyChar = '░';

    /** Indeterminate bounce position */
    private int $bouncePos = 0;
    private int $bounceDir = 1;
    private float $lastBounce = 0.0;

    public function __construct(
        private string $label,
        private int $total = 0 // 0 = indeterminate
    ) {}

    /* --- Fluent Setters --- */
    public function width(int $w): self { $this->width = $w; return $this; }
    public function fill(string $c): self { $this->fillChar = $c; return $this; }
    public function empty(string $c): self { $this->emptyChar = $c; return $this; }

    public function start(): void
    {
        if ($this->started) return;
        
        Terminal::hideCursor();
        $this->startTime = microtime(true);
        $this->started = true;
        $this->draw();
    }

    public function advance(int $step = 1): void
    {
        if ($this->total > 0) {
            $this->current = min($this->current + $step, $this->total);
        }
        $this->draw();
    }

    public function finish(string $message = ''): void
    {
        if (!$this->started || $this->finished) return;

        if ($this->total > 0) $this->current = $this->total;
        
        $this->finished = true;
        $this->draw($message);
        
        Terminal::showCursor();
        echo PHP_EOL;
    }

    private function draw(string $finishMessage = ''): void
    {
        // Move back to the top of the bar block
        if ($this->lastLines > 0) {
            Terminal::moveCursorUp($this->lastLines);
        }

        $elapsed = microtime(true) - $this->startTime;
        $lines = [];

        if ($this->finished) {
            $lines[] = Colors::success($finishMessage ?: "Completed: {$this->label}") 
                     . Colors::muted(sprintf(" (%.2fs)", $elapsed));
        } elseif ($this->total === 0) {
            $lines = $this->renderIndeterminate($elapsed);
        } else {
            $lines = $this->renderDeterminate($elapsed);
        }

        // Atomic render to prevent flickering
        $output = "";
        foreach ($lines as $line) {
            $output .= "\r\033[2K" . $line . PHP_EOL;
        }
        echo $output;

        $this->lastLines = count($lines);
    }

    private function renderDeterminate(float $elapsed): array
    {
        $pct = $this->current / $this->total;
        $pctStr = str_pad((int)($pct * 100) . '%', 4, ' ', STR_PAD_LEFT);

        $lines[] = Colors::wrap($this->label, Colors::BOLD)
                 . Colors::muted(sprintf('  %d / %d', $this->current, $this->total));
        
        $lines[] = '  ' . $this->buildBarVisual($pct) . '  ' . Colors::wrap($pctStr, Colors::YELLOW);

        if ($this->current > 0 && $pct < 1.0) {
            $rate = $this->current / $elapsed;
            $eta = ($this->total - $this->current) / max($rate, 0.001);
            $lines[] = Colors::muted(sprintf('  %.1f items/s • ETA %.0fs', $rate, $eta));
        } else {
            $lines[] = Colors::muted("  Processing...");
        }

        return $lines;
    }

    private function renderIndeterminate(float $elapsed): array
    {
        $lines[] = Colors::wrap($this->label, Colors::BOLD);
        $lines[] = '  ' . $this->getBounceVisual();
        $lines[] = Colors::muted(sprintf('  Elapsed: %.1fs', $elapsed));
        return $lines;
    }

    private function buildBarVisual(float $pct): string
    {
        $filledSize = (int) round($this->width * $pct);
        $emptySize = $this->width - $filledSize;

        $color = match (true) {
            $pct >= 1.0 => Colors::GREEN,
            $pct >= 0.7 => Colors::CYAN,
            $pct >= 0.3 => Colors::YELLOW,
            default     => Colors::RED,
        };

        return Colors::wrap(str_repeat($this->fillChar, $filledSize), $color)
             . Colors::muted(str_repeat($this->emptyChar, $emptySize));
    }

    private function getBounceVisual(): string
    {
        $now = microtime(true);
        $bounceLen = 6;

        // Only update bounce physics if enough time has passed
        if (($now - $this->lastBounce) >= 0.05) {
            $this->bouncePos += $this->bounceDir;
            if ($this->bouncePos >= ($this->width - $bounceLen) || $this->bouncePos <= 0) {
                $this->bounceDir *= -1;
            }
            $this->lastBounce = $now;
        }

        $bar = array_fill(0, $this->width, $this->emptyChar);
        for ($i = 0; $i < $bounceLen; $i++) {
            $bar[$this->bouncePos + $i] = $this->fillChar;
        }

        return Colors::muted('[') . Colors::wrap(implode('', $bar), Colors::CYAN) . Colors::muted(']');
    }
}