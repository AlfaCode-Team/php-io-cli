<?php
declare(strict_types= 1);

namespace AlfacodeTeam\PhpIoCli;
final class RenderContext
{
    public function __construct(
        public bool $dirty = true,
        public int $width = 80,
        public int $height = 24,
        public array $meta = [],
    ) {}

    public function markDirty(): void
    {
        $this->dirty = true;
    }

    public function clearDirty(): void
    {
        $this->dirty = false;
    }
}