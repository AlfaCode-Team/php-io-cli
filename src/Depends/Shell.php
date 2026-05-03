<?php
declare(strict_types=1);

namespace AlfacodeTeam\PhpIoCli\Depends;

/**
 * Thin, testable wrapper around proc_open.
 *
 * Features
 * ─────────
 * • Streams stdout AND stderr simultaneously with stream_select() so neither
 *   pipe can block the other (the classic deadlock trap with proc_open).
 * • Fires a $tick callback on every poll cycle (default ≤ 50 ms) so the
 *   caller can animate a SpinnerComponent with the most recent output line.
 * • Merges caller-supplied env vars over the current process environment.
 * • Returns an immutable ShellResult value object.
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
        ?callable $tick  = null,
        array    $env    = [],
        string   $cwd    = '',
    ): ShellResult {
        $descriptors = [
            0 => ['pipe', 'r'],   // stdin  — we close this immediately
            1 => ['pipe', 'w'],   // stdout
            2 => ['pipe', 'w'],   // stderr
        ];

        // Merge caller env over the real process environment.
        // array_merge resets numeric keys; this is intentional for env arrays.
        $fullEnv = array_merge(
            (array) (getenv() ?: []),
            $env
        );

        $process = proc_open(
            $command,
            $descriptors,
            $pipes,
            $cwd !== '' ? $cwd : (getcwd() ?: null),
            $fullEnv
        );

        if (!is_resource($process)) {
            return new ShellResult(1, [], ["proc_open failed for: {$command}"]);
        }

        // We never write to stdin.
        fclose($pipes[0]);

        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $stdout       = [];
        $stderr       = [];
        $stdoutBuf    = '';
        $stderrBuf    = '';
        $lastLine     = '';
        $lastIsStderr = false;

        // ── Streaming loop ────────────────────────────────────
        while (true) {
            $read   = [$pipes[1], $pipes[2]];
            $write  = null;
            $except = null;

            // Wait up to 50 ms for data on either pipe.
            // Returns false on error, 0 on timeout, >0 when data is ready.
            $changed = stream_select($read, $write, $except, 0, 50_000);

            if ($changed > 0) {
                foreach ($read as $stream) {
                    $isStdout = ($stream === $pipes[1]);
                    $chunk = fread($stream, 4096);

                    if ($chunk === false || $chunk === '') {
                        continue;
                    }

                    if ($isStdout) {
                        $stdoutBuf .= $chunk;
                    } else {
                        $stderrBuf .= $chunk;
                    }
                }
            }

            // ── Drain complete lines from buffers ─────────────
            foreach ([
                'stdout' => [&$stdoutBuf, &$stdout, false],
                'stderr' => [&$stderrBuf, &$stderr, true],
            ] as [$buf, $collection, $isErr]) {
                while (($pos = strpos($buf, "\n")) !== false) {
                    $line       = rtrim(substr($buf, 0, $pos));
                    $buf        = substr($buf, $pos + 1);
                    $collection[] = $line;

                    if ($line !== '') {
                        $lastLine     = $line;
                        $lastIsStderr = $isErr;
                    }
                }
            }

            // ── Tick callback (animation + last-line sub-label) ─
            if ($tick !== null) {
                $tick($lastLine, $lastIsStderr);
            }

            // ── Check for EOF on both pipes ───────────────────
            if (feof($pipes[1]) && feof($pipes[2])) {
                // Flush any remaining partial lines
                foreach ([
                    [&$stdoutBuf, &$stdout, false],
                    [&$stderrBuf, &$stderr, true],
                ] as [$buf, $collection, $isErr]) {
                    if (trim($buf) !== '') {
                        $collection[] = rtrim($buf);
                    }
                }
                break;
            }
        }

        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        return new ShellResult($exitCode, $stdout, $stderr);
    }

    /**
     * Convenience: run and return trimmed stdout or null on failure.
     * Good for quick value capture (e.g. reading a git config entry).
     */
    public static function capture(string $command, string $cwd = ''): ?string
    {
        $result = self::run($command, cwd: $cwd);
        return $result->ok() ? trim($result->output()) : null;
    }
}