<?php
declare(strict_types=1);

namespace AlfacodeTeam\PhpIoCli;

/**
 * The public-facing contract for all interactive CLI components.
 */
interface IPromptComponent
{
    /**
     * Executes the component lifecycle and returns the resolved value.
     * 
     * @return mixed The result of the prompt (e.g., string, bool, array, or null)
     */
    public function run(): mixed;
}