<?php

declare(strict_types=1);

namespace AlfacodeTeam\PhpIoCli\Tests\Integration;

use AlfacodeTeam\PhpIoCli\AbstractCommand;
use AlfacodeTeam\PhpIoCli\BufferIO;
use AlfacodeTeam\PhpIoCli\CLIApplication;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

// ── Fixtures ─────────────────────────────────────────────────────────────────

final class PingCommand extends AbstractCommand
{
    protected function configure(): void
    {
        $this->name = 'ping';
        $this->description = 'Returns pong';
    }

    protected function handle(): int
    {
        $this->info('pong');

        return self::SUCCESS;
    }
}

final class GreetCommand extends AbstractCommand
{
    protected function configure(): void
    {
        $this->name = 'greet';
        $this->description = 'Greet a user';
        $this->addArgument('name', 'User name', required: true);
    }

    protected function handle(): int
    {
        $this->info('Hello, ' . $this->argument('name') . '!');

        return self::SUCCESS;
    }
}

final class SideEffectCommand extends AbstractCommand
{
    public static int $runs = 0;

    protected function configure(): void
    {
        $this->name = 'wipe';
        $this->description = 'Destroys data when run';
        $this->addOption('force', 'f', 'Skip confirmation');
    }

    protected function handle(): int
    {
        self::$runs++;

        return self::SUCCESS;
    }
}

// ── Tests ─────────────────────────────────────────────────────────────────────

#[CoversClass(CLIApplication::class)]
final class CLIApplicationTest extends TestCase
{
    // ---------------------------------------------------------------
    // Basic dispatch
    // ---------------------------------------------------------------

    public function test_runs_matching_command(): void
    {
        $io = new BufferIO();
        $app = (new CLIApplication('TestApp', '1.0.0'))
            ->withIO($io)
            ->add(new PingCommand());

        $exit = $app->run(['ping']);

        $this->assertSame(AbstractCommand::SUCCESS, $exit);
        $this->assertStringContainsString('pong', $io->getOutput());
    }

    public function test_command_with_argument(): void
    {
        $io = new BufferIO();
        $app = (new CLIApplication('TestApp', '1.0.0'))
            ->withIO($io)
            ->add(new GreetCommand());

        $app->run(['greet', 'Alice']);

        $this->assertStringContainsString('Hello, Alice!', $io->getOutput());
    }

    // ---------------------------------------------------------------
    // Built-in commands
    // ---------------------------------------------------------------

    // ---------------------------------------------------------------
    // Help never runs the command
    // ---------------------------------------------------------------

    /**
     * `<command> --help` used to execute() the command before printing its help
     * (execute() was the only way to hand it an IO), so `migrate:fresh --help`
     * dropped every table. Help must be side-effect free.
     */
    public function test_help_flag_prints_help_without_running_the_command(): void
    {
        SideEffectCommand::$runs = 0;
        $io = new BufferIO();
        $app = (new CLIApplication('TestApp', '1.0.0'))
            ->withIO($io)
            ->add(new SideEffectCommand());

        $this->assertSame(AbstractCommand::SUCCESS, $app->run(['wipe', '--help']));
        $this->assertSame(AbstractCommand::SUCCESS, $app->run(['wipe', '-h']));

        $this->assertSame(0, SideEffectCommand::$runs);
        $this->assertStringContainsString('Destroys data when run', $io->getOutput());
        $this->assertStringContainsString('--force', $io->getOutput());
    }

    public function test_help_subcommand_prints_help_without_running_the_command(): void
    {
        SideEffectCommand::$runs = 0;
        $io = new BufferIO();
        $app = (new CLIApplication('TestApp', '1.0.0'))
            ->withIO($io)
            ->add(new SideEffectCommand());

        $this->assertSame(AbstractCommand::SUCCESS, $app->run(['help', 'wipe']));

        $this->assertSame(0, SideEffectCommand::$runs);
        $this->assertStringContainsString('Command: wipe', $io->getOutput());
    }

    public function test_help_for_a_command_with_a_required_argument_still_prints(): void
    {
        $io = new BufferIO();
        $app = (new CLIApplication('TestApp', '1.0.0'))
            ->withIO($io)
            ->add(new GreetCommand());

        $this->assertSame(AbstractCommand::SUCCESS, $app->run(['greet', '--help']));
        $this->assertStringContainsString('<name>', $io->getOutput());
        $this->assertStringNotContainsString('Missing required argument', $io->getOutput());
    }

    public function test_version_command_outputs_name_and_version(): void
    {
        $io = new BufferIO();
        $app = (new CLIApplication('MyApp', '2.5.0'))->withIO($io);

        $app->run(['version']);
        $output = $io->getOutput();

        $this->assertStringContainsString('MyApp', $output);
        $this->assertStringContainsString('2.5.0', $output);
    }

    public function test_list_command_shows_registered_commands(): void
    {
        $io = new BufferIO();
        $app = (new CLIApplication('TestApp', '1.0.0'))
            ->withIO($io)
            ->add(new PingCommand(), new GreetCommand());

        $app->run(['list']);
        $output = $io->getOutput();

        $this->assertStringContainsString('ping', $output);
        $this->assertStringContainsString('greet', $output);
    }

    public function test_bare_invocation_shows_list(): void
    {
        $io = new BufferIO();
        $app = (new CLIApplication('TestApp', '1.0.0'))
            ->withIO($io)
            ->add(new PingCommand());

        $app->run([]);
        $output = $io->getOutput();

        $this->assertStringContainsString('ping', $output);
    }

    // ---------------------------------------------------------------
    // Not-found handling
    // ---------------------------------------------------------------

    public function test_unknown_command_returns_invalid(): void
    {
        $io = new BufferIO();
        $app = (new CLIApplication('TestApp', '1.0.0'))->withIO($io);

        $exit = $app->run(['nonexistent']);

        $this->assertSame(AbstractCommand::INVALID, $exit);
    }

    // ---------------------------------------------------------------
    // has / get
    // ---------------------------------------------------------------

    public function test_has_returns_true_for_registered_command(): void
    {
        $app = $this->makeApp();

        $this->assertTrue($app->has('ping'));
        $this->assertFalse($app->has('missing'));
    }

    public function test_get_returns_registered_command(): void
    {
        $app = $this->makeApp();

        $this->assertInstanceOf(PingCommand::class, $app->get('ping'));
    }

    public function test_get_throws_for_unknown_command(): void
    {
        $app = $this->makeApp();

        $this->expectException(\InvalidArgumentException::class);
        $app->get('ghost');
    }

    // ---------------------------------------------------------------
    // all()
    // ---------------------------------------------------------------

    public function test_all_returns_registered_commands_sorted(): void
    {
        $app = $this->makeApp();
        $keys = array_keys($app->all());

        // Expect alphabetical order
        $this->assertContains('greet', $keys);
        $this->assertContains('ping', $keys);
        $this->assertLessThan(array_search('ping', $keys, true), array_search('greet', $keys, true));
    }

    // ---------------------------------------------------------------
    // catchExceptions
    // ---------------------------------------------------------------

    public function test_catch_exceptions_false_rethrows(): void
    {
        $io = new BufferIO();

        // Create a command that throws
        $cmd = new class extends AbstractCommand {
            protected function configure(): void
            {
                $this->name = 'boom';
            }

            protected function handle(): int
            {
                throw new \RuntimeException('Boom!');
            }
        };

        $app = (new CLIApplication())
            ->withIO($io)
            ->catchExceptions(false)
            ->add($cmd);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Boom!');

        $app->run(['boom']);
    }

    private function makeApp(): CLIApplication
    {
        $io = new BufferIO();

        return (new CLIApplication('TestApp', '1.0.0'))
            ->withIO($io)
            ->add(new PingCommand(), new GreetCommand());
    }
}
