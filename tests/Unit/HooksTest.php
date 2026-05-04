<?php

declare(strict_types=1);

namespace AlfacodeTeam\PhpIoCli\Tests\Unit;

use AlfacodeTeam\PhpIoCli\Hooks;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(\AlfacodeTeam\PhpIoCli\Hooks::class)]
final class HooksTest extends TestCase
{
    private Hooks $hooks;

    protected function setUp(): void
    {
        $this->hooks = new Hooks();
    }

    // ---------------------------------------------------------------
    // on / dispatch
    // ---------------------------------------------------------------

    public function test_listener_is_called_on_dispatch(): void
    {
        $called = false;

        $this->hooks->on('test', function () use (&$called): void {
            $called = true;
        });

        $this->hooks->dispatch('test');

        $this->assertTrue($called);
    }

    public function test_dispatch_passes_payload_to_listener(): void
    {
        $received = null;

        $this->hooks->on('data', function (mixed $payload) use (&$received): void {
            $received = $payload;
        });

        $this->hooks->dispatch('data', ['key' => 'value']);

        $this->assertSame(['key' => 'value'], $received);
    }

    public function test_multiple_listeners_all_fire(): void
    {
        $log = [];

        $this->hooks->on('event', function () use (&$log): void {
            $log[] = 'A';
        });
        $this->hooks->on('event', function () use (&$log): void {
            $log[] = 'B';
        });
        $this->hooks->on('event', function () use (&$log): void {
            $log[] = 'C';
        });

        $this->hooks->dispatch('event');

        $this->assertSame(['A', 'B', 'C'], $log);
    }

    public function test_dispatch_on_unknown_event_does_nothing(): void
    {
        // Should not throw
        $this->hooks->dispatch('nonexistent');
        $this->assertTrue(true);
    }

    // ---------------------------------------------------------------
    // once
    // ---------------------------------------------------------------

    public function test_once_listener_fires_only_once(): void
    {
        $count = 0;

        $this->hooks->once('tick', function () use (&$count): void {
            $count++;
        });

        $this->hooks->dispatch('tick');
        $this->hooks->dispatch('tick');
        $this->hooks->dispatch('tick');

        $this->assertSame(1, $count);
    }

    // ---------------------------------------------------------------
    // off
    // ---------------------------------------------------------------

    public function test_off_removes_specific_listener(): void
    {
        $count = 0;

        $listener = function () use (&$count): void {
            $count++;
        };

        $this->hooks->on('click', $listener);
        $this->hooks->off('click', $listener);
        $this->hooks->dispatch('click');

        $this->assertSame(0, $count);
    }

    public function test_off_without_listener_removes_all(): void
    {
        $count = 0;

        $this->hooks->on('event', function () use (&$count): void {
            $count++;
        });
        $this->hooks->on('event', function () use (&$count): void {
            $count++;
        });

        $this->hooks->off('event');
        $this->hooks->dispatch('event');

        $this->assertSame(0, $count);
    }

    public function test_off_on_unknown_event_does_nothing(): void
    {
        $this->hooks->off('ghost_event');
        $this->assertTrue(true);
    }

    // ---------------------------------------------------------------
    // dispatchUntil — Chain of Responsibility
    // ---------------------------------------------------------------

    public function test_dispatch_until_stops_at_first_non_null_return(): void
    {
        $log = [];

        $this->hooks->on('validate', function () use (&$log): ?string {
            $log[] = 'first';
            return null;
        });

        $this->hooks->on('validate', function () use (&$log): ?string {
            $log[] = 'second';
            return 'HANDLED';
        });

        $this->hooks->on('validate', function () use (&$log): ?string {
            $log[] = 'third'; // should NOT run
            return null;
        });

        $result = $this->hooks->dispatchUntil('validate');

        $this->assertSame('HANDLED', $result);
        $this->assertSame(['first', 'second'], $log);
    }

    public function test_dispatch_until_returns_null_when_no_listener_handles(): void
    {
        $this->hooks->on('event', fn() => null);

        $result = $this->hooks->dispatchUntil('event');

        $this->assertNull($result);
    }

    public function test_dispatch_until_returns_null_on_unknown_event(): void
    {
        $result = $this->hooks->dispatchUntil('unknown');

        $this->assertNull($result);
    }

    // ---------------------------------------------------------------
    // Fluent API
    // ---------------------------------------------------------------

    public function test_on_and_off_return_self_for_chaining(): void
    {
        $result = $this->hooks->on('a', fn() => null)->off('a');

        $this->assertSame($this->hooks, $result);
    }
}
