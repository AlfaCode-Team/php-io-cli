<?php
declare(strict_types=1);

namespace AlfacodeTeam\PhpIoCli\Components;

use AlfacodeTeam\PhpIoCli\Depends\Colors;
use AlfacodeTeam\PhpIoCli\Depends\Fuzzy;
use AlfacodeTeam\PhpIoCli\Depends\Key;
use AlfacodeTeam\PhpIoCli\Depends\Terminal;

/**
 * Interactive search selection component.
 * 
 * Usage:
 * // $color = (new Select('Choose a color', ['Red', 'Blue', 'Green']))->run();
 */
final class Select extends Component
{
    private int $lastLines = 0;

    public function __construct(
        private string $question,
        private array $choices
    ) {
        parent::__construct();
    }

    /* =========================================================
       LIFECYCLE
    ========================================================= */

    protected function setup(): void
    {
        $this->state->batch([
            'index'   => 0,
            'search'  => '',
            'choices' => $this->choices,
            'result'  => null,
            'done'    => false,
        ]);

        // Navigation
        $this->input->bind('UP', function ($state) {
            $state->decrement('index');
        });

        $this->input->bind('DOWN', function ($state) {
            $count = count($this->getFiltered());
            $state->increment('index', $count > 0 ? $count - 1 : 0);
        });

        // Search logic
        $this->input->bind('BACKSPACE', function ($state) {
            $state->search = mb_substr((string)$state->search, 0, -1);
            $state->index = 0;
        });

        // Selection
        $this->input->bind('ENTER', function ($state) {
            $filtered = $this->getFiltered();
            if (empty($filtered)) {
                return;
            }
            $state->result = $filtered[$state->index] ?? null;
            $state->done = true;
            $this->stop();
        });

        // Typing fallback
        $this->input->fallback(function ($state, $key) {
            if (Key::isPrintable($key)) {
                $state->search .= $key;
                $state->index = 0; // Reset index on new search
            }
        });
    }

    /* =========================================================
       RENDER
    ========================================================= */

    public function render(): void
    {
        // 1. Move cursor back up (Anti-flicker)
        if ($this->lastLines > 0) {
            Terminal::moveCursorUp($this->lastLines);
        }

        Terminal::hideCursor();

        $done     = (bool) $this->state->done;
        $search   = (string) $this->state->search;
        $filtered = $this->getFiltered();
        $lines    = [];

        // Question Line
        $lines[] = Colors::wrap('? ', Colors::CYAN) . Colors::wrap($this->question, Colors::BOLD);

        if (!$done) {
            // Search Bar Line
            $searchLabel = Colors::wrap('› ', Colors::GRAY);
            $searchText  = $search !== '' 
                ? Colors::wrap($search, Colors::YELLOW) . Colors::wrap('▊', Colors::CYAN) 
                : Colors::wrap('Type to filter...', Colors::DIM);
            
            $lines[] = "  {$searchLabel}{$searchText}";
            $lines[] = ""; // Spacer

            // List Items
            if (empty($filtered)) {
                $lines[] = Colors::wrap("    ✘ No matches found", Colors::RED);
            } else {
                // Windowing (Enterprise scale: show 8 items max)
                $windowSize = 8;
                $total = count($filtered);
                $start = (int) max(0, min($this->state->index - floor($windowSize / 2), $total - $windowSize));
                
                foreach (array_slice($filtered, $start, $windowSize) as $i => $item) {
                    $realIndex = $start + $i;
                    $active = $realIndex === $this->state->index;

                    if ($active) {
                        $lines[] = Colors::wrap("  › {$item}", [Colors::GREEN, Colors::BOLD]);
                    } else {
                        $lines[] = Colors::wrap("    {$item}", Colors::GRAY);
                    }
                }
                
                // Scroll indicators for large lists
                if ($total > $windowSize) {
                    $lines[] = Colors::muted(sprintf("    (Showing %d of %d)", $windowSize, $total));
                }
            }
            
            $lines[] = "";
            $lines[] = Colors::muted("    ↑↓ navigate  •  ENTER select  •  Type to filter");

        } else {
            // Collapse UI on finish
            $lines[] = Colors::wrap('  › ', Colors::GRAY) . Colors::wrap((string)$this->state->result, Colors::GREEN);
        }

        // 3. Clear and print
        foreach ($lines as $line) {
            Terminal::clearLine();
            echo $line . PHP_EOL;
        }

        $this->lastLines = count($lines);
    }

    /* =========================================================
       CLEANUP & RESOLVE
    ========================================================= */

    public function destroy(): void
    {
        Terminal::showCursor();
        parent::destroy();
    }

    public function resolve(): mixed
    {
        return $this->state->result;
    }

    private function getFiltered(): array
    {
        return Fuzzy::filter($this->choices, (string)$this->state->search);
    }
}