<?php 
declare(strict_types=1);

namespace AlfacodeTeam\PhpIoCli;

final class Hooks
{
    /**
     * @var array<string, array<int, callable>>
     */
    private array $listeners = [];

    /* =========================================================
       REGISTER LISTENERS
    ========================================================= */

    public function on(string $event, callable $listener): self
    {
        $this->listeners[$event][] = $listener;

        return $this;
    }

    /* =========================================================
       REMOVE LISTENERS
    ========================================================= */

    public function off(string $event, ?callable $listener = null): self
    {
        if (!isset($this->listeners[$event])) {
            return $this;
        }

        if ($listener === null) {
            unset($this->listeners[$event]);
            return $this;
        }

        $this->listeners[$event] = array_values(array_filter(
            $this->listeners[$event],
            fn ($l) => $l !== $listener
        ));

        return $this;
    }

    /* =========================================================
       DISPATCH EVENT
    ========================================================= */

    public function dispatch(string $event, mixed $payload = null): void
    {
        if (!isset($this->listeners[$event])) {
            return;
        }

        foreach ($this->listeners[$event] as $listener) {
            $listener($payload, $event, $this);
        }
    }

    /* =========================================================
       DISPATCH WITH STOP PROPAGATION
    ========================================================= */

    public function dispatchUntil(string $event, mixed $payload = null): mixed
    {
        if (!isset($this->listeners[$event])) {
            return null;
        }

        foreach ($this->listeners[$event] as $listener) {
            $result = $listener($payload, $event, $this);

            if ($result !== null) {
                return $result;
            }
        }

        return null;
    }

    /* =========================================================
       HAS LISTENERS
    ========================================================= */

    public function has(string $event): bool
    {
        return !empty($this->listeners[$event]);
    }

    /* =========================================================
       CLEAR ALL
    ========================================================= */

    public function clear(): void
    {
        $this->listeners = [];
    }
}