<?php
declare(strict_types=1);

namespace AlfacodeTeam\PhpIoCli\Components;

use AlfacodeTeam\PhpIoCli\Depends\Colors;

/**
 * Enterprise Styled Tables
 * Handles ANSI-aware widths and Unicode formatting.
 * 
 * Renders styled Unicode tables to the terminal.
 *
 * Usage:
 *   Table::make()
 *       ->headers(['Name', 'Role', 'Status'])
 *       ->rows([
 *           ['Alice', 'Admin',  'Active'],
 *           ['Bob',   'Editor', 'Inactive'],
 *       ])
 *       ->render();
 */
final class Table
{
    private array $headers = [];
    private array $rows    = [];
    private string $style  = 'box';
    private array $alignments = [];
    private bool $striped = true;

    private function __construct() {}

    public static function make(): self { return new self(); }

    public function headers(array $headers): self { $this->headers = $headers; return $this; }
    public function rows(array $rows): self { $this->rows = $rows; return $this; }
    public function style(string $style): self { $this->style = $style; return $this; }
    public function striped(bool $enable = true): self { $this->striped = $enable; return $this; }

    /** @param array<int, string> $alignments ['left', 'center', 'right'] */
    public function align(array $alignments): self { $this->alignments = $alignments; return $this; }

    public function render(): void
    {
        echo $this->toString();
    }

    public function toString(): string
    {
        if (empty($this->headers) && empty($this->rows)) {
            return "";
        }

        $colCount = $this->getColumnCount();
        $widths   = $this->computeWidths($colCount);

        [$tl, $tr, $bl, $br, $hSep, $vSep, $tJoin, $bJoin, $lJoin, $rJoin, $cross] = $this->getBorders();

        $out = '';

        // 1. Top border
        $out .= $this->drawSeparator($widths, $tl, $tr, $hSep, $tJoin) . PHP_EOL;

        // 2. Headers
        if (!empty($this->headers)) {
            $out .= $this->drawRow($this->headers, $widths, $vSep, true) . PHP_EOL;
            $out .= $this->drawSeparator($widths, $lJoin, $rJoin, $hSep, $cross) . PHP_EOL;
        }

        // 3. Data rows
        foreach ($this->rows as $index => $row) {
            $isAlt = $this->striped && ($index % 2 !== 0);
            $out .= $this->drawRow($row, $widths, $vSep, false, $isAlt) . PHP_EOL;
        }

        // 4. Bottom border
        $out .= $this->drawSeparator($widths, $bl, $br, $hSep, $bJoin) . PHP_EOL;

        return $out;
    }

    private function getColumnCount(): int
    {
        $max = count($this->headers);
        foreach ($this->rows as $row) {
            $max = max($max, count($row));
        }
        return $max;
    }

    /**
     * Fixes the "ANSI Headache": 
     * Uses Colors::strip() to calculate the VISUAL width of the content.
     */
    private function computeWidths(int $count): array
    {
        $widths = array_fill(0, $count, 0);

        $allData = array_merge([$this->headers], $this->rows);
        foreach ($allData as $row) {
            foreach ($row as $i => $cell) {
                // We strip ANSI before measuring length
                $visualLength = mb_strlen(Colors::strip((string)$cell));
                $widths[$i] = max($widths[$i], $visualLength);
            }
        }
        return $widths;
    }

    private function drawRow(array $cells, array $widths, string $sep, bool $isHeader = false, bool $isAlt = false): string
    {
        $parts = [];
        foreach ($widths as $i => $width) {
            $rawContent = (string)($cells[$i] ?? '');
            $align = $this->alignments[$i] ?? 'left';
            
            $padded = $this->applyPadding($rawContent, $width, $align);

            // Styling logic
            if ($isHeader) {
                $content = Colors::wrap($padded, [Colors::CYAN, Colors::BOLD]);
            } elseif ($isAlt) {
                $content = Colors::wrap($padded, Colors::DIM);
            } else {
                $content = $padded;
            }

            $parts[] = " {$content} ";
        }

        $vSep = Colors::muted($sep);
        return $vSep . implode($vSep, $parts) . $vSep;
    }

    private function applyPadding(string $text, int $targetWidth, string $align): string
    {
        $visualLen = mb_strlen(Colors::strip($text));
        $diff = max(0, $targetWidth - $visualLen);

        return match ($align) {
            'right'  => str_repeat(' ', $diff) . $text,
            'center' => str_repeat(' ', (int)floor($diff / 2)) . $text . str_repeat(' ', (int)ceil($diff / 2)),
            default  => $text . str_repeat(' ', $diff),
        };
    }

    private function drawSeparator(array $widths, string $l, string $r, string $h, string $join): string
    {
        $segments = [];
        foreach ($widths as $w) {
            $segments[] = str_repeat($h, $w + 2);
        }
        return Colors::muted($l . implode($join, $segments) . $r);
    }

    private function getBorders(): array
    {
        return match ($this->style) {
            'compact' => ['┌','┐','└','┘','─','│','┬','┴','├','┤','┼'],
            'minimal' => [' ',' ',' ',' ','─',' ','─','─','─','─',' '],
            'bold'    => ['┏','┓','┗','┛','━','┃','┳','┻','┣','┫','╋'],
            default   => ['╔','╗','╚','╝','═','║','╦','╩','╠','╣','╬'],
        };
    }
}