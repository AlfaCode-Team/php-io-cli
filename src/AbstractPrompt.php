<?php
declare(strict_types=1);
namespace AlfacodeTeam\PhpIoCli;

use AlfacodeTeam\PhpIoCli\Depends\Key;
use AlfacodeTeam\PhpIoCli\Depends\Terminal;


/* =========================================================
   PROMPT ENGINE
========================================================= */

abstract class AbstractPrompt implements IPromptComponent, ILifecycle
{
    protected bool $running = false;

    public function __construct(
        protected Hooks $hooks = new Hooks()
    ) {}

    /* =========================================================
       MAIN LOOP (shared across all components)
    ========================================================= */

    public function run(): mixed
    {
        Terminal::enableRaw();

        $this->running = true;

        try {
            $this->mount();
            $this->dispatch('mount');

            while ($this->running) {

                $this->render();
                $this->dispatch('render');

                $key = Key::normalize(
                    Terminal::readKey()
                );

                $this->update($key);
                $this->dispatch('update', $key);
            }

            $result = $this->resolve();

            $this->dispatch('submit', $result);

            return $result;

        } finally {
            $this->destroy();
            $this->dispatch('destroy');

            Terminal::disableRaw();
        }
    }

    /* =========================================================
       EVENT DISPATCH
    ========================================================= */

    protected function dispatch(string $event, mixed $payload = null): void
    {
        $this->hooks->dispatch($event, $payload);
    }

    /* =========================================================
       CONTROL FLOW
    ========================================================= */

    protected function stop(): void
    {
        $this->running = false;
    }

    /* =========================================================
       ABSTRACT METHODS (must be implemented)
    ========================================================= */

    abstract protected function resolve(): mixed;

    abstract public function mount(): void;

    abstract public function render(): void;

    abstract public function update(string $key): void;

    abstract public function destroy(): void;
}