<?php

declare(strict_types=1);

namespace AlfacodeTeam\PhpIoCli;

/**
 * Contract for reading a single keypress.
 *
 * Decoupling AbstractPrompt from Terminal::readKey() via this interface
 * allows components to be driven by any key source — a real TTY, a
 * pre-recorded input stream, an in-memory queue, or a test double —
 * without touching the reactive event loop.
 *
 * Built-in implementations
 * ────────────────────────
 *   {@see \AlfacodeTeam\PhpIoCli\Depends\TerminalKeyReader}  — wraps Terminal::readKey() (default)
 *   {@see \AlfacodeTeam\PhpIoCli\Depends\ArrayKeyReader}     — replays a fixed sequence (testing)
 *
 * Custom implementations
 * ──────────────────────
 * Implement this interface and inject via AbstractPrompt::withKeyReader():
 *
 *   $prompt->withKeyReader(new MyCustomKeyReader())->run();
 *
 * The reader MUST block until a key is available, just as a real TTY does,
 * so the event loop in AbstractPrompt::run() can yield correctly.
 * Returning an empty string is treated as a no-op key (no bindings fire).
 */
interface KeyReaderInterface
{
    /**
     * Block until a keypress is available and return the raw byte sequence.
     *
     * The returned string will be passed through Key::normalize() before
     * being dispatched to Input bindings, so implementations do not need
     * to normalise escape sequences themselves — just return exactly what
     * the source produced.
     *
     * @return string Raw key bytes, e.g. "\e[A" for the up arrow.
     */
    public function readKey(): string;

    /**
     * Called by AbstractPrompt before the event loop starts.
     *
     * Use this to enable raw mode, open a stream, or perform any other
     * one-time setup required by the source. The default TTY implementation
     * calls Terminal::enableRaw() here.
     */
    public function setUp(): void;

    /**
     * Called by AbstractPrompt in the finally block after the loop ends.
     *
     * Use this to restore terminal state, close streams, or clean up.
     * The default TTY implementation calls Terminal::disableRaw() here.
     * Must be idempotent — it may be called more than once.
     */
    public function tearDown(): void;
}
