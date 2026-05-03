<?php
declare(strict_types=1);

namespace AlfacodeTeam\PhpIoCli\Components;

use AlfacodeTeam\PhpIoCli\Depends\Colors;
use AlfacodeTeam\PhpIoCli\Depends\Key;
use AlfacodeTeam\PhpIoCli\Depends\Terminal;

/**
 * Numeric input with arrow-key stepping, min/max enforcement, and live validation.
 *
 * Usage:
 *   $port = (new NumberInput('Server port'))
 *       ->min(1)->max(65535)->default(8080)->step(1)->run();
 */
final class NumberInput extends Component
{
    private ?float $min      = null;
    private ?float $max      = null;
    private float  $step     = 1;
    private ?float $default  = null;
    private bool   $intOnly  = false;
    private int    $lastLines = 0;

    public function __construct(private string $question)
    {
        parent::__construct();
    }

    /* --- Fluent --- */
    public function min(float $v): self    { $this->min = $v; return $this; }
    public function max(float $v): self    { $this->max = $v; return $this; }
    public function step(float $v): self   { $this->step = $v; return $this; }
    public function default(float $v): self{ $this->default = $v; return $this; }
    public function integer(): self        { $this->intOnly = true; return $this; }

    /* =========================================================
       LIFECYCLE
    ========================================================= */

    protected function setup(): void
    {
        $this->state->batch([
            'raw'   => $this->default !== null ? (string)$this->default : '',
            'error' => null,
            'done'  => false,
        ]);

        // Typing digits, minus, dot
        $this->input->fallback(function ($s, $key) {
            if (!Key::isPrintable($key)) return;
            $allowed = $this->intOnly ? '0123456789-' : '0123456789.-';
            if (mb_strpos($allowed, $key) !== false) {
                $s->raw   = (string)$s->raw . $key;
                $s->error = null;
            }
        });

        $this->input->bind('BACKSPACE', function ($s) {
            $s->raw   = mb_substr((string)$s->raw, 0, -1);
            $s->error = null;
        });

        // Arrow stepping
        $this->input->bind('UP', function ($s) {
            $current = (float)((string)$s->raw ?: '0');
            $new     = $current + $this->step;
            if ($this->max !== null) $new = min($new, $this->max);
            $s->raw  = $this->format($new);
        });

        $this->input->bind('DOWN', function ($s) {
            $current = (float)((string)$s->raw ?: '0');
            $new     = $current - $this->step;
            if ($this->min !== null) $new = max($new, $this->min);
            $s->raw  = $this->format($new);
        });

        $this->input->bind('ENTER', function ($s) {
            $raw = trim((string)$s->raw);
            if ($raw === '' && $this->default !== null) {
                $raw = (string)$this->default;
            }

            if ($raw === '') {
                $s->error = 'A number is required.';
                return;
            }

            if (!is_numeric($raw)) {
                $s->error = "'{$raw}' is not a valid number.";
                return;
            }

            $val = (float)$raw;

            if ($this->min !== null && $val < $this->min) {
                $s->error = "Minimum value is {$this->min}.";
                return;
            }

            if ($this->max !== null && $val > $this->max) {
                $s->error = "Maximum value is {$this->max}.";
                return;
            }

            $s->raw  = $this->format($val);
            $s->done = true;
            $this->stop();
        });
    }

    private function format(float $v): string
    {
        return $this->intOnly ? (string)(int)$v : rtrim(rtrim(number_format($v, 10, '.', ''), '0'), '.');
    }

    /* =========================================================
       RENDER
    ========================================================= */

    public function render(): void
    {
        if ($this->lastLines > 0) {
            Terminal::moveCursorUp($this->lastLines);
        }

        Terminal::hideCursor();

        $raw   = (string)$this->state->raw;
        $error = $this->state->error;
        $done  = (bool)$this->state->done;
        $lines = [];

        $mark    = $done ? Colors::success('') : Colors::wrap('? ', Colors::CYAN);
        $lines[] = $mark . Colors::wrap($this->question, Colors::BOLD);

        if (!$done) {
            $cursor  = Colors::wrap('▊', Colors::CYAN);
            $display = Colors::wrap($raw, Colors::YELLOW) . $cursor;

            // Range hint inline
            $hint = '';
            if ($this->min !== null || $this->max !== null) {
                $lo = $this->min !== null ? $this->format($this->min) : '−∞';
                $hi = $this->max !== null ? $this->format($this->max) : '+∞';
                $hint = Colors::muted("  [{$lo} … {$hi}]");
            }

            $lines[] = Colors::wrap('  › ', Colors::GRAY) . $display . $hint;

            if ($error !== null) {
                $lines[] = Colors::wrap("  ✘ {$error}", Colors::RED);
            } else {
                $def = $this->default !== null ? Colors::muted("  Default: {$this->format($this->default)}") : '';
                $lines[] = $def !== '' ? $def : '';
            }

            $lines[] = Colors::muted('  ↑↓ step  •  Type number  •  ENTER confirm');
        } else {
            $lines[] = Colors::wrap('  › ', Colors::GRAY) . Colors::wrap($raw, Colors::GREEN);
        }

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
        $v = (float)$this->state->raw;
        return $this->intOnly ? (int)$v : $v;
    }
}