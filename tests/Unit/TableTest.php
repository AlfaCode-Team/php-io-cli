<?php

declare(strict_types=1);

namespace AlfacodeTeam\PhpIoCli\Tests\Unit;

use AlfacodeTeam\PhpIoCli\Components\Table;
use AlfacodeTeam\PhpIoCli\Depends\Colors;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Table::class)]
final class TableTest extends TestCase
{
    protected function setUp(): void
    {
        Colors::disable(); // Plain output for reliable assertions
    }

    protected function tearDown(): void
    {
        Colors::enable();
    }

    // ---------------------------------------------------------------
    // Basic rendering
    // ---------------------------------------------------------------

    public function test_table_contains_header_values(): void
    {
        $output = $this->plainTable();

        $this->assertStringContainsString('Name', $output);
        $this->assertStringContainsString('Role', $output);
        $this->assertStringContainsString('Status', $output);
    }

    public function test_table_contains_row_values(): void
    {
        $output = $this->plainTable();

        $this->assertStringContainsString('Alice', $output);
        $this->assertStringContainsString('Admin', $output);
        $this->assertStringContainsString('Bob', $output);
        $this->assertStringContainsString('Inactive', $output);
    }

    public function test_empty_table_returns_empty_string(): void
    {
        $output = Table::make()->toString();

        $this->assertSame('', $output);
    }

    // ---------------------------------------------------------------
    // Styles
    // ---------------------------------------------------------------

    #[DataProvider('styleProvider')]
    public function test_table_renders_with_different_styles(string $style): void
    {
        $output = Colors::strip(
            Table::make()
                ->headers(['Col'])
                ->rows([['Value']])
                ->style($style)
                ->toString(),
        );

        $this->assertStringContainsString('Col', $output);
        $this->assertStringContainsString('Value', $output);
    }

    public static function styleProvider(): array
    {
        return [
            'box' => ['box'],
            'bold' => ['bold'],
            'compact' => ['compact'],
            'minimal' => ['minimal'],
        ];
    }

    // ---------------------------------------------------------------
    // make() factory
    // ---------------------------------------------------------------

    public function test_make_returns_new_table_instance(): void
    {
        $t1 = Table::make();
        $t2 = Table::make();

        $this->assertNotSame($t1, $t2);
    }

    // ---------------------------------------------------------------
    // Alignment — smoke test
    // ---------------------------------------------------------------

    public function test_align_does_not_break_rendering(): void
    {
        $output = Colors::strip(
            Table::make()
                ->headers(['Left', 'Center', 'Right'])
                ->rows([['a', 'b', 'c']])
                ->align([0 => 'left', 1 => 'center', 2 => 'right'])
                ->toString(),
        );

        $this->assertStringContainsString('Left', $output);
        $this->assertStringContainsString('Right', $output);
    }

    // ---------------------------------------------------------------
    // ANSI-safe width calculation
    // ---------------------------------------------------------------

    public function test_ansi_color_in_cell_does_not_corrupt_alignment(): void
    {
        // Even though the cell contains ANSI codes, the column should align correctly
        $coloredCell = Colors::wrap('Active', Colors::GREEN);
        $output = Colors::strip(
            Table::make()
                ->headers(['Name', 'Status'])
                ->rows([
                    ['Alice', $coloredCell],
                    ['Bob',   'Inactive'],
                ])
                ->toString(),
        );

        $this->assertStringContainsString('Alice', $output);
        $this->assertStringContainsString('Active', $output);
        $this->assertStringContainsString('Inactive', $output);
    }

    private function plainTable(): string
    {
        return Colors::strip(
            Table::make()
                ->headers(['Name', 'Role', 'Status'])
                ->rows([
                    ['Alice', 'Admin', 'Active'],
                    ['Bob', 'Editor', 'Inactive'],
                ])
                ->toString(),
        );
    }
}
