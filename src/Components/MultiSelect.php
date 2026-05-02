<?php
declare(strict_types=1);

namespace AlfacodeTeam\PhpIoCli\Components;

use AlfacodeTeam\PhpIoCli\Depends\Colors;
use AlfacodeTeam\PhpIoCli\Depends\Key;
use AlfacodeTeam\PhpIoCli\Depends\Terminal;

/**
 * Multiple selection list using Spacebar to toggle.
 * 
 * // $items = (new MultiSelect('Select features:', ['Auth', 'API', 'Docker']))->run();
 */
final class MultiSelect extends Component
{
    private int $lastLines = 0;

    public function __construct(private string $question, private array $choices)
    {
        parent::__construct();
    }

    protected function setup(): void
    {
        $this->state->batch(['index' => 0, 'selected' => [], 'done' => false]);

        $this->input->bind('UP', fn($s) => $s->decrement('index'));
        $this->input->bind('DOWN', fn($s) => $s->increment('index', count($this->choices) - 1));
        $this->input->bind(' ', function($s) {
            $val = $this->choices[$s->index];
            $s->toggle('selected', $val);
        });
        $this->input->bind('ENTER', function($s) {
            $s->done = true;
            $this->stop();
        });
    }

    public function render(): void
    {
        if ($this->lastLines > 0) Terminal::moveCursorUp($this->lastLines);
        
        $lines = [Colors::wrap('? ', Colors::CYAN) . Colors::wrap($this->question, Colors::BOLD)];

        if (!$this->state->done) {
            foreach ($this->choices as $i => $item) {
                $active = $i === $this->state->index;
                $checked = in_array($item, (array)$this->state->selected, true);
                
                $pointer = $active ? Colors::wrap('› ', Colors::CYAN) : '  ';
                $box = $checked ? Colors::wrap('⬢ ', Colors::GREEN) : Colors::wrap('⬡ ', Colors::GRAY);
                $label = $active ? Colors::wrap($item, Colors::YELLOW) : Colors::wrap($item, Colors::GRAY);

                $lines[] = " {$pointer}{$box}{$label}";
            }
            $lines[] = Colors::muted('  ↑↓ move • Space toggle • Enter submit');
        } else {
            $lines[] = Colors::wrap('  › ', Colors::GRAY) . Colors::wrap(implode(', ', (array)$this->state->selected), Colors::GREEN);
        }

        foreach ($lines as $line) {
            Terminal::clearLine();
            echo $line . PHP_EOL;
        }
        $this->lastLines = count($lines);
    }

     public function resolve(): array
    {
        return (array)$this->state->selected;
    }
}