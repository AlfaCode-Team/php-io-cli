<?php

declare(strict_types=1);

namespace AlfacodeTeam\PhpIoCli\Depends;

final class Input
{
    /** @var array<string, array<int, \Closure>> */
    private array $bindings = [];

    /**  */
    private \Closure|null $fallback = null;

    /**
     * Bind a handler to one or multiple keys.
     *
     * @param string|array<string> $keys
     * @param \Closure(State, string): (void|bool) $handler
     */
    public function bind(string|array $keys, \Closure $handler): self
    {
        foreach ((array) $keys as $key) {
            $this->bindings[$key][] = $handler;
        }

        return $this;
    }

    /**
     * Define what happens if no specific binding matches the key.
     *
     * @param \Closure(State, string): void $handler
     */
    public function fallback(\Closure $handler): self
    {
        $this->fallback = $handler;

        return $this;
    }

    /**
     * Clear all bindings for specific keys.
     */
    public function unbind(string|array $keys): self
    {
        foreach ((array) $keys as $key) {
            unset($this->bindings[$key]);
        }

        return $this;
    }

    /**
     * Process a key press against the registered bindings.
     */
    public function handle(string $key, State $state): void
    {
        // 1. Normalization: Map common hex/escape codes to readable strings
        $normalizedKey = Key::normalize($key);

        if (isset($this->bindings[$normalizedKey])) {
            foreach ($this->bindings[$normalizedKey] as $handler) {
                /**
                 * If a handler returns (bool) false, we stop propagation.
                 * This prevents multiple handlers from reacting to the same key.
                 */
                if ($handler($state, $normalizedKey) === false) {
                    break;
                }
            }

            return;
        }

        // 2. Fallback (usually used for typing characters into an input)
        if ($this->fallback) {
            ($this->fallback)($state, $key);
        }
    }
}
