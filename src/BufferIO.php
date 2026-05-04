<?php

declare(strict_types=1);

namespace AlfacodeTeam\PhpIoCli;

use AlfacodeTeam\PhpIoCli\Depends\Colors;
use Symfony\Component\Console\Formatter\OutputFormatterInterface;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\StreamableInputInterface;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\StreamOutput;

/**
 * Captures CLI output in memory for testing and allows simulated user input.
 */
class BufferIO extends ConsoleIO
{
    public function __construct(
        string $input = '',
        int $verbosity = StreamOutput::VERBOSITY_NORMAL,
        OutputFormatterInterface|null $formatter = null,
    ) {
        $inputInstance = new StringInput($input);
        $inputInstance->setInteractive(false);

        $stream = fopen('php://memory', 'rw');
        if ($stream === false) {
            throw new \RuntimeException('Unable to open memory output stream');
        }

        // Use the decorator if provided, or default to false for memory buffers
        $decorated = $formatter !== null ? $formatter->isDecorated() : false;
        $output = new StreamOutput($stream, $verbosity, $decorated, $formatter);

        parent::__construct($inputInstance, $output, new HelperSet([
            new QuestionHelper(),
        ]));
    }

    /**
     * Retrieves the captured output and strips unnecessary control characters.
     */
    public function getOutput(): string
    {
        assert($this->output instanceof StreamOutput);
        fseek($this->output->getStream(), 0);

        $output = (string) stream_get_contents($this->output->getStream());

        // Use the Colors::strip helper you built earlier to clean up ANSI
        return Colors::strip($this->cleanBackspaces($output));
    }

    /**
     * Simulated interaction for testing prompts.
     *
     * @param string[] $inputs Array of keys/strings to "type"
     */
    public function setUserInputs(array $inputs): void
    {

        if (!$this->input instanceof StreamableInputInterface) {
            throw new \RuntimeException('Setting the user inputs requires at least the version 3.2 of the symfony/console component.');
        }

        $this->input->setStream($this->createStream($inputs));
        $this->input->setInteractive(true);
    }

    /**
     * Handles the cleanup of backspace characters (\x08)
     */
    private function cleanBackspaces(string $output): string
    {
        return (string) preg_replace_callback("{(?<=^|\n|\x08)(.+?)(\x08+)}", static function ($matches): string {
            $pre = strip_tags($matches[1]);
            if (mb_strlen($pre) === mb_strlen($matches[2])) {
                return '';
            }

            return mb_rtrim($matches[1]) . "\n";
        }, $output);
    }

    /**
     * @param string[] $inputs
     *
     * @return resource stream
     */
    private function createStream(array $inputs)
    {
        $stream = fopen('php://memory', 'r+');
        if ($stream === false) {
            throw new \RuntimeException('Unable to open memory output stream');
        }

        foreach ($inputs as $input) {
            fwrite($stream, $input . PHP_EOL);
        }

        rewind($stream);

        return $stream;
    }
}
