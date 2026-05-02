<?php
declare(strict_types=1);

namespace AlfacodeTeam\PhpIoCli\Components;

use AlfacodeTeam\PhpIoCli\Depends\Colors;
use AlfacodeTeam\PhpIoCli\Depends\Fuzzy;
use AlfacodeTeam\PhpIoCli\Depends\Key;

final class Select extends Component
{
    private int $lastRenderedLines = 0;

    public function __construct(
        private string $question,
        private array $choices
    ) {
        parent::__construct();
    }

    /**
     * Use the reactive State and Input classes you built.
     */
    protected function setup(): void
    {
        $this->state->batch([
            'index' => 0,
            'search' => '',
            'choices' => $this->choices,
            'result' => null,
        ]);

        // Bind navigation using the Input dispatcher
        $this->input->bind('UP', function($state) {
            $state->decrement('index');
        });

        $this->input->bind('DOWN', function($state) {
            $count = count($this->getFiltered());
            $state->increment('index', $count > 0 ? $count - 1 : 0);
        });

        $this->input->bind('ENTER', function($state) {
            $filtered = $this->getFiltered();
            $state->result = $filtered[$state->index] ?? null;
            $this->stop();
        });

        $this->input->bind('BACKSPACE', function($state) {
            $state->search = mb_substr($state->search, 0, -1);
            $state->index = 0;
        });

        // Handle typing (Fallback)
        $this->input->fallback(function($state, $key) {
            if (Key::isPrintable($key)) {
                $state->search .= $key;
                $state->index = 0;
            }
        });
    }

    public function render(): void
    {
        // 1. Instead of clearScreen (flicker), move cursor back up
        if ($this->lastRenderedLines > 0) {
            echo "\033[{$this->lastRenderedLines}A";
        }

        $lines = [];

        // 2. Build the UI
        $lines[] = Colors::wrap("? ", Colors::CYAN) . Colors::wrap($this->question, Colors::BOLD);
        $lines[] = Colors::wrap("› ", Colors::GRAY) . Colors::wrap($this->state->search ?: 'Type to search...', $this->state->search ? Colors::YELLOW : Colors::DIM);
        $lines[] = "";

        $filtered = $this->getFiltered();

        if (empty($filtered)) {
            $lines[] = Colors::wrap("  No results found.", Colors::RED);
        } else {
            foreach ($filtered as $i => $item) {
                $active = $i === $this->state->index;
                $lines[] = $active
                    ? Colors::wrap(" › {$item}", [Colors::GREEN, Colors::BOLD])
                    : "   " . Colors::wrap($item, Colors::GRAY);
            }
        }

        // 3. Clear and print (Buffer-style to prevent tearing)
        $output = "";
        foreach ($lines as $line) {
            $output .= "\033[2K\r" . $line . PHP_EOL;
        }

        echo $output;
        $this->lastRenderedLines = count($lines);
    }

    public function resolve(): mixed
    {
        return $this->state->result;
    }

    private function getFiltered(): array
    {
        return Fuzzy::filter($this->choices, $this->state->search);
    }
}