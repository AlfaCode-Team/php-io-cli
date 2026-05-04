<?php

declare(strict_types=1);

namespace AlfacodeTeam\PhpIoCli\Tests\Integration;

use AlfacodeTeam\PhpIoCli\AbstractCommand;
use AlfacodeTeam\PhpIoCli\BufferIO;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

// ── Fixtures ─────────────────────────────────────────────────────────────────

/**
 * Minimal command that echoes an argument and an option.
 */
final class EchoCommand extends AbstractCommand
{
    protected function configure(): void
    {
        $this->name        = 'echo';
        $this->description = 'Echoes back arguments and options';

        $this->addArgument('message', 'The message to echo', required: true);
        $this->addOption('upper', 'u', 'Output in uppercase');
        $this->addOption('repeat', 'r', 'Repeat count', acceptsValue: true, default: '1');
    }

    protected function handle(): int
    {
        $msg   = (string) $this->argument('message');
        $upper = $this->hasOption('upper');
        $times = (int) $this->option('repeat', '1');

        if ($upper) {
            $msg = strtoupper($msg);
        }

        for ($i = 0; $i < $times; $i++) {
            $this->info($msg);
        }

        return self::SUCCESS;
    }
}

/**
 * Command that validates a required argument.
 */
final class RequiredArgCommand extends AbstractCommand
{
    protected function configure(): void
    {
        $this->name = 'req';
        $this->addArgument('name', 'Name', required: true);
    }

    protected function handle(): int
    {
        $this->success((string) $this->argument('name'));
        return self::SUCCESS;
    }
}

/**
 * Command that always throws a runtime exception.
 */
final class FailingCommand extends AbstractCommand
{
    protected function configure(): void
    {
        $this->name = 'fail';
    }

    protected function handle(): int
    {
        throw new \RuntimeException('Something went wrong');
    }
}

/**
 * Command that returns FAILURE explicitly.
 */
final class ExplicitFailCommand extends AbstractCommand
{
    protected function configure(): void
    {
        $this->name = 'explicit-fail';
    }

    protected function handle(): int
    {
        $this->error('Explicit failure');
        return self::FAILURE;
    }
}

// ── Tests ─────────────────────────────────────────────────────────────────────

#[CoversClass(\AlfacodeTeam\PhpIoCli\AbstractCommand::class)]
final class AbstractCommandTest extends TestCase
{
    // ---------------------------------------------------------------
    // Basic execution
    // ---------------------------------------------------------------

    public function test_command_returns_success_code(): void
    {
        $io  = new BufferIO();
        $cmd = new EchoCommand();

        $exit = $cmd->execute(['hello'], $io);

        $this->assertSame(AbstractCommand::SUCCESS, $exit);
    }

    public function test_command_outputs_argument(): void
    {
        $io  = new BufferIO();
        $cmd = new EchoCommand();

        $cmd->execute(['hello world'], $io);

        $this->assertStringContainsString('hello world', $io->getOutput());
    }

    // ---------------------------------------------------------------
    // Options
    // ---------------------------------------------------------------

    public function test_long_flag_option_works(): void
    {
        $io  = new BufferIO();
        $cmd = new EchoCommand();

        $cmd->execute(['hello', '--upper'], $io);

        $this->assertStringContainsString('HELLO', $io->getOutput());
    }

    public function test_short_flag_option_works(): void
    {
        $io  = new BufferIO();
        $cmd = new EchoCommand();

        $cmd->execute(['hello', '-u'], $io);

        $this->assertStringContainsString('HELLO', $io->getOutput());
    }

    public function test_option_with_value_via_equals(): void
    {
        $io  = new BufferIO();
        $cmd = new EchoCommand();

        $cmd->execute(['hi', '--repeat=3'], $io);
        $output = $io->getOutput();

        // "hi" should appear 3 times
        $this->assertSame(3, substr_count($output, 'hi'));
    }

    public function test_option_with_value_via_space(): void
    {
        $io  = new BufferIO();
        $cmd = new EchoCommand();

        $cmd->execute(['hi', '--repeat', '2'], $io);
        $output = $io->getOutput();

        $this->assertSame(2, substr_count($output, 'hi'));
    }

    // ---------------------------------------------------------------
    // Required arguments
    // ---------------------------------------------------------------

    public function test_missing_required_argument_returns_invalid(): void
    {
        $io  = new BufferIO();
        $cmd = new RequiredArgCommand();

        $exit = $cmd->execute([], $io);

        $this->assertSame(AbstractCommand::INVALID, $exit);
    }

    // ---------------------------------------------------------------
    // Exception handling
    // ---------------------------------------------------------------

    public function test_unhandled_exception_returns_failure(): void
    {
        $io  = new BufferIO();
        $cmd = new FailingCommand();

        $exit = $cmd->execute([], $io);

        $this->assertSame(AbstractCommand::FAILURE, $exit);
    }

    public function test_explicit_failure_returns_failure_code(): void
    {
        $io  = new BufferIO();
        $cmd = new ExplicitFailCommand();

        $exit = $cmd->execute([], $io);

        $this->assertSame(AbstractCommand::FAILURE, $exit);
    }

    // ---------------------------------------------------------------
    // Metadata
    // ---------------------------------------------------------------

    public function test_get_name_returns_configured_name(): void
    {
        $cmd = new EchoCommand();

        $this->assertSame('echo', $cmd->getName());
    }

    public function test_get_description_returns_configured_description(): void
    {
        $cmd = new EchoCommand();

        $this->assertSame('Echoes back arguments and options', $cmd->getDescription());
    }

    // ---------------------------------------------------------------
    // Help
    // ---------------------------------------------------------------

    public function test_print_help_does_not_throw(): void
    {
        $io  = new BufferIO();
        $cmd = new EchoCommand();

        // execute() wires $this->io inside the command; printHelp() uses the
        // same io, so all output lands in BufferIO — not in PHP's output buffer.
        $cmd->execute(['placeholder'], $io);
        $cmd->printHelp();

        // FIX: was ob_start()/ob_get_clean() which captured PHP stdout (always
        // empty here). Read from the BufferIO stream instead.
        $help = $io->getOutput();

        $this->assertStringContainsString('echo', $help);
    }
}
