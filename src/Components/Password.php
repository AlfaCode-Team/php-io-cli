<?php

declare(strict_types=1);

namespace AlfacodeTeam\PhpIoCli\Components;

use AlfacodeTeam\PhpIoCli\Depends\Colors;
use AlfacodeTeam\PhpIoCli\Depends\Key;
use AlfacodeTeam\PhpIoCli\Depends\Terminal;

/**
 * Enterprise Password Input
 * Supports masked/plaintext toggle and live strength validation.
 */
final class Password extends Component
{
    private bool $strengthMeter = false;

    private int $lastLines = 0;

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
            'visible' => false,
            'done' => false,
        ]);

        // Capture characters
        $this->input->fallback(static function ($state, $key): void {
            if (Key::isPrintable($key)) {
                $state->value .= $key;
            }
        });

        $this->input->bind('BACKSPACE', static function ($state): void {
            $state->value = mb_substr((string) $state->value, 0, -1);
        });

        // TAB toggles visibility
        $this->input->bind('TAB', static function ($state): void {
            $state->visible = !(bool) $state->visible;
        });

        $this->input->bind('ENTER', function ($state): void {
            $state->done = true;
            $this->stop();
        });
    }

    public function showStrength(): self
    {
        $this->strengthMeter = true;

        return $this;
    }

    /* =========================================================
       RENDER
    ========================================================= */

    public function render(): void
    {
        // 1. Move cursor back to top of the component
        if ($this->lastLines > 0) {
            Terminal::moveCursorUp($this->lastLines);
        }

        Terminal::hideCursor();

        $value = (string) $this->state->value;
        $visible = (bool) $this->state->visible;
        $done = (bool) $this->state->done;
        $len = mb_strlen($value);

        $lines = [];

        // Question Line
        $lines[] = Colors::wrap('? ', Colors::CYAN)
            . Colors::wrap($this->question, Colors::BOLD)
            . ($done ? Colors::muted(' [hidden]') : '');

        if (!$done) {
            // Input Line
            $display = $visible ? $value : str_repeat('●', $len);
            $display .= Colors::wrap('▊', Colors::CYAN); // Block cursor character

            $lines[] = Colors::wrap('  › ', Colors::GRAY) . Colors::wrap($display, Colors::YELLOW);

            // Strength meter
            if ($this->strengthMeter && $len > 0) {
                $lines[] = '    ' . $this->buildStrengthBar($value);
            } else {
                $lines[] = '';
            }

            // Help Hint
            $toggle = $visible ? 'hide' : 'show';
            $lines[] = Colors::muted("    TAB {$toggle} password  •  ENTER submit");
        } else {
            // Collapse UI on finish to keep terminal clean
            $lines[] = Colors::success(" Password accepted ({$len} chars)");
        }

        // 2. Output with line clearing
        foreach ($lines as $line) {
            Terminal::clearLine();
            echo $line . PHP_EOL;
        }

        $this->lastLines = count($lines);
    }

    /* =========================================================
       CLEANUP
    ========================================================= */

    public function destroy(): void
    {
        Terminal::showCursor();
        parent::destroy();
    }

    public function resolve(): mixed
    {
        return $this->state->value;
    }

    /* =========================================================
       STRENGTH LOGIC
    ========================================================= */

    private function buildStrengthBar(string $password): string
    {
        $score = 0;
        $len = mb_strlen($password);

        if ($len >= 8) {
            $score++;
        }
        if ($len >= 12) {
            $score++;
        }
        if (preg_match('/[A-Z]/', $password)) {
            $score++;
        }
        if (preg_match('/[0-9]/', $password)) {
            $score++;
        }
        if (preg_match('/[^A-Za-z0-9]/', $password)) {
            $score++;
        }

        $labels = ['Very weak', 'Weak', 'Fair', 'Good', 'Strong'];
        $colors = [Colors::RED, Colors::RED, Colors::YELLOW, Colors::GREEN, Colors::GREEN];

        // Ensure we don't index out of bounds
        $index = max(0, min($score - 1, 4));

        $filled = str_repeat('━', $score);
        $empty = str_repeat('━', 5 - $score);
        $label = $labels[$index];
        $color = $colors[$index];

        return Colors::wrap($filled, $color)
            . Colors::muted($empty)
            . '  '
            . Colors::wrap($label, $color);
    }
}
