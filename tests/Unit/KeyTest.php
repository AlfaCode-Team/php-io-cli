<?php

declare(strict_types=1);

namespace AlfacodeTeam\PhpIoCli\Tests\Unit;

use AlfacodeTeam\PhpIoCli\Depends\Key;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Key::class)]
final class KeyTest extends TestCase
{
    // ---------------------------------------------------------------
    // normalize
    // ---------------------------------------------------------------

    #[DataProvider('escapeSequenceProvider')]
    public function test_normalize_maps_escape_sequences(string $raw, string $expected): void
    {
        $this->assertSame($expected, Key::normalize($raw));
    }

    public static function escapeSequenceProvider(): array
    {
        return [
            'arrow up' => ["\e[A",   'UP'],
            'arrow down' => ["\e[B",   'DOWN'],
            'arrow right' => ["\e[C",   'RIGHT'],
            'arrow left' => ["\e[D",   'LEFT'],
            'home' => ["\e[H",   'HOME'],
            'end' => ["\e[F",   'END'],
            'enter (newline)' => ["\n",     'ENTER'],
            'enter (carriage)' => ["\r",     'ENTER'],
            'tab' => ["\t",     'TAB'],
            'esc' => ["\e",     'ESC'],
            'backspace (del)' => ["\x7f",   'BACKSPACE'],
            'backspace (bs)' => ["\x08",   'BACKSPACE'],
            'delete sequence' => ["\e[3~",  'DELETE'],
            'ctrl+c' => ["\x03",   'CTRL_C'],
            'ctrl+d' => ["\x04",   'CTRL_D'],
        ];
    }

    public function test_normalize_returns_printable_as_is(): void
    {
        $this->assertSame('a', Key::normalize('a'));
        $this->assertSame('Z', Key::normalize('Z'));
        $this->assertSame('5', Key::normalize('5'));
        $this->assertSame('!', Key::normalize('!'));
        $this->assertSame(' ', Key::normalize(' '));
    }

    public function test_normalize_returns_unknown_sequence_as_is(): void
    {
        $unknown = "\e[99m";
        $this->assertSame($unknown, Key::normalize($unknown));
    }

    // ---------------------------------------------------------------
    // isPrintable
    // ---------------------------------------------------------------

    #[DataProvider('printableCharProvider')]
    public function test_is_printable_returns_true_for_printable_chars(string $char): void
    {
        $this->assertTrue(Key::isPrintable($char));
    }

    public static function printableCharProvider(): array
    {
        return [
            'lowercase a' => ['a'],
            'uppercase A' => ['A'],
            'digit 0' => ['0'],
            'space' => [' '],
            'exclamation' => ['!'],
            'at sign' => ['@'],
            'tilde' => ['~'],
        ];
    }

    #[DataProvider('nonPrintableCharProvider')]
    public function test_is_printable_returns_false_for_control_chars(string $char): void
    {
        $this->assertFalse(Key::isPrintable($char));
    }

    public static function nonPrintableCharProvider(): array
    {
        return [
            'null byte' => ["\x00"],
            'ctrl+c' => ["\x03"],
            'backspace' => ["\x7f"],
            'escape sequence' => ["\e[A"],
            'newline' => ["\n"],
            'tab' => ["\t"],
        ];
    }

    public function test_is_printable_rejects_multibyte_escape_sequences(): void
    {
        // Escape sequences are multi-byte, should not be printable
        $this->assertFalse(Key::isPrintable("\e[A"));
        $this->assertFalse(Key::isPrintable("\e[3~"));
    }
}
