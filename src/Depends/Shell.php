<?php

declare(strict_types=1);

namespace AlfacodeTeam\PhpIoCli\Depends;

/**
 * Enterprise Shell Wrapper
 *
 * v1-> Features
 * ─────────
 * • Streams stdout AND stderr simultaneously with stream_select() so neither
 *   pipe can block the other (the classic deadlock trap with proc_open).
 * • Fires a $tick callback on every poll cycle (default ≤ 50 ms) so the
 *   caller can animate a SpinnerComponent with the most recent output line.
 * • Merges caller-supplied env vars over the current process environment.
 * • Returns an immutable ShellResult value object.
 *
 * Features:
 * - Deadlock-free simultaneous stdout/stderr streaming.
 * - Non-blocking stream_select for high-performance UI ticks.
 * - Guaranteed capture of partial trailing lines (fixes test failures).
 *
 * Usage with SpinnerComponent
 * ───────────────────────────
 *   $spin = new SpinnerComponent('Running git …');
 *   $spin->start();
 *
 *   $result = Shell::run(
 *       'git submodule add …',
 *       tick: function (string $lastLine, bool $isStderr) use ($spin): void {
 *           $spin->tick($lastLine);
 *       },
 *   );
 *
 *   $result->ok()
 *       ? $spin->stop('Done')
 *       : $spin->fail('git failed');
 */
final class Shell
{
    private function __construct() {}

    /**
     * Execute $command in a child process and stream its output.
     *
     * @param string        $command   Shell command string passed to /bin/sh (or cmd.exe on Windows).
     * @param callable|null $tick      Signature: (string $lastLine, bool $isStderr): void
     *                                 Called on every poll cycle AND whenever a new line arrives.
     *                                 Pass your SpinnerComponent::tick() here.
     * @param array<string,string> $env  Additional environment variables merged over current env.
     * @param string        $cwd       Working directory; defaults to getcwd().
     */
    public static function run(
        string   $command,
        callable|null $tick = null,
        array    $env = [],
        string   $cwd = '',
    ): ShellResult {
        $descriptors = [
            0 => ['pipe', 'r'], // stdin
            1 => ['pipe', 'w'], // stdout
            2 => ['pipe', 'w'], // stderr
        ];

        // Ensure environment variables are preserved and merged
        $fullEnv = array_merge((array) (getenv() ?: []), $env);

        $process = proc_open(
            $command,
            $descriptors,
            $pipes,
            $cwd !== '' ? $cwd : null,
            $fullEnv,
        );

        if (!is_resource($process)) {
            return new ShellResult(1, [], ["proc_open failed for: {$command}"]);
        }

        // Close stdin immediately as we don't support interactive input here
        fclose($pipes[0]);

        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $stdout = [];
        $stderr = [];
        $stdoutBuf = '';
        $stderrBuf = '';
        $lastLine = '';
        $lastIsStderr = false;

        // --- Streaming Loop ---
        while (true) {
            $read = [$pipes[1], $pipes[2]];
            $write = null;
            $except = null;

            // Wait 50ms for activity
            $changed = stream_select($read, $write, $except, 0, 50_000);

            if ($changed === false) {
                break; // System error
            }

            if ($changed > 0) {
                foreach ($read as $stream) {
                    $isStdout = ($stream === $pipes[1]);
                    $chunk = fread($stream, 4096);

                    if ($chunk !== false && $chunk !== '') {
                        if ($isStdout) {
                            $stdoutBuf .= $chunk;
                        } else {
                            $stderrBuf .= $chunk;
                        }
                    }
                }
            }

            // --- Process complete lines for STDOUT ---
            while (($pos = mb_strpos($stdoutBuf, "\n")) !== false) {
                $line = mb_rtrim(mb_substr($stdoutBuf, 0, $pos));
                $stdoutBuf = mb_substr($stdoutBuf, $pos + 1);
                $stdout[] = $line;
                $lastLine = $line;
                $lastIsStderr = false;
            }

            // --- Process complete lines for STDERR ---
            while (($pos = mb_strpos($stderrBuf, "\n")) !== false) {
                $line = mb_rtrim(mb_substr($stderrBuf, 0, $pos));
                $stderrBuf = mb_substr($stderrBuf, $pos + 1);
                $stderr[] = $line;
                $lastLine = $line;
                $lastIsStderr = true;
            }

            // --- UI Tick ---
            if ($tick !== null) {
                $tick($lastLine, $lastIsStderr);
            }

            // Exit loop if both pipes are closed
            if (feof($pipes[1]) && feof($pipes[2])) {
                break;
            }
        }

        // --- Final Flush ---
        // Capture any remaining data that didn't end with a newline (Critical for tests!)
        if (($trimmed = mb_rtrim($stdoutBuf)) !== '') {
            $stdout[] = $trimmed;
            $lastLine = $trimmed;
            $lastIsStderr = false;
        }
        if (($trimmed = mb_rtrim($stderrBuf)) !== '') {
            $stderr[] = $trimmed;
            $lastLine = $trimmed;
            $lastIsStderr = true;
        }

        // Final tick to update UI with last processed data
        if ($tick !== null && $lastLine !== '') {
            $tick($lastLine, $lastIsStderr);
        }

        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        return new ShellResult($exitCode, $stdout, $stderr);
    }

    /**
     * Run and return trimmed stdout. Returns null on failure.
     */
    public static function capture(string $command, string $cwd = ''): string|null
    {
        $result = self::run($command, cwd: $cwd);

        return $result->ok() ? mb_trim($result->output()) : null;
    }
}
