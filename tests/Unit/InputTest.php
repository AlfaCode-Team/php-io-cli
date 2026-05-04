<?php

declare(strict_types=1);

namespace AlfacodeTeam\PhpIoCli\Tests\Unit;

use AlfacodeTeam\PhpIoCli\Depends\Input;
use AlfacodeTeam\PhpIoCli\Depends\State;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(\AlfacodeTeam\PhpIoCli\Depends\Input::class)]
final class InputTest extends TestCase
{
    // ---------------------------------------------------------------
    // Basic binding
    // ---------------------------------------------------------------

    public function test_bound_key_fires_handler(): void
    {
        $input = new Input();
        $state = new State(['count' => 0]);
        $fired = false;

        $input->bind('ENTER', function (State $s) use (&$fired): void {
            $fired = true;
        });

        $input->handle('ENTER', $state);

        $this->assertTrue($fired);
    }

    public function test_handler_receives_state(): void
    {
        $input    = new Input();
        $state    = new State(['value' => 'hello']);
        $received = null;

        $input->bind('UP', function (State $s) use (&$received): void {
            $received = $s;
        });

        $input->handle('UP', $state);

        $this->assertSame($state, $received);
    }

    public function test_unbound_key_does_not_throw(): void
    {
        $input = new Input();
        $state = new State();

        // Should silently do nothing
        $input->handle('X', $state);
        $this->assertTrue(true);
    }

    // ---------------------------------------------------------------
    // Multiple keys to same handler
    // ---------------------------------------------------------------

    public function test_bind_multiple_keys_array(): void
    {
        $input = new Input();
        $state = new State(['confirmed' => null]);

        $input->bind(['y', 'Y'], function (State $s): void {
            $s->confirmed = true;
        });

        $input->handle('y', $state);
        $this->assertTrue((bool) $state->confirmed);

        $state->confirmed = null;
        $input->handle('Y', $state);
        $this->assertTrue((bool) $state->confirmed);
    }

    // ---------------------------------------------------------------
    // Fallback
    // ---------------------------------------------------------------

    public function test_fallback_fires_for_unbound_key(): void
    {
        $input   = new Input();
        $state   = new State(['typed' => '']);
        $lastKey = null;

        $input->fallback(function (State $s, string $key) use (&$lastKey): void {
            $lastKey = $key;
            $s->typed .= $key;
        });

        $input->handle('a', $state);
        $input->handle('b', $state);

        $this->assertSame('ab', $state->typed);
        $this->assertSame('b', $lastKey);
    }

    public function test_fallback_does_not_fire_when_binding_exists(): void
    {
        $input      = new Input();
        $state      = new State();
        $fallbackRan = false;

        $input->bind('ENTER', function (State $s): void {});
        $input->fallback(function (State $s, string $key) use (&$fallbackRan): void {
            $fallbackRan = true;
        });

        $input->handle('ENTER', $state);

        $this->assertFalse($fallbackRan);
    }

    // ---------------------------------------------------------------
    // Stop propagation (return false)
    // ---------------------------------------------------------------

    public function test_return_false_stops_propagation(): void
    {
        $input = new Input();
        $state = new State();
        $log   = [];

        $input->bind('UP', function (State $s) use (&$log): false {
            $log[] = 'first';
            return false;
        });

        $input->bind('UP', function (State $s) use (&$log): void {
            $log[] = 'second'; // should not run
        });

        $input->handle('UP', $state);

        $this->assertSame(['first'], $log);
    }

    // ---------------------------------------------------------------
    // unbind
    // ---------------------------------------------------------------

    public function test_unbind_removes_handler(): void
    {
        $input = new Input();
        $state = new State(['x' => 0]);

        $input->bind('UP', function (State $s): void {
            $s->x++;
        });

        $input->unbind('UP');
        $input->handle('UP', $state);

        $this->assertSame(0, $state->x);
    }

    public function test_unbind_unknown_key_does_not_throw(): void
    {
        $input = new Input();
        $input->unbind('NONEXISTENT');

        $this->assertTrue(true);
    }

    // ---------------------------------------------------------------
    // Normalisation integration
    // ---------------------------------------------------------------

    public function test_handle_normalizes_key_before_dispatch(): void
    {
        $input = new Input();
        $state = new State(['moved' => false]);

        // Bind normalized name
        $input->bind('UP', function (State $s): void {
            $s->moved = true;
        });

        // Pass raw escape sequence — Input normalizes it
        $input->handle("\e[A", $state);

        $this->assertTrue((bool) $state->moved);
    }
}
