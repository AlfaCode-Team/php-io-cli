<?php

declare(strict_types=1);

namespace AlfacodeTeam\PhpIoCli\Components;

use AlfacodeTeam\PhpIoCli\Depends\Colors;
use AlfacodeTeam\PhpIoCli\Depends\Spinner as SpinnerEngine;
use AlfacodeTeam\PhpIoCli\Depends\SpinnerFrames;
use AlfacodeTeam\PhpIoCli\Depends\Terminal;

/**
 * Standalone non-blocking spinner for wrapping long-running tasks.
 *
 * Usage:
 *   $spin = new SpinnerComponent('Fetching data');
 *   $spin->start();
 *   $result = doSlowWork();
 *   $spin->stop('Done!');
 */
final class SpinnerComponent
{
    private SpinnerEngine $engine;

    private bool $running = false;

    private float $startTime = 0.0;

    private int $lastLines = 0;

    public function __construct(
        private string $label,
        string $style = 'dots',
    ) {
        $this->engine = new SpinnerEngine(SpinnerFrames::get($style));
    }

    public function start(): void
    {
        Terminal::hideCursor();
        $this->running = true;
        $this->startTime = microtime(true);
        $this->engine->start();
        $this->draw();
    }

    /**
     * Call this in your loop to keep the spinner animating.
     */
    public function tick(string $subLabel = ''): void
    {
        if (!$this->running) {
            return;
        }
        $this->draw($subLabel);
    }

    public function stop(string $successMessage = ''): void
    {
        $this->engine->stop();
        $this->running = false;

        if ($this->lastLines > 0) {
            Terminal::moveCursorUp($this->lastLines);
        }

        $elapsed = round(microtime(true) - $this->startTime, 2);
        $msg = $successMessage ?: $this->label;
        Terminal::clearLine();
        echo Colors::success($msg) . Colors::muted("  ({$elapsed}s)") . PHP_EOL;

        Terminal::showCursor();
    }

    public function fail(string $message = ''): void
    {
        $this->engine->stop();
        $this->running = false;

        if ($this->lastLines > 0) {
            Terminal::moveCursorUp($this->lastLines);
        }

        Terminal::clearLine();
        echo Colors::error($message ?: $this->label) . PHP_EOL;
        Terminal::showCursor();
    }

    private function draw(string $subLabel = ''): void
    {
        if ($this->lastLines > 0) {
            Terminal::moveCursorUp($this->lastLines);
        }

        $frame = $this->engine->tick();
        $elapsed = round(microtime(true) - $this->startTime, 1);
        $lines = [];

        $lines[] = Colors::wrap($frame . ' ', Colors::CYAN)
                 . Colors::wrap($this->label, Colors::BOLD)
                 . Colors::muted("  {$elapsed}s");

        if ($subLabel !== '') {
            $lines[] = Colors::muted("  └─ {$subLabel}");
        }

        foreach ($lines as $line) {
            Terminal::clearLine();
            echo $line . PHP_EOL;
        }

        $this->lastLines = count($lines);
    }
}
