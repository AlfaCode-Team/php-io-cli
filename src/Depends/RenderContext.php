<?php

declare(strict_types=1);

namespace AlfacodeTeam\PhpIoCli\Depends;

/**
 * Tracks the state, dimensions, and metadata of the current render cycle.
 */
final class RenderContext
{
    public function __construct(
        public bool $dirty = true,
        public int $width = 80,
        public int $height = 24,
        public array $meta = [],
    ) {
        // Automatically detect size if possible on initialization
        $this->refreshDimensions();
    }

    /**
     * Mark the context as needing a re-render.
     */
    public function markDirty(): self
    {
        $this->dirty = true;
        return $this;
    }

    /**
     * Reset the dirty flag after a successful render.
     */
    public function clear(): self
    {
        $this->dirty = false;
        return $this;
    }

    /**
     * Check if the UI needs to be redrawn.
     */
    public function shouldRender(): bool
    {
        return $this->dirty;
    }

    /**
     * Fetch the current terminal dimensions dynamically.
     */
    public function refreshDimensions(): self
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $mode = shell_exec('mode con');
            if ($mode && preg_match('/Columns:\s+(\d+).*Lines:\s+(\d+)/s', $mode, $matches)) {
                $this->width = (int) $matches[1];
                $this->height = (int) $matches[2];
            }
        } else {
            $stty = shell_exec('stty size 2>/dev/null');
            if ($stty) {
                [$rows, $cols] = explode(' ', trim($stty));
                $this->height = (int) $rows;
                $this->width = (int) $cols;
            }
        }

        return $this;
    }

    /**
     * Helper to set metadata fluently.
     */
    public function set(string $key, mixed $value): self
    {
        $this->meta[$key] = $value;
        return $this;
    }

    /**
     * Helper to retrieve metadata with a fallback.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->meta[$key] ?? $default;
    }
}
