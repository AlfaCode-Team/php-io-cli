<?php

declare(strict_types=1);

namespace AlfacodeTeam\PhpIoCli;

use Closure;

final class Hooks
{
    /** @var array<string, array<int, Closure>> */
    private array $listeners = [];

    /**
     * Subscribe to an event.
     */
    public function on(string $event, callable $listener): self
    {
        $this->listeners[$event][] = Closure::fromCallable($listener);
        return $this;
    }

    /**
     * Subscribe to an event once, then automatically unsubscribe.
     */
    public function once(string $event, callable $listener): self
    {
        $wrapper = function (mixed $payload, string $event, Hooks $hooks) use ($listener, &$wrapper) {
            $this->off($event, $wrapper);
            return $listener($payload, $event, $hooks);
        };

        return $this->on($event, $wrapper);
    }

    /**
     * Unsubscribe from an event.
     */
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
            fn($l) => $l !== $listener
        ));

        return $this;
    }

    /**
     * Dispatch standard event.
     */
    public function dispatch(string $event, mixed $payload = null): void
    {
        foreach ($this->listeners[$event] ?? [] as $listener) {
            $listener($payload, $event, $this);
        }
    }

    /**
     * Dispatch until a listener returns a non-null value (Chain of Responsibility).
     */
    public function dispatchUntil(string $event, mixed $payload = null): mixed
    {
        foreach ($this->listeners[$event] ?? [] as $listener) {
            $result = $listener($payload, $event, $this);

            if ($result !== null) {
                return $result;
            }
        }

        return null;
    }
}
