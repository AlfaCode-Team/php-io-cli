<?php

declare(strict_types=1);

namespace AlfacodeTeam\PhpIoCli\Tests\Integration;

use AlfacodeTeam\PhpIoCli\Depends\Shell;
use AlfacodeTeam\PhpIoCli\Depends\ShellResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * All commands used here are safe, cross-platform, read-only operations.
 * We favour `php -r` and `printf` so the suite passes on both Unix and
 * Linux-based CI without relying on shell builtins that may differ.
 */
#[CoversClass(Shell::class)]
final class ShellTest extends TestCase
{
    // ---------------------------------------------------------------
    // Shell::run — basic success path
    // ---------------------------------------------------------------

    public function test_run_returns_shell_result_instance(): void
    {
        $result = Shell::run('php -r "echo \'ok\';"');

        $this->assertInstanceOf(ShellResult::class, $result);
    }

    public function test_run_ok_is_true_for_successful_command(): void
    {
        $result = Shell::run('php -r "exit(0);"');

        $this->assertTrue($result->ok());
        $this->assertFalse($result->failed());
        $this->assertSame(0, $result->exitCode);
    }

    public function test_run_captures_stdout(): void
    {
        $result = Shell::run('php -r "echo \"hello shell\";"');

        $this->assertTrue($result->ok());
        $this->assertStringContainsString('hello shell', $result->output());
    }

    public function test_run_captures_multiline_stdout(): void
    {
        $result = Shell::run('php -r "echo \"line1\nline2\nline3\";"');

        $this->assertTrue($result->ok());
        $this->assertContains('line1', $result->stdout);
        $this->assertContains('line2', $result->stdout);
        $this->assertContains('line3', $result->stdout);
    }

    // ---------------------------------------------------------------
    // Shell::run — failure path
    // ---------------------------------------------------------------

    public function test_run_failed_is_true_for_non_zero_exit(): void
    {
        $result = Shell::run('php -r "exit(1);"');

        $this->assertTrue($result->failed());
        $this->assertFalse($result->ok());
        $this->assertSame(1, $result->exitCode);
    }

    public function test_run_captures_stderr(): void
    {
        // php -r with a deliberate notice/warning goes to stderr
        $result = Shell::run('php -r "fwrite(STDERR, \'error output\');"');

        $this->assertStringContainsString('error output', $result->errors());
    }

    public function test_run_exit_code_matches_process_exit(): void
    {
        $result = Shell::run('php -r "exit(42);"');

        $this->assertSame(42, $result->exitCode);
    }

    // ---------------------------------------------------------------
    // Shell::run — tick callback
    // ---------------------------------------------------------------

    public function test_run_tick_callback_is_invoked(): void
    {
        $ticked = false;

        Shell::run(
            'php -r "echo \"tick test\";"',
            tick: static function (string $lastLine, bool $isStderr) use (&$ticked): void {
                $ticked = true;
            },
        );

        $this->assertTrue($ticked, 'tick callback must be called at least once');
    }

    public function test_run_tick_callback_receives_last_line(): void
    {
        $receivedLines = [];

        Shell::run(
            'php -r "echo \"abc\ndef\";"',
            tick: static function (string $lastLine) use (&$receivedLines): void {
                if ($lastLine !== '') {
                    $receivedLines[] = $lastLine;
                }
            },
        );

        // At least one of the lines should have been surfaced in the tick
        $this->assertNotEmpty($receivedLines);
    }

    // ---------------------------------------------------------------
    // Shell::run — environment variables
    // ---------------------------------------------------------------

    public function test_run_passes_env_variables_to_child(): void
    {
        $result = Shell::run(
            'php -r "echo getenv(\'MY_TEST_VAR\');"',
            env: ['MY_TEST_VAR' => 'hello-from-env'],
        );

        $this->assertTrue($result->ok());
        $this->assertStringContainsString('hello-from-env', $result->output());
    }

    // ---------------------------------------------------------------
    // Shell::run — working directory
    // ---------------------------------------------------------------

    public function test_run_respects_cwd(): void
    {
        $cwd = sys_get_temp_dir();
        $result = Shell::run('php -r "echo getcwd();"', cwd: $cwd);

        $this->assertTrue($result->ok());
        // Resolve symlinks to handle /var → /private/var on macOS
        $this->assertSame(
            realpath($cwd),
            realpath(mb_trim($result->output())),
        );
    }

    // ---------------------------------------------------------------
    // Shell::capture — success
    // ---------------------------------------------------------------

    public function test_capture_returns_trimmed_stdout_on_success(): void
    {
        $output = Shell::capture('php -r "echo \'  trimmed  \';"');

        $this->assertSame('trimmed', $output);
    }

    public function test_capture_returns_null_on_failure(): void
    {
        $output = Shell::capture('php -r "exit(1);"');

        $this->assertNull($output);
    }

    public function test_capture_php_version_contains_version_string(): void
    {
        $output = Shell::capture('php --version');

        $this->assertNotNull($output);
        $this->assertStringContainsString('PHP', (string) $output);
    }

    // ---------------------------------------------------------------
    // Shell::run — stdout and stderr arrays are accessible
    // ---------------------------------------------------------------

    public function test_run_stdout_property_is_array_of_lines(): void
    {
        $result = Shell::run('php -r "echo \"a\nb\nc\";"');

        $this->assertIsArray($result->stdout);
        $this->assertGreaterThanOrEqual(1, count($result->stdout));
    }

    public function test_run_stderr_property_is_array(): void
    {
        $result = Shell::run('php -r "echo \'ok\';"');

        $this->assertIsArray($result->stderr);
    }

    // ---------------------------------------------------------------
    // Shell::run — proc_open failure (bad command)
    // ---------------------------------------------------------------

    public function test_run_returns_failure_for_completely_invalid_command(): void
    {
        // A command that cannot be found at all still returns a ShellResult
        $result = Shell::run('this-command-definitely-does-not-exist-xyz-12345 2>/dev/null; exit 127');

        $this->assertInstanceOf(ShellResult::class, $result);
        $this->assertTrue($result->failed());
    }
}
