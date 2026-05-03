<?php

declare(strict_types=1);

namespace AlfacodeTeam\PhpIoCli\Depends;

final class Spinner
{
    private array $frames;
    private int $index = 0;
    private float $interval;
    private float $lastTick = 0.0;
    private bool $running = false;
    private string $currentFrame = '';

    public function __construct(
        ?array $frames = null,
        float $interval = 0.1
    ) {
        $this->frames = $frames ?? SpinnerFrames::default();
        $this->interval = $interval;
        $this->currentFrame = $this->frames[0];
    }

    public function start(): void
    {
        $this->running = true;
        $this->lastTick = microtime(true);
    }

    public function stop(): void
    {
        $this->running = false;
    }

    /**
     * Updates the internal state and returns the current frame.
     * Does NOT echo — the Renderer owns all terminal output.
     */
    public function tick(): string
    {
        if (!$this->running) {
            return '';
        }

        $now = microtime(true);

        if (($now - $this->lastTick) >= $this->interval) {
            $this->index++;
            $this->lastTick = $now;
            $this->currentFrame = $this->frames[$this->index % count($this->frames)];
        }

        return $this->currentFrame;
    }
}
