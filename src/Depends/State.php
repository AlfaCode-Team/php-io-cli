<?php
declare(strict_types=1);

namespace AlfacodeTeam\PhpIoCli\Depends;

/* =========================================================
   STATE
========================================================= */

final class State
{
    private array $data = [];
    private array $watchers = [];

    /* =========================================================
       GET / SET
    ========================================================= */

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $old = $this->data[$key] ?? null;

        if ($old === $value) {
            return;
        }

        $this->data[$key] = $value;

        $this->notify($key, $value, $old);
    }

    public function all(): array
    {
        return $this->data;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    /* =========================================================
       BATCH UPDATE
    ========================================================= */

    public function batch(array $data): void
    {
        foreach ($data as $key => $value) {
            $this->set($key, $value);
        }
    }

    /* =========================================================
       WATCHERS (reactivity)
    ========================================================= */

    public function watch(string $key, callable $callback): void
    {
        $this->watchers[$key][] = $callback;
    }

    private function notify(string $key, mixed $new, mixed $old): void
    {
        foreach ($this->watchers[$key] ?? [] as $cb) {
            $cb($new, $old, $this);
        }
    }
}
