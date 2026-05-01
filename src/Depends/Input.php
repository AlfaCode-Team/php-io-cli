<?php
declare(strict_types=1);

namespace AlfacodeTeam\PhpIoCli\Depends;
final class Input
{
    private array $bindings = [];
    /** @var callable|null */
    private $fallback = null;

    public function bind(string|array $keys, callable $handler): self
    {
        foreach ((array) $keys as $key) {
            $this->bindings[$key][] = $handler;
        }

        return $this;
    }

    public function fallback(callable $handler): self
    {
        $this->fallback = $handler;
        return $this;
    }

    public function handle(string $key, State $state): void
    {
        if (isset($this->bindings[$key])) {
            foreach ($this->bindings[$key] as $handler) {
                $handler($state, $key);
            }
            return;
        }

        if ($this->fallback) {
            ($this->fallback)($state, $key);
        }
    }
}