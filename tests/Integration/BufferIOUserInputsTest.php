<?php

declare(strict_types=1);

namespace AlfacodeTeam\PhpIoCli\Tests\Integration;

use AlfacodeTeam\PhpIoCli\AbstractCommand;
use AlfacodeTeam\PhpIoCli\BufferIO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

// ── Fixtures ─────────────────────────────────────────────────────────────────

/**
 * Command that asks a confirm prompt, then echoes the result.
 */
final class ConfirmCommand extends AbstractCommand
{
    // Expose the IO so we can call it directly in the fixture
    private \AlfacodeTeam\PhpIoCli\IOInterface|null $ioRef = null;

    public function setIORef(\AlfacodeTeam\PhpIoCli\IOInterface $io): void
    {
        $this->ioRef = $io;
    }

    protected function configure(): void
    {
        $this->name = 'confirm-cmd';
        $this->description = 'Asks a yes/no question';
    }

    protected function handle(): int
    {
        $answer = $this->io()->askConfirmation('Do you want to proceed?', false);

        if ($answer) {
            $this->info('Proceeding!');
        } else {
            $this->info('Aborted.');
        }

        return self::SUCCESS;
    }

    private function io(): \AlfacodeTeam\PhpIoCli\IOInterface
    {
        // AbstractCommand stores IO internally; we replicate via reflection
        $ref = new \ReflectionObject($this);
        // walk up to AbstractCommand
        $parent = $ref->getParentClass();
        if ($parent === false) {
            throw new \RuntimeException('No parent');
        }
        $prop = $parent->getProperty('io');
        $prop->setAccessible(true);

        return $prop->getValue($this);
    }
}

/**
 * Command that asks for a selection and reports the choice.
 */
final class SelectCommand extends AbstractCommand
{
    protected function configure(): void
    {
        $this->name = 'select-cmd';
        $this->description = 'Asks the user to pick an environment';
    }

    protected function handle(): int
    {
        $choice = $this->ioInstance()->select(
            'Pick environment',
            ['production', 'staging', 'development'],
            'staging',
        );

        $this->info("Selected: {$choice}");

        return self::SUCCESS;
    }

    private function ioInstance(): \AlfacodeTeam\PhpIoCli\IOInterface
    {
        $ref = new \ReflectionObject($this);
        $parent = $ref->getParentClass();
        if ($parent === false) {
            throw new \RuntimeException('No parent');
        }
        $prop = $parent->getProperty('io');
        $prop->setAccessible(true);

        return $prop->getValue($this);
    }
}

/**
 * Command that asks free-text, a confirm, and then echoes both.
 */
final class MultiPromptCommand extends AbstractCommand
{
    protected function configure(): void
    {
        $this->name = 'multi-prompt';
        $this->description = 'Multiple prompts in sequence';
    }

    protected function handle(): int
    {
        $io = $this->ioInstance();
        $name = $io->ask('What is your name?', 'World');
        $ok = $io->askConfirmation("Hello {$name}, continue?", true);

        if ($ok) {
            $this->info("Hello, {$name}!");
        } else {
            $this->info('Cancelled.');
        }

        return self::SUCCESS;
    }

    private function ioInstance(): \AlfacodeTeam\PhpIoCli\IOInterface
    {
        $ref = new \ReflectionObject($this);
        $parent = $ref->getParentClass();
        if ($parent === false) {
            throw new \RuntimeException('No parent');
        }
        $prop = $parent->getProperty('io');
        $prop->setAccessible(true);

        return $prop->getValue($this);
    }
}

// ── Tests ─────────────────────────────────────────────────────────────────────

#[CoversClass(BufferIO::class)]
final class BufferIOUserInputsTest extends TestCase
{
    // ---------------------------------------------------------------
    // Confirm prompt — user answers "yes"
    // ---------------------------------------------------------------

    public function test_confirm_prompt_with_yes_input(): void
    {
        $io = new BufferIO();
        $io->setUserInputs(['yes']);

        $cmd = new ConfirmCommand();
        $exit = $cmd->execute([], $io);

        $this->assertSame(AbstractCommand::SUCCESS, $exit);
        $this->assertStringContainsString('Proceeding!', $io->getOutput());
    }

    // ---------------------------------------------------------------
    // Confirm prompt — user answers "no"
    // ---------------------------------------------------------------

    public function test_confirm_prompt_with_no_input(): void
    {
        $io = new BufferIO();
        $io->setUserInputs(['no']);

        $cmd = new ConfirmCommand();
        $exit = $cmd->execute([], $io);

        $this->assertSame(AbstractCommand::SUCCESS, $exit);
        $this->assertStringContainsString('Aborted.', $io->getOutput());
    }

    // ---------------------------------------------------------------
    // Select prompt — picks the second option
    // ---------------------------------------------------------------

    public function test_select_prompt_with_pre_set_choice(): void
    {
        $io = new BufferIO();
        // Symfony ChoiceQuestion accepts the option value as input
        $io->setUserInputs(['staging']);

        $cmd = new SelectCommand();
        $exit = $cmd->execute([], $io);

        $this->assertSame(AbstractCommand::SUCCESS, $exit);
        $this->assertStringContainsString('staging', $io->getOutput());
    }

    // ---------------------------------------------------------------
    // Select prompt — picks by index (Symfony also accepts numeric index)
    // ---------------------------------------------------------------

    public function test_select_prompt_with_numeric_index(): void
    {
        $io = new BufferIO();
        $io->setUserInputs(['1']); // index 1 → 'staging'

        $cmd = new SelectCommand();
        $exit = $cmd->execute([], $io);

        $this->assertSame(AbstractCommand::SUCCESS, $exit);
        $this->assertStringContainsString('staging', $io->getOutput());
    }

    // ---------------------------------------------------------------
    // Multiple sequential prompts
    // ---------------------------------------------------------------

    public function test_multiple_prompts_consume_inputs_in_order(): void
    {
        $io = new BufferIO();
        $io->setUserInputs(['Alice', 'yes']);

        $cmd = new MultiPromptCommand();
        $exit = $cmd->execute([], $io);

        $this->assertSame(AbstractCommand::SUCCESS, $exit);
        $this->assertStringContainsString('Hello, Alice!', $io->getOutput());
    }

    public function test_multiple_prompts_with_declined_confirmation(): void
    {
        $io = new BufferIO();
        $io->setUserInputs(['Bob', 'no']);

        $cmd = new MultiPromptCommand();
        $exit = $cmd->execute([], $io);

        $this->assertSame(AbstractCommand::SUCCESS, $exit);
        $this->assertStringContainsString('Cancelled.', $io->getOutput());
    }

    // ---------------------------------------------------------------
    // setUserInputs makes io interactive
    // ---------------------------------------------------------------

    public function test_set_user_inputs_marks_io_as_interactive(): void
    {
        $io = new BufferIO();
        $this->assertFalse($io->isInteractive());

        $io->setUserInputs(['yes']);
        $this->assertTrue($io->isInteractive());
    }

    // ---------------------------------------------------------------
    // Output capture still works with interactive inputs set
    // ---------------------------------------------------------------

    public function test_output_is_still_captured_with_user_inputs(): void
    {
        $io = new BufferIO();
        $io->setUserInputs(['yes']);
        $io->write('Captured line');

        $this->assertStringContainsString('Captured line', $io->getOutput());
    }
}
