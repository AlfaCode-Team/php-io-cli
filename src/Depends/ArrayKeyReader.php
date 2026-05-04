<?php

declare(strict_types=1);

namespace AlfacodeTeam\PhpIoCli\Depends;

use AlfacodeTeam\PhpIoCli\KeyReaderInterface;

/**
 * Test-friendly KeyReader that replays a fixed sequence of keypresses.
 *
 * Feed it the exact keys you want the component to receive, in order.
 * Once the queue is exhausted it returns an empty string on every
 * subsequent call (the event loop treats this as a no-op).
 *
 * Usage in tests
 * ──────────────
 *   $reader = new ArrayKeyReader(['DOWN', 'DOWN', 'ENTER']);
 *   $result = (new Select('Env', ['prod', 'staging', 'dev']))
 *       ->withKeyReader($reader)
 *       ->run();
 *   $this->assertSame('dev', $result);
 *
 * Keys are passed through Key::normalize() inside AbstractPrompt, so you
 * can supply either normalised names ('UP', 'ENTER') or raw escape bytes
 * ("\e[A", "\n") — both work.
 *
 * setUp() and tearDown() are intentional no-ops: there is no real terminal
 * to configure when running under PHPUnit.
 */
final class ArrayKeyReader implements KeyReaderInterface
{
    /** @var list<string> */
    private array $queue;

    private int $position = 0;

    /**
     * @param list<string> $keys Ordered sequence of keys to replay.
     */
    public function __construct(array $keys)
    {
        $this->queue = array_values($keys);
    }

    public function readKey(): string
    {
        if ($this->position >= count($this->queue)) {
            return '';
        }

        return $this->queue[$this->position++];
    }

    /**
     * Returns true when all queued keys have been consumed.
     */
    public function exhausted(): bool
    {
        return $this->position >= count($this->queue);
    }

    /**
     * Rewinds the reader so the same sequence can be replayed.
     */
    public function reset(): void
    {
        $this->position = 0;
    }

    /** No-op — no terminal to configure in test context. */
    public function setUp(): void {}

    /** No-op — no terminal to restore in test context. */
    public function tearDown(): void {}
}
