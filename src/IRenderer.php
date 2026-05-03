<?php

declare(strict_types=1);

namespace AlfacodeTeam\PhpIoCli;

use AlfacodeTeam\PhpIoCli\Depends\RenderContext;
use AlfacodeTeam\PhpIoCli\Depends\State;

/**
 * Defines the contract for visual rendering of components.
 *
 * Previously this interface existed but was never implemented by Renderer.
 * Renderer now declares `implements IRenderer` and satisfies all three methods.
 */
interface IRenderer
{
    /**
     * Main render entry point.
     */
    public function render(State $state, RenderContext $context): void;

    /**
     * Triggered before the main render.
     * Typical uses: hide cursor, move cursor to frame start.
     */
    public function beforeRender(State $state, RenderContext $context): void;

    /**
     * Triggered after the main render.
     * Typical uses: cursor repositioning, output flushing.
     */
    public function afterRender(State $state, RenderContext $context): void;

    /**
     * A unique key representing the renderer type, used for diffing/caching.
     */
    public function key(): string;
}
