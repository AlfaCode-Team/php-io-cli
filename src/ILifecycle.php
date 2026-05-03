<?php

declare(strict_types=1);

namespace AlfacodeTeam\PhpIoCli;

/**
 * Defines the standard lifecycle for interactive CLI components.
 */
interface ILifecycle
{
    /**
     * Triggered once when the component is started.
     * Use this to initialize state, bindings, and resources.
     */
    public function mount(): void;

    /**
     * Triggered when the UI needs to be drawn to the terminal.
     */
    public function render(): void;

    /**
     * Triggered whenever a keypress is captured.
     *
     * @param string $key The normalized key name (e.g., 'UP', 'ENTER', 'a')
     */
    public function update(string $key): void;

    /**
     * Triggered once when the component finishes or is interrupted.
     * Use this to restore terminal state or hide cursors.
     */
    public function destroy(): void;
}
