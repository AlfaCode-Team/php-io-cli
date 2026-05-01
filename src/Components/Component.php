<?php
namespace AlfacodeTeam\PhpIoCli\Components;

use AlfacodeTeam\PhpIoCli\AbstractPrompt;
use AlfacodeTeam\PhpIoCli\RenderContext;
use AlfacodeTeam\PhpIoCli\Depends\State;
use AlfacodeTeam\PhpIoCli\Depends\Input;

abstract class Component extends AbstractPrompt
{
    protected State $state;
    protected Input $input;
    protected RenderContext $context;

    public function __construct()
    {
        parent::__construct();
    }

    /* =========================================================
       FINAL RUN IS INHERITED FROM AbstractPrompt
    ========================================================= */

    public function mount(): void
    {
        $this->state = new State();
        $this->input = new Input();
        $this->context = new RenderContext();

        $this->setup();
    }

    /* =========================================================
       NEW CLEAN HOOK (replaces Component mount duplication)
    ========================================================= */

    abstract protected function setup(): void;

    abstract public function render(): void;

    abstract public function update(string $key): void;

    abstract public function resolve(): mixed;

    public function destroy(): void {}
}