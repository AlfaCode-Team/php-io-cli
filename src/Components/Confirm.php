<?php

declare(strict_types=1);

namespace AlfacodeTeam\PhpIoCli\Components;

use AlfacodeTeam\PhpIoCli\Depends\Colors;
use AlfacodeTeam\PhpIoCli\Depends\Terminal;

/**
 * Boolean confirmation component.
 *
 * // $confirmed = (new Confirm('Do you want to proceed?'))->run();
 */
final class Confirm extends Component
{
    private int $lastLines = 0;

    public function __construct(
        private string $question,
        private bool $default = true,
    ) {
        parent::__construct();
    }

    protected function setup(): void
    {
        $this->state->confirmed = $this->default;

        $this->input->bind(['y', 'Y'], static fn($s) => $s->confirmed = true);
        $this->input->bind(['n', 'N'], static fn($s) => $s->confirmed = false);
        $this->input->bind(['LEFT', 'RIGHT'], static fn($s) => $s->confirmed = !$s->confirmed);
        $this->input->bind('ENTER', fn() => $this->stop());
    }

    public function render(): void
    {
        if ($this->lastLines > 0) {
            Terminal::moveCursorUp($this->lastLines);
        }

        $active = $this->state->confirmed;

        $yes = $active ? Colors::wrap(' Yes ', [Colors::BG_CYAN, Colors::BLACK]) : Colors::wrap(' Yes ', Colors::GRAY);
        $no = !$active ? Colors::wrap(' No ', [Colors::BG_CYAN, Colors::BLACK]) : Colors::wrap(' No ', Colors::GRAY);

        $lines = [
            Colors::wrap('? ', Colors::CYAN) . Colors::wrap($this->question, Colors::BOLD),
            "  {$yes} {$no}",
            Colors::muted('  ← → to toggle  •  Enter to confirm'),
        ];

        foreach ($lines as $line) {
            Terminal::clearLine();
            echo $line . PHP_EOL;
        }
        $this->lastLines = count($lines);
    }

    public function resolve(): bool
    {
        return (bool) $this->state->confirmed;
    }
}
