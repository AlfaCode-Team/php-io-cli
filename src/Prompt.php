<?php
declare(strict_types=1);
namespace AlfacodeTeam\PhpIoCli;

use AlfacodeTeam\PhpIoCli\Depends\Renderer;
use AlfacodeTeam\PhpIoCli\Depends\State;
use AlfacodeTeam\PhpIoCli\Depends\Terminal;


/* =========================================================
   PROMPT ENGINE
========================================================= */

final class Prompt
{
    public function __construct(
        private State $state,
        private Renderer $renderer
    ) {
    }

    public function run(): string|array
    {
        Terminal::enableRaw();

        try {
            $this->state->loading = true;
            $this->renderer->render($this->state);

            usleep(300000); // simulate async load
            $this->state->loading = false;

            while (!$this->state->done) {

                $this->renderer->render($this->state);

                $key = Terminal::readKey();
                $this->handle($key);
            }

        } finally {
            Terminal::disableRaw();
        }

        return $this->resolve();
    }

    private function handle(string $key): void
    {
        $filtered = $this->state->filtered();
        $count = count($filtered);

        switch ($key) {

            /* =========================
               NAVIGATION (arrow keys always work)
            ========================= */

            case "\033[A": // UP
                $this->state->index = max(0, $this->state->index - 1);
                break;

            case "\033[B": // DOWN
                $this->state->index = min($count - 1, $this->state->index + 1);
                break;

            /* =========================
               VIM NAVIGATION (only when search is empty)
            ========================= */

            case "k":
                if ($this->state->search === '') {
                    $this->state->index = max(0, $this->state->index - 1);
                } else {
                    $this->state->search .= 'k';
                    $this->state->index = 0;
                }
                break;

            case "j":
                if ($this->state->search === '') {
                    $this->state->index = min($count - 1, $this->state->index + 1);
                } else {
                    $this->state->search .= 'j';
                    $this->state->index = 0;
                }
                break;

            /* =========================
               SUBMIT
            ========================= */

            case "\n":
            case "\r":
                $this->state->done = true;
                break;

            /* =========================
               MULTI-SELECT TOGGLE
            ========================= */

            case " ":
                if ($this->state->multi) {
                    $this->toggle();
                }
                break;

            /* =========================
               BACKSPACE
            ========================= */

            case "\177":
            case "\x08":
                $this->state->search = mb_substr($this->state->search, 0, -1);
                $this->state->index = 0;
                break;

            /* =========================
               SEARCH INPUT
            ========================= */

            default:
                if (strlen($key) === 1 && ctype_print($key)) {
                    $this->state->search .= $key;
                    $this->state->index = 0;
                }
                break;
        }
    }

    private function toggle(): void
    {
        $value = $this->state->filtered()[$this->state->index];

        if (in_array($value, $this->state->selected, true)) {
            $this->state->selected = array_values(array_filter(
                $this->state->selected,
                fn($v) => $v !== $value
            ));
        } else {
            $this->state->selected[] = $value;
        }
    }

    private function resolve(): string|array
    {
        if ($this->state->multi) {
            return $this->state->selected;
        }

        return $this->state->filtered()[$this->state->index];
    }
}
