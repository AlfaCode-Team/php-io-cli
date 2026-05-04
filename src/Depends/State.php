<?php

declare(strict_types=1);

namespace AlfacodeTeam\PhpIoCli\Depends;

/**
 * Reactive State Container.
 * Handles data storage, property watching, and CLI-specific state mutations.
 */
final class State
{
    /** @var array<string, mixed> */
    private array $data = [];

    /** @var array<string, array<int, \Closure>> */
    private array $watchers = [];

    public function __construct(array $initialData = [])
    {
        $this->data = $initialData;
    }

    /* --- Magic Access --- */

    public function __get(string $key): mixed
    {
        return $this->get($key);
    }

    public function __set(string $key, mixed $value): void
    {
        $this->set($key, $value);
    }

    /* --- Core Get/Set --- */

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function set(string $key, mixed $value): self
    {
        $old = $this->data[$key] ?? null;

        if ($old === $value) {
            return $this;
        }

        $this->data[$key] = $value;
        $this->notify($key, $value, $old);

        return $this;
    }

    /**
     * Set multiple keys at once without triggering intermediate renders.
     *
     * @param array<string, mixed> $values
     */
    public function batch(array $values): self
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value);
        }

        return $this;
    }

    /* --- CLI Navigation Helpers --- */

    public function increment(string $key, int $max): void
    {
        $current = (int) $this->get($key, 0);
        if ($current < $max) {
            $this->set($key, $current + 1);
        }
    }

    public function decrement(string $key): void
    {
        $current = (int) $this->get($key, 0);
        if ($current > 0) {
            $this->set($key, $current - 1);
        }
    }

    /**
     * Toggle a value in an array (useful for multi-select).
     */
    public function toggle(string $key, mixed $value): void
    {
        $current = (array) $this->get($key, []);
        $index = array_search($value, $current, true);

        if ($index === false) {
            $current[] = $value;
        } else {
            unset($current[$index]);
        }

        $this->set($key, array_values($current));
    }

    /**
     * Filter items by search query (case-insensitive substring match).
     * Returns all items if no search query is set.
     *
     * @return array<int|string, mixed>
     */
    public function filtered(): array
    {
        $items = (array) $this->get('items', []);
        $search = mb_strtolower((string) $this->get('search', ''));

        if ($search === '') {
            return $items;
        }

        return array_filter(
            $items,
            static fn($item) => mb_stripos((string) $item, $search) !== false,
        );
    }

    /* --- Reactivity --- */

    /**
     * @param \Closure(mixed $new, mixed $old, self $state): void $callback
     */
    public function watch(string $key, \Closure $callback): self
    {
        $this->watchers[$key][] = $callback;

        return $this;
    }

    private function notify(string $key, mixed $new, mixed $old): void
    {
        foreach ($this->watchers[$key] ?? [] as $cb) {
            $cb($new, $old, $this);
        }
    }
}
