<?php
namespace AlfacodeTeam\PhpIoCli;

interface IRenderer
{
    /**
     * Main render entry point.
     */
    public function render(mixed $state, RenderContext $context): void;

    /**
     * Optional: called before rendering begins.
     */
    public function beforeRender(mixed $state, RenderContext $context): void;

    /**
     * Optional: called after rendering completes.
     */
    public function afterRender(mixed $state, RenderContext $context): void;

    /**
     * Optional: return a unique render key for diffing systems.
     */
    public function key(): string;
}