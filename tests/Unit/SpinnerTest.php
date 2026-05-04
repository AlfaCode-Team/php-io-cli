<?php

declare(strict_types=1);

namespace AlfacodeTeam\PhpIoCli\Tests\Unit;

use AlfacodeTeam\PhpIoCli\Depends\Spinner;
use AlfacodeTeam\PhpIoCli\Depends\SpinnerFrames;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Spinner::class)]
final class SpinnerTest extends TestCase
{
    // ---------------------------------------------------------------
    // stop() returns empty string
    // ---------------------------------------------------------------

    public function test_tick_returns_empty_string_when_not_started(): void
    {
        $spinner = new Spinner(SpinnerFrames::line());

        $this->assertSame('', $spinner->tick());
    }

    public function test_tick_returns_empty_string_after_stop(): void
    {
        $spinner = new Spinner(SpinnerFrames::line());
        $spinner->start();
        $spinner->stop();

        $this->assertSame('', $spinner->tick());
    }

    // ---------------------------------------------------------------
    // tick() returns a frame string when running
    // ---------------------------------------------------------------

    public function test_tick_returns_non_empty_string_when_running(): void
    {
        $spinner = new Spinner(SpinnerFrames::dots());
        $spinner->start();

        $frame = $spinner->tick();

        $this->assertIsString($frame);
        $this->assertNotSame('', $frame);
    }

    public function test_tick_returns_value_from_provided_frames(): void
    {
        $frames = ['A', 'B', 'C'];
        $spinner = new Spinner($frames);
        $spinner->start();

        $frame = $spinner->tick();

        $this->assertContains($frame, $frames);
    }

    // ---------------------------------------------------------------
    // tick() advances the frame index over time
    // ---------------------------------------------------------------

    public function test_tick_advances_frame_after_interval(): void
    {
        // Use a very short interval so we don't wait long in tests
        $frames = ['X', 'Y', 'Z'];
        $spinner = new Spinner($frames, interval: 0.001); // 1 ms interval
        $spinner->start();

        $first = $spinner->tick();

        // Sleep past the interval to force a frame advance
        usleep(5_000); // 5 ms

        $second = $spinner->tick();

        // After enough time, the frame should have advanced
        // (X → Y or further)
        $this->assertNotSame($first, $second, 'Frame should advance after the interval elapses');
    }

    public function test_frames_wrap_around_cyclically(): void
    {
        // Two frames, very short interval — tick many times to confirm cycling
        $frames = ['F1', 'F2'];
        $spinner = new Spinner($frames, interval: 0.0001);
        $spinner->start();

        $seen = [];
        for ($i = 0; $i < 40; $i++) {
            usleep(500);
            $seen[] = $spinner->tick();
        }

        $unique = array_unique($seen);
        sort($unique);

        $this->assertSame(['F1', 'F2'], $unique, 'Both frames should appear during cycling');
    }

    // ---------------------------------------------------------------
    // Default frames (no constructor arg) use SpinnerFrames::default()
    // ---------------------------------------------------------------

    public function test_default_frames_are_used_when_none_provided(): void
    {
        $spinner = new Spinner();
        $spinner->start();

        $frame = $spinner->tick();

        $this->assertContains($frame, SpinnerFrames::default());
    }

    // ---------------------------------------------------------------
    // start() / stop() are idempotent
    // ---------------------------------------------------------------

    public function test_calling_start_twice_does_not_throw(): void
    {
        $spinner = new Spinner(SpinnerFrames::line());
        $spinner->start();
        $spinner->start(); // second call — should not throw

        $this->assertNotSame('', $spinner->tick());
    }

    public function test_calling_stop_twice_does_not_throw(): void
    {
        $spinner = new Spinner(SpinnerFrames::line());
        $spinner->start();
        $spinner->stop();
        $spinner->stop(); // second call — should not throw

        $this->assertSame('', $spinner->tick());
    }

    // ---------------------------------------------------------------
    // tick() does not advance below interval
    // ---------------------------------------------------------------

    public function test_tick_does_not_advance_before_interval(): void
    {
        // Long interval — frame should NOT change between two rapid ticks
        $frames = ['A', 'B', 'C'];
        $spinner = new Spinner($frames, interval: 60.0); // 60-second interval
        $spinner->start();

        $first = $spinner->tick();
        $second = $spinner->tick(); // called immediately — no time has passed

        $this->assertSame($first, $second, 'Frame must not advance before the interval elapses');
    }
}
