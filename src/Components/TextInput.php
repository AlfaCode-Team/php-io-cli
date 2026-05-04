<?php

declare(strict_types=1);

namespace AlfacodeTeam\PhpIoCli\Components;

use AlfacodeTeam\PhpIoCli\Depends\Colors;
use AlfacodeTeam\PhpIoCli\Depends\Key;
use AlfacodeTeam\PhpIoCli\Depends\State;
use AlfacodeTeam\PhpIoCli\Depends\Terminal;

/**
 * Enterprise TextInput
 * Features: Inline validation, placeholder, default values, and virtual caret.
 */
final class TextInput extends Component
{
    private string $placeholder = '';

    private string $defaultValue = '';

    private int $lastLines = 0;

    /** @var (callable(string): ?string)|null */
    private $validator = null;

    public function __construct(private string $question)
    {
        parent::__construct();
    }

    /* =========================================================
       LIFECYCLE
    ========================================================= */

    protected function setup(): void
    {
        $this->state->batch([
            'value' => '',
            'cursor' => 0,
            'done' => false,
            'error' => null,
        ]);

        // Key: Typing
        $this->input->fallback(static function (State|string $state, string $key): void {
            if (Key::isPrintable($key)) {
                $cur = (int) $state->cursor;
                $value = (string) $state->value;
                $state->value = mb_substr($value, 0, $cur) . $key . mb_substr($value, $cur);
                $state->cursor = $cur + 1;
                $state->error = null;
            }
        });

        // Key: Navigation
        $this->input->bind('LEFT', static fn(State|string $s) => $s->decrement('cursor'));
        $this->input->bind('RIGHT', static fn(State|string $s) => $s->increment('cursor', mb_strlen((string) $s->value)));
        $this->input->bind('HOME', static function (State|string $s): void {
            $s->cursor = 0;
        });
        $this->input->bind('END', static function (State|string $s): void {
            $s->cursor = mb_strlen((string) $s->value);
        });

        // Key: Deletion
        $this->input->bind('BACKSPACE', static function (State|string $state): void {
            $cur = (int) $state->cursor;
            if ($cur === 0) {
                return;
            }
            $state->value = mb_substr((string) $state->value, 0, $cur - 1) . mb_substr((string) $state->value, $cur);
            $state->cursor = $cur - 1;
            $state->error = null;
        });

        $this->input->bind('DELETE', static function (State|string $state): void {
            $cur = (int) $state->cursor;
            $value = (string) $state->value;
            if ($cur >= mb_strlen($value)) {
                return;
            }
            $state->value = mb_substr($value, 0, $cur) . mb_substr($value, $cur + 1);
        });

        // Key: Submission
        $this->input->bind('ENTER', function (State|string $state): void {
            $value = (string) $state->value;

            if ($value === '' && $this->defaultValue !== '') {
                $value = $this->defaultValue;
                $state->value = $value;
            }

            if ($this->validator !== null) {
                $err = ($this->validator)($value);
                if ($err !== null) {
                    $state->error = $err;

                    return;
                }
            }

            $state->done = true;
            $this->stop();
        });
    }

    /* --- Fluent Builders --- */

    public function placeholder(string $text): self
    {
        $this->placeholder = $text;

        return $this;
    }

    public function default(string $value): self
    {
        $this->defaultValue = $value;

        return $this;
    }

    public function validate(callable $validator): self
    {
        $this->validator = $validator;

        return $this;
    }

    /* =========================================================
       RENDER
    ========================================================= */

    public function render(): void
    {
        // Flicker prevention
        if ($this->lastLines > 0) {
            Terminal::moveCursorUp($this->lastLines);
        }

        Terminal::hideCursor();

        $value = (string) $this->state->value;
        $cursor = (int) $this->state->cursor;
        $error = $this->state->error;
        $done = (bool) $this->state->done;

        $lines = [];

        // Line 1: Question
        $questionMark = $done ? Colors::success('') : Colors::wrap('? ', Colors::CYAN);
        $lines[] = $questionMark . Colors::wrap($this->question, Colors::BOLD);

        if (!$done) {
            // Line 2: Input Area
            $before = mb_substr($value, 0, $cursor);
            $at = mb_substr($value, $cursor, 1);
            $after = mb_substr($value, $cursor + 1);
            $char = ($at !== '') ? $at : ' ';

            // Block Cursor using Inverse Video ANSI
            $cursorAnsi = Colors::wrap($char, [Colors::BOLD, "\033[7m"]);

            $displayText = Colors::wrap($before, Colors::YELLOW) . $cursorAnsi . Colors::wrap($after, Colors::YELLOW);

            // Handle Placeholder
            if ($value === '' && $cursor === 0) {
                $ph = $this->placeholder ?: ($this->defaultValue ? "({$this->defaultValue})" : 'type...');
                // Combine cursor with placeholder for better UX
                $displayText = $cursorAnsi . Colors::wrap(mb_substr($ph, 0), Colors::DIM);
            }

            $lines[] = Colors::wrap('  › ', Colors::GRAY) . $displayText;

            // Line 3: Feedback (Error or Default)
            if ($error !== null) {
                $lines[] = Colors::wrap("  ✘ {$error}", Colors::RED);
            } elseif ($this->defaultValue !== '' && $value === '') {
                $lines[] = Colors::muted("    Default: {$this->defaultValue}");
            } else {
                $lines[] = '';
            }

            // Line 4: Help
            $lines[] = Colors::muted('    ← → move  •  HOME/END  •  ENTER submit');
        } else {
            // Finished: Show result and collapse
            $lines[] = Colors::wrap('  › ', Colors::GRAY) . Colors::wrap($value, Colors::GREEN);
        }

        // Output logic
        foreach ($lines as $line) {
            Terminal::clearLine();
            echo $line . PHP_EOL;
        }

        $this->lastLines = count($lines);
    }

    public function destroy(): void
    {
        Terminal::showCursor();
        parent::destroy();
    }

    public function resolve(): mixed
    {
        $value = (string) $this->state->value;

        return ($value !== '') ? $value : $this->defaultValue;
    }
}
