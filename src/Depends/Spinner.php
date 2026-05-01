<?php
declare(strict_types=1);

namespace AlfacodeTeam\PhpIoCli\Depends;

/* =========================================================
   SPINNER (async loading state)
========================================================= */

final class Spinner
{
    private array $frames;
    private int $index = 0;
    private float $interval;
    private float $lastTick = 0.0;
    private bool $running = false;
    private string $message = '';

    public function __construct(
        array $frames = SpinnerFrames::default(),
        float $interval = 0.1
    ) {
        $this->frames = $frames;
        $this->interval = $interval;
    }

    /* =========================================================
       LIFECYCLE
    ========================================================= */

    public function start(string $message = ''): void
    {
        $this->running = true;
        $this->message = $message;
        $this->render();
    }

    public function stop(): void
    {
        $this->running = false;
        $this->clearLine();
    }

    /* =========================================================
       TICK (non-blocking safe)
    ========================================================= */

    public function tick(): ?string
    {
        if (! $this->running) {
            return null;
        }

        $now = microtime(true);

        if (($now - $this->lastTick) < $this->interval) {
            return null;
        }

        $this->lastTick = $now;

        $frame = $this->frames[$this->index % count($this->frames)];
        $this->index++;

        $this->render($frame);

        return $frame;
    }

    /* =========================================================
       RENDER
    ========================================================= */

    private function render(?string $frame = null): void
    {
        $frame ??= $this->frames[$this->index % count($this->frames)];

        $this->clearLine();

        echo "{$frame} {$this->message}";
        fflush(STDOUT);
    }

    /* =========================================================
       TERMINAL CONTROL
    ========================================================= */

    private function clearLine(): void
    {
        echo "\r\033[2K";
    }
}