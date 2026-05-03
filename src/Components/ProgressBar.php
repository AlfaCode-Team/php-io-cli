<?php
declare(strict_types=1);

namespace AlfacodeTeam\PhpIoCli\Components;

use AlfacodeTeam\PhpIoCli\Depends\Colors;
use AlfacodeTeam\PhpIoCli\Depends\Terminal;
use AlfacodeTeam\PhpIoCli\Depends\Spinner as SpinnerEngine;
use AlfacodeTeam\PhpIoCli\Depends\SpinnerFrames;

/**
 * Enterprise Integrated Progress Bar
 * Combines Bar logic + SpinnerEngine + Sub-label tracking.
 */
final class ProgressBar
{
    private SpinnerEngine $spinner;
    private int $current = 0;
    private int $lastLines = 0;
    private float $startTime = 0.0;
    private string $status = '';
    private bool $finished = false;

    private int $width = 40;
    private string $fillChar = '█';
    private string $emptyChar = '░';

    public function __construct(
        private string $label,
        private int $total = 0, // 0 = indeterminate
        string $spinnerStyle = 'dots'
    ) {
        $this->spinner = new SpinnerEngine(SpinnerFrames::get($spinnerStyle));
    }

    /* --- Fluent Configuration --- */
    public function width(int $w): self { $this->width = $w; return $this; }

    /* =========================================================
       CONTROL
    ========================================================= */

    public function start(): void
    {
        Terminal::hideCursor();
        $this->startTime = microtime(true);
        $this->spinner->start();
        $this->draw();
    }

    /**
     * Updates the status message and triggers a re-render.
     * Perfect for feeding shell output (git/composer) into the UI.
     */
    public function tick(string $status = ''): void
    {
        if ($status !== '') {
            // Truncate to prevent line wrapping which breaks cursor math
            $this->status = mb_strimwidth(trim($status), 0, 60, '...');
        }
        $this->draw();
    }

    public function advance(int $step = 1, string $status = ''): void
    {
        if ($this->total > 0) {
            $this->current = min($this->current + $step, $this->total);
        }
        if ($status !== '') $this->status = $status;
        $this->draw();
    }

    public function finish(string $message = ''): void
    {
        $this->finished = true;
        $this->spinner->stop();
        
        if ($this->total > 0) $this->current = $this->total;

        $this->draw($message);
        
        Terminal::showCursor();
        echo PHP_EOL;
    }

    /* =========================================================
       RENDER (The "No-Headache" Engine)
    ========================================================= */

    private function draw(string $finishMessage = ''): void
    {
        // 1. Move up and physically wipe the previous frame
        if ($this->lastLines > 0) {
            Terminal::moveCursorUp($this->lastLines);
            for ($i = 0; $i < $this->lastLines; $i++) {
                Terminal::clearLine();
                echo PHP_EOL;
            }
            Terminal::moveCursorUp($this->lastLines);
        }

        $elapsed = microtime(true) - $this->startTime;
        $lines = [];

        if ($this->finished) {
            // Finished State
            $msg = $finishMessage ?: "Completed: {$this->label}";
            $lines[] = Colors::success($msg) . Colors::muted(sprintf(" (%.2fs)", $elapsed));
        } else {
            $frame = Colors::wrap($this->spinner->tick() ?: '', Colors::CYAN);

            if ($this->total > 0) {
                // DETERMINATE MODE (Bar + Spinner + Status)
                $pct = $this->current / $this->total;
                $pctStr = str_pad((int)($pct * 100) . '%', 4, ' ', STR_PAD_LEFT);

                $lines[] = "{$frame} " . Colors::wrap($this->label, Colors::BOLD) 
                         . Colors::muted(sprintf(' (%d/%d)', $this->current, $this->total));
                
                $lines[] = '  ' . $this->buildBar($pct) . ' ' . Colors::wrap($pctStr, Colors::YELLOW);
            } else {
                // INDETERMINATE MODE (Label + Spinner + Status)
                $lines[] = "{$frame} " . Colors::wrap($this->label, Colors::BOLD) 
                         . Colors::muted(sprintf(' %.1fs', $elapsed));
            }

            // Sub-label (The "SpinnerComponent" style status)
            if ($this->status !== '') {
                $lines[] = Colors::muted("    └─ {$this->status}");
            }
        }

        // 2. Atomic Render
        $output = "";
        foreach ($lines as $line) {
            $output .= "\r\033[2K" . $line . PHP_EOL;
        }
        echo $output;

        $this->lastLines = count($lines);
    }

    private function buildBar(float $pct): string
    {
        $filledSize = (int) round($this->width * $pct);
        $emptySize  = $this->width - $filledSize;

        $color = match (true) {
            $pct >= 1.0 => Colors::GREEN,
            $pct >= 0.7 => Colors::CYAN,
            $pct >= 0.3 => Colors::YELLOW,
            default     => Colors::RED,
        };

        return Colors::wrap(str_repeat($this->fillChar, $filledSize), $color)
             . Colors::muted(str_repeat($this->emptyChar, $emptySize));
    }
}