<?php
declare(strict_types=1);

namespace AlfacodeTeam\PhpIoCli;

/**
 * Defines the contract for visual rendering of components.
 */
interface IRenderer
{
    /**
     * Main render entry point.
     * 
     * @param Depends\State $state Usually an instance of AlfacodeTeam\PhpIoCli\Depends\State
     */
    public function render(Depends\State $state, Depends\RenderContext $context): void;

    /**
     * Triggered before the main render. Useful for cursor hiding or clearing.
     */
    public function beforeRender(Depends\State $state, Depends\RenderContext $context): void;

    /**
     * Triggered after render. Useful for cursor positioning or buffering.
     */
    public function afterRender(Depends\State $state, Depends\RenderContext $context): void;

    /**
     * A unique key representing the current visual state for diffing/caching.
     */
    public function key(): string;
}