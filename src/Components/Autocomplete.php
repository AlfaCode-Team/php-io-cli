<?php

declare(strict_types=1);

namespace AlfacodeTeam\PhpIoCli\Components;

use AlfacodeTeam\PhpIoCli\Depends\Colors;
use AlfacodeTeam\PhpIoCli\Depends\Fuzzy;
use AlfacodeTeam\PhpIoCli\Depends\Key;
use AlfacodeTeam\PhpIoCli\Depends\Terminal;

/**
 * Text input with live fuzzy-search dropdown suggestions.
 *
 * Usage:
 *   $lang = (new Autocomplete('Pick a language', ['PHP', 'Python', 'Go', 'Rust']))->run();
 * Enterprise Autocomplete Prompt
 * Reactive text input with ANSI-safe dropdown suggestions.
 */
final class Autocomplete extends Component
{
    private int $lastLines = 0;
    private int $maxSuggestions = 6;
    private int $minDropdownWidth = 40;

    public function __construct(
        private string $question,
        private array $suggestions
    ) {
        parent::__construct();
    }

    public function maxSuggestions(int $n): self
    {
        $this->maxSuggestions = $n;
        return $this;
    }

    protected function setup(): void
    {
        $this->state->batch([
            'value'    => '',
            'cursor'   => 0,
            'focused'  => 0,
            'done'     => false,
        ]);

        // 1. Text Input Logic (Multibyte Safe)
        $this->input->fallback(function ($s, $key): void {
            if (Key::isPrintable($key)) {
                $cur = (int) $s->cursor;
                $val = (string) $s->value;

                $s->value = mb_substr($val, 0, $cur) . $key . mb_substr($val, $cur);
                $s->cursor = $cur + 1;
                $s->focused = 0; // Reset focus on type
            }
        });

        // 2. Navigation & Deletion
        $this->input->bind('BACKSPACE', function ($s): void {
            $cur = (int) $s->cursor;
            if ($cur === 0) {
                return;
            }

            $val = (string) $s->value;
            $s->value = mb_substr($val, 0, $cur - 1) . mb_substr($val, $cur);
            $s->cursor = $cur - 1;
            $s->focused = 0;
        });

        $this->input->bind('LEFT', fn($s) => $s->decrement('cursor'));
        $this->input->bind('RIGHT', fn($s) => $s->increment('cursor', mb_strlen((string) $s->value)));

        $this->input->bind('UP', function ($s): void {
            $s->decrement('focused');
        });

        $this->input->bind('DOWN', function ($s): void {
            $count = count($this->getFiltered());
            $s->increment('focused', $count > 0 ? $count - 1 : 0);
        });

        // 3. Selection Logic
        $this->input->bind('TAB', function ($s): void {
            $filtered = $this->getFiltered();
            if (!empty($filtered)) {
                $selection = $filtered[(int) $s->focused] ?? $filtered[0];
                $s->value = $selection;
                $s->cursor = mb_strlen($selection);
            }
        });

        $this->input->bind('ENTER', function ($s): void {
            $filtered = $this->getFiltered();
            $val = (string) $s->value;

            // If a suggestion is highlighted and different from current text, "fill" it first
            if (!empty($filtered) && $val !== '') {
                $highlighted = $filtered[(int) $s->focused] ?? null;
                if ($highlighted && $highlighted !== $val) {
                    $s->value = $highlighted;
                    $s->cursor = mb_strlen($highlighted);
                    return;
                }
            }

            $s->done = true;
            $this->stop();
        });
    }

    public function render(): void
    {
        if ($this->lastLines > 0) {
            Terminal::moveCursorUp($this->lastLines);
        }

        $val      = (string) $this->state->value;
        $cur      = (int) $this->state->cursor;
        $focused  = (int) $this->state->focused;
        $filtered = $this->getFiltered();
        $lines    = [];

        // HEADER
        $lines[] = Colors::wrap('? ', Colors::CYAN) . Colors::wrap($this->question, Colors::BOLD);

        if (!(bool) $this->state->done) {
            // INPUT LINE WITH VIRTUAL CARET
            $before = mb_substr($val, 0, $cur);
            $at     = mb_substr($val, $cur, 1);
            $after  = mb_substr($val, $cur + 1);

            // Caret styling (Reverse Video)
            $caretChar = ($at === '') ? ' ' : $at;
            $caret     = Colors::wrap($caretChar, ["\033[7m", Colors::YELLOW]);

            $lines[] = Colors::wrap('  › ', Colors::GRAY) . Colors::wrap($before, Colors::YELLOW) . $caret . Colors::wrap($after, Colors::YELLOW);

            // DYNAMIC DROPDOWN
            if (!empty($filtered) && $val !== '') {
                $lines = array_merge($lines, $this->renderDropdown($filtered, $focused));
            } else {
                $lines[] = Colors::muted('    (Type to search suggestions)');
            }

            $lines[] = Colors::muted('    ↑↓ nav • TAB complete • ENTER confirm');
        } else {
            // FINAL STATE
            $lines[] = Colors::wrap('  ✔ ', Colors::GREEN) . Colors::wrap($val, Colors::BOLD);
        }

        // ATOMIC RENDER
        $output = "";
        foreach ($lines as $line) {
            $output .= "\r\033[2K" . $line . PHP_EOL;
        }
        echo $output;

        $this->lastLines = count($lines);
    }

    private function renderDropdown(array $filtered, int $focused): array
    {
        $visible = array_slice($filtered, 0, $this->maxSuggestions);

        // Calculate dynamic width based on content
        $width = $this->minDropdownWidth;
        foreach ($visible as $item) {
            $width = max($width, mb_strlen(Colors::strip($item)) + 6);
        }

        $lines = [];
        $t = Colors::GRAY; // Border style

        $lines[] = Colors::wrap('    ┌' . str_repeat('─', $width) . '┐', $t);

        foreach ($visible as $i => $item) {
            $active = $i === $focused;

            $icon = $active ? Colors::wrap('› ', Colors::GREEN) : '  ';
            $text = $active ? Colors::wrap($item, [Colors::YELLOW, Colors::BOLD]) : Colors::muted($item);

            // Padded line construction
            $contentLen = mb_strlen(Colors::strip($item));
            $padding    = str_repeat(' ', max(0, $width - $contentLen - 4));

            $lines[] = Colors::wrap('    │ ', $t) . $icon . $text . $padding . Colors::wrap(' │', $t);
        }

        if (count($filtered) > $this->maxSuggestions) {
            $more = count($filtered) - $this->maxSuggestions;
            $lines[] = Colors::wrap('    │ ', $t) . Colors::muted("  … and {$more} more ") . str_repeat(' ', $width - 17) . Colors::wrap(' │', $t);
        }

        $lines[] = Colors::wrap('    └' . str_repeat('─', $width) . '┘', $t);

        return $lines;
    }

    public function resolve(): mixed
    {
        return $this->state->value;
    }

    private function getFiltered(): array
    {
        return Fuzzy::filter($this->suggestions, (string) $this->state->value);
    }
}
