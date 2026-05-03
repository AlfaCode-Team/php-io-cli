<?php

declare(strict_types=1);

namespace AlfacodeTeam\PhpIoCli\Tests\Unit;

use AlfacodeTeam\PhpIoCli\Depends\ShellResult;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AlfacodeTeam\PhpIoCli\Depends\ShellResult
 */
final class ShellResultTest extends TestCase
{
    public function test_ok_returns_true_for_exit_code_zero(): void
    {
        $result = new ShellResult(0, ['output'], []);

        $this->assertTrue($result->ok());
        $this->assertFalse($result->failed());
    }

    public function test_failed_returns_true_for_nonzero_exit_code(): void
    {
        $result = new ShellResult(1, [], ['error']);

        $this->assertTrue($result->failed());
        $this->assertFalse($result->ok());
    }

    public function test_output_joins_stdout_lines(): void
    {
        $result = new ShellResult(0, ['line one', 'line two', 'line three'], []);

        $this->assertSame('line one' . PHP_EOL . 'line two' . PHP_EOL . 'line three', $result->output());
    }

    public function test_errors_joins_stderr_lines(): void
    {
        $result = new ShellResult(1, [], ['err1', 'err2']);

        $this->assertSame('err1' . PHP_EOL . 'err2', $result->errors());
    }

    public function test_meaningful_errors_filters_blank_lines(): void
    {
        $result = new ShellResult(1, [], ['', 'Real error', '   ', 'Another error', '']);

        $this->assertSame(['Real error', 'Another error'], $result->meaningfulErrors());
    }

    public function test_meaningful_errors_returns_empty_for_blank_stderr(): void
    {
        $result = new ShellResult(1, [], ['', '   ']);

        $this->assertEmpty($result->meaningfulErrors());
    }

    public function test_properties_are_readonly(): void
    {
        $result = new ShellResult(0, ['a'], ['b']);

        $this->assertSame(0, $result->exitCode);
        $this->assertSame(['a'], $result->stdout);
        $this->assertSame(['b'], $result->stderr);
    }

    public function test_output_empty_when_no_stdout(): void
    {
        $result = new ShellResult(0, [], []);

        $this->assertSame('', $result->output());
    }

    public function test_errors_empty_when_no_stderr(): void
    {
        $result = new ShellResult(0, [], []);

        $this->assertSame('', $result->errors());
    }
}
