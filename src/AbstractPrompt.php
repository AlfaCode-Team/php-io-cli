<?php
declare(strict_types=1);

namespace AlfacodeTeam\PhpIoCli;

use AlfacodeTeam\PhpIoCli\Depends\Key;
use AlfacodeTeam\PhpIoCli\Depends\Terminal;
use AlfacodeTeam\PhpIoCli\Depends\RenderContext; // Assuming this is used now
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

    /**
     * The Engine Loop
     */
    public function run(): mixed
    {
        Terminal::enableRaw();
        $this->running = true;

        try {
            $this->mount();
            $this->dispatch('mount');

            while ($this->running) {
                // Only render if the state has actually changed
                if ($this->context->dirty) {
                    $this->render();
                    $this->context->clear();
                    $this->dispatch('render');
                }

                $rawKey = Terminal::readKey();
                $key = Key::normalize($rawKey);

                // Handle Global Exit (Ctrl+C)
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

    protected function handleCancel(): void
    {
        $this->stop();
        echo PHP_EOL . "  " . \AlfacodeTeam\PhpIoCli\Depends\Colors::error("Cancelled.") . PHP_EOL;
    }

    protected function handleError(Exception $e): void
    {
        // Ensure we are on a new line before the error prints
        echo PHP_EOL;
    }

    protected function dispatch(string $event, mixed $payload = null): void
    {
        $this->hooks->dispatch($event, $payload);
    }

    protected function stop(): void
    {
        $this->running = false;
    }

    /* --- Abstract Methods --- */
    abstract protected function resolve(): mixed;
    abstract public function mount(): void;
    abstract public function render(): void;
    abstract public function update(string $key): void;
    abstract public function destroy(): void;
}