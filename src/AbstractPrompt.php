<?php

declare(strict_types=1);

namespace AlfacodeTeam\PhpIoCli;

use AlfacodeTeam\PhpIoCli\Depends\Key;
use AlfacodeTeam\PhpIoCli\Depends\Colors;
use AlfacodeTeam\PhpIoCli\Depends\Terminal;
use AlfacodeTeam\PhpIoCli\Depends\RenderContext;
use Exception;

abstract class AbstractPrompt implements IPromptComponent, ILifecycle
{
    protected bool $running = false;
    protected RenderContext $context;

    public function __construct(
        protected Hooks $hooks = new Hooks()
    ) {
        $this->context = new RenderContext();
    }

    /* =========================================================
       ENGINE LOOP
    ========================================================= */

    public function run(): mixed
    {
        Terminal::enableRaw();
        $this->running = true;

        try {
            $this->mount();
            $this->dispatch('mount');

            while ($this->running) {
                if ($this->context->dirty) {
                    // Give subclasses (or an injected IRenderer) a chance to
                    // run beforeRender / afterRender hooks around render().
                    $this->beforeRenderHook();
                    $this->render();
                    $this->afterRenderHook();
                    $this->context->clear();
                    $this->dispatch('render');
                }

                $rawKey = Terminal::readKey();
                $key    = Key::normalize($rawKey);

                if ($key === 'CTRL_C') {
                    $this->handleCancel();
                    break;
                }

                $this->update($key);
                $this->dispatch('update', $key);
            }

            $result = $this->resolve();
            $this->dispatch('submit', $result);

            return $result;

        } catch (Exception $e) {
            $this->handleError($e);
            throw $e;
        } finally {
            $this->destroy();
            $this->dispatch('destroy');
            Terminal::disableRaw();
        }
    }

    /* =========================================================
       RENDER LIFECYCLE HOOKS
       Concrete subclasses may override these to delegate to an
       IRenderer without breaking the base run() contract.
    ========================================================= */

    /**
     * Called immediately before render() in the engine loop.
     * Override to invoke IRenderer::beforeRender() when using a renderer object.
     */
    protected function beforeRenderHook(): void {}

    /**
     * Called immediately after render() in the engine loop.
     * Override to invoke IRenderer::afterRender() when using a renderer object.
     */
    protected function afterRenderHook(): void {}

    /* =========================================================
       HELPERS
    ========================================================= */

    protected function handleCancel(): void
    {
        $this->stop();
        echo PHP_EOL . '  ' . Colors::error('Cancelled.') . PHP_EOL;
    }

    protected function handleError(Exception $e): void
    {
        echo PHP_EOL . '  ' . Colors::error('An error occurred.') . PHP_EOL;
    }

    protected function dispatch(string $event, mixed $payload = null): void
    {
        $this->hooks->dispatch($event, $payload);
    }

    protected function stop(): void
    {
        $this->running = false;
    }

    /* =========================================================
       ABSTRACT CONTRACT
    ========================================================= */

    abstract protected function resolve(): mixed;
    abstract public function mount(): void;
    abstract public function render(): void;
    abstract public function update(string $key): void;
    abstract public function destroy(): void;
}
