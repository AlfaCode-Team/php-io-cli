<?php
declare(strict_types=1);

namespace AlfacodeTeam\PhpIoCli\Components;

use AlfacodeTeam\PhpIoCli\Depends\Colors;
use AlfacodeTeam\PhpIoCli\Depends\Terminal;
use DateTimeImmutable;

/**
 * Interactive calendar date picker.
 *
 * Usage:
 *   $date = (new DatePicker('Select a start date'))->run(); // returns DateTimeImmutable
 * Enterprise Date Picker
 * Reactive calendar with grid navigation and smart month clamping.
 */
final class DatePicker extends Component
{
    private int $lastLines = 0;

    public function __construct(private string $question)
    {
        parent::__construct();
    }

    protected function setup(): void
    {
        $now = new DateTimeImmutable();

        $this->state->batch([
            'year'  => (int)$now->format('Y'),
            'month' => (int)$now->format('n'),
            'day'   => (int)$now->format('j'),
            'done'  => false,
        ]);

        // 1. Precise Day/Week Navigation
        $this->input->bind('LEFT',  fn($s) => $this->shiftDate($s, '-1 day'));
        $this->input->bind('RIGHT', fn($s) => $this->shiftDate($s, '+1 day'));
        $this->input->bind('UP',    fn($s) => $this->shiftDate($s, '-7 days'));
        $this->input->bind('DOWN',  fn($s) => $this->shiftDate($s, '+7 days'));

        // 2. Month Navigation (Mapped to [ and ] or PageUp/Down if your Terminal detects them)
        $this->input->bind(['[', 'PAGE_UP'],   fn($s) => $this->shiftMonth($s, -1));
        $this->input->bind([']', 'PAGE_DOWN'], fn($s) => $this->shiftMonth($s, 1));

        // 3. Shortcuts
        $this->input->bind('t', function($s) {
            $now = new DateTimeImmutable();
            $s->batch([
                'year' => (int)$now->format('Y'),
                'month' => (int)$now->format('n'),
                'day' => (int)$now->format('j'),
            ]);
        });

        $this->input->bind('ENTER', function ($s) {
            $s->done = true;
            $this->stop();
        });
    }

    private function shiftDate(mixed $s, string $modify): void
    {
        $dt = $this->getSelectedDate()->modify($modify);
        $s->batch([
            'year'  => (int)$dt->format('Y'),
            'month' => (int)$dt->format('n'),
            'day'   => (int)$dt->format('j'),
        ]);
    }

    private function shiftMonth(mixed $s, int $delta): void
    {
        $dt = $this->getSelectedDate();
        // Modify month first
        $newDt = $dt->modify(($delta > 0 ? '+' : '') . $delta . ' months');
        
        // Handle PHP "overflow" (Jan 31 + 1 month = March 3). Clamp to last day of month.
        if ((int)$newDt->format('n') !== ($dt->format('n') + $delta + 12) % 12 ?: 12) {
             $newDt = $newDt->modify('last day of last month');
        }

        $s->batch([
            'year'  => (int)$newDt->format('Y'),
            'month' => (int)$newDt->format('n'),
            'day'   => (int)$newDt->format('j'),
        ]);
    }

    private function getSelectedDate(): DateTimeImmutable
    {
        return new DateTimeImmutable(sprintf(
            '%04d-%02d-%02d', 
            $this->state->year, 
            $this->state->month, 
            $this->state->day
        ));
    }

    public function render(): void
    {
        // 1. Move up and CLEAR the old lines
        if ($this->lastLines > 0) {
            Terminal::moveCursorUp($this->lastLines);
            // We clear every line we previously occupied to prevent "Ghosting"
            for ($i = 0; $i < $this->lastLines; $i++) {
                Terminal::clearLine();
                echo PHP_EOL;
            }
            Terminal::moveCursorUp($this->lastLines);
        }

        $year  = (int)$this->state->year;
        $month = (int)$this->state->month;
        $day   = (int)$this->state->day;
        $done  = (bool)$this->state->done;

        $lines = [];
        $lines[] = Colors::wrap('? ', Colors::CYAN) . Colors::wrap($this->question, Colors::BOLD);

        if (!$done) {
            $currentDt = $this->getSelectedDate();
            $firstOfMonth = $currentDt->modify('first day of this month');
            $daysInMonth = (int)$currentDt->format('t');
            $startColumn = (int)$firstOfMonth->format('N') - 1; // 0 (Mon) to 6 (Sun)

            $lines[] = '';
            $lines[] = '  ' . Colors::wrap($currentDt->format('F Y'), [Colors::BOLD, Colors::CYAN]);
            $lines[] = '  ' . Colors::muted('Mo Tu We Th Fr Sa Su');

            $currentLine = '  ' . str_repeat('   ', $startColumn);
            $col = $startColumn;

            for ($d = 1; $d <= $daysInMonth; $d++) {
                $isToday = $this->isToday($year, $month, $d);
                $isSelected = ($d === $day);
                
                $label = str_pad((string)$d, 2, ' ', STR_PAD_LEFT);
                
                if ($isSelected) {
                    // Reverse video for the "Cursor"
                    $cell = Colors::wrap(" {$label}", ["\033[7m", Colors::CYAN, Colors::BOLD]);
                } elseif ($isToday) {
                    $cell = Colors::wrap(" {$label}", [Colors::YELLOW, Colors::UNDERLINE]);
                } else {
                    // Weekend dimming
                    $isWeekend = ($col % 7) >= 5;
                    $cell = $isWeekend ? Colors::muted(" {$label}") : " {$label}";
                }

                $currentLine .= $cell;
                $col++;

                if ($col % 7 === 0) {
                    $lines[] = $currentLine;
                    $currentLine = '  ';
                }
            }

            if (trim($currentLine) !== '') {
                $lines[] = $currentLine;
            }

            $lines[] = '';
            $lines[] = '  ' . Colors::info($currentDt->format('l, d F Y'));
            $lines[] = Colors::muted('  ←↑↓→ nav • [ ] month • t today • ENTER confirm');
        } else {
            $lines[] = Colors::wrap('  ✔ ', Colors::GREEN) . Colors::info($this->getSelectedDate()->format('Y-m-d'));
        }

        // Atomic render
        $output = "";
        foreach ($lines as $line) {
            $output .= "\r\033[2K" . $line . PHP_EOL;
        }
        echo $output;

        $this->lastLines = count($lines);
    }

    private function isToday(int $y, int $m, int $d): bool
    {
        return date('Y-m-d') === sprintf('%04d-%02d-%02d', $y, $m, $d);
    }

    public function resolve(): DateTimeImmutable
    {
        return $this->getSelectedDate();
    }
}
