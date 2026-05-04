<?php

declare(strict_types=1);

namespace AlfacodeTeam\PhpIoCli\Components;

use AlfacodeTeam\PhpIoCli\AbstractPrompt;
use AlfacodeTeam\PhpIoCli\Depends\Input;
use AlfacodeTeam\PhpIoCli\Depends\Renderer;
use AlfacodeTeam\PhpIoCli\Depends\State;
use AlfacodeTeam\PhpIoCli\Hooks;

abstract class Component extends AbstractPrompt
{
    protected State $state;

    protected Input $input;

    protected Renderer $renderer;

    public function __construct(Hooks|null $hooks = null)
    {
        parent::__construct($hooks ?? new Hooks());
        $this->state = new State();
        $this->input = new Input();
        $this->renderer = new Renderer();
    }

    /* =========================================================
       ABSTRACT HOOKS FOR SUBCLASSES
    ========================================================= */

    /**
     * Subclasses wire their State + Input bindings here.
     */
    abstract protected function setup(): void;

    /* =========================================================
       LIFECYCLE
    ========================================================= */

    /**
     * Sealed: delegates to setup() so subclasses can't accidentally
     * break the boot sequence.
     */
    final public function mount(): void
    {
        $this->setup();
    }

    /**
     * Proxies the key update to the Input dispatcher, then marks the UI dirty.
     */
    public function update(string $key): void
    {
        $this->input->handle($key, $this->state);
        $this->context->markDirty();
    }

    public function destroy(): void
    {
        echo "\r\033[2K";
    }

    abstract public function render(): void;

    abstract public function resolve(): mixed;

    /**
     * Wire IRenderer::beforeRender() into AbstractPrompt's engine loop.
     */
    protected function beforeRenderHook(): void
    {
        $this->renderer->beforeRender($this->state, $this->context);
    }

    /**
     * Wire IRenderer::afterRender() into AbstractPrompt's engine loop.
     */
    protected function afterRenderHook(): void
    {
        $this->renderer->afterRender($this->state, $this->context);
    }
}
