<?php
declare(strict_types=1);

namespace AlfacodeTeam\PhpIoCli\Components;

use AlfacodeTeam\PhpIoCli\AbstractPrompt;
use AlfacodeTeam\PhpIoCli\Hooks;
use AlfacodeTeam\PhpIoCli\Depends\State;
use AlfacodeTeam\PhpIoCli\Depends\Input;

abstract class Component extends AbstractPrompt
{
    protected State $state;
    protected Input $input;

    /**
     * Pass hooks to the parent and ensure state is ready.
     */
    public function __construct(?Hooks $hooks = null)
    {
        parent::__construct($hooks ?? new Hooks());
        $this->state = new State();
        $this->input = new Input();
    }

    /**
     * Standardizes the boot process for all prompts.
     */
    public final function mount(): void
    {
        // Setup is the "User Land" hook for subclasses
        $this->setup();
    }

    /**
     * Concrete prompts will define their logic here.
     */
    abstract protected function setup(): void;

    abstract public function render(): void;

    /**
     * Proxies the key update to the Input handler.
     */
    public function update(string $key): void
    {
        $this->input->handle($key, $this->state);
        
        // Every keypress marks the UI as dirty by default to trigger a re-render
        $this->context->markDirty();
    }

    abstract public function resolve(): mixed;

    public function destroy(): void 
    {
        // Default: clear line on exit to leave a clean terminal
        echo "\r\033[2K";
    }
}