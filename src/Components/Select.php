<?php
namespace AlfacodeTeam\PhpIoCli\Components;

use AlfacodeTeam\PhpIoCli\Depends\Colors;
use AlfacodeTeam\PhpIoCli\Depends\Fuzzy;
use AlfacodeTeam\PhpIoCli\Depends\Terminal;
final class Select extends Component
{
    private array $choices;
    private int $index = 0;
    private string $search = '';
    private mixed $result = null;

    public function __construct(string $question, array $choices)
    {
        $this->choices = $choices;
        parent::__construct();
    }

    protected function setup(): void
    {
        $this->state->batch([
            'done' => false,
        ]);
    }

    public function render(): void
    {
        Terminal::clearScreen();

        echo Colors::wrap("Select option", Colors::BOLD) . PHP_EOL;

        foreach ($this->filtered() as $i => $item) {

            $active = $i === $this->index;

            echo $active
                ? Colors::wrap("› {$item}", [Colors::GREEN, Colors::BOLD])
                : "  " . Colors::wrap($item, Colors::GRAY);

            echo PHP_EOL;
        }
    }

    public function update(string $key): void
    {
        $items = $this->filtered();

        match ($key) {

            'UP' => $this->index = max(0, $this->index - 1),
            'DOWN' => $this->index = min(count($items) - 1, $this->index + 1),

            'ENTER' => $this->submit($items),

            'BACKSPACE' => $this->search = mb_substr($this->search, 0, -1),

            default => $this->handleInput($key),
        };
    }

    public function resolve(): mixed
    {
        return $this->result;
    }

    private function handleInput(string $key): void
    {
        if (strlen($key) === 1 && ctype_print($key)) {
            $this->search .= $key;
            $this->index = 0;
        }
    }

    private function submit(array $items): void
    {
        $this->result = $items[$this->index] ?? null;
        $this->stop();
    }

    private function filtered(): array
    {
        return Fuzzy::filter($this->choices, $this->search);
    }
}