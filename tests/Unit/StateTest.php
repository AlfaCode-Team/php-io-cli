<?php

declare(strict_types=1);

namespace AlfacodeTeam\PhpIoCli\Tests\Unit;

use AlfacodeTeam\PhpIoCli\Depends\State;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AlfacodeTeam\PhpIoCli\Depends\State
 */
final class StateTest extends TestCase
{
    // ---------------------------------------------------------------
    // Basic get / set
    // ---------------------------------------------------------------

    public function test_initial_data_is_accessible(): void
    {
        $state = new State(['name' => 'Alice', 'count' => 42]);

        $this->assertSame('Alice', $state->name);
        $this->assertSame(42, $state->count);
    }

    public function test_missing_key_returns_null(): void
    {
        $state = new State();

        $this->assertNull($state->nonexistent);
    }

    public function test_get_with_default_returns_fallback(): void
    {
        $state = new State();

        $this->assertSame('fallback', $state->get('missing', 'fallback'));
    }

    public function test_set_updates_value(): void
    {
        $state = new State(['count' => 0]);
        $state->count = 5;

        $this->assertSame(5, $state->count);
    }

    public function test_set_same_value_does_not_trigger_watcher(): void
    {
        $state = new State(['x' => 1]);
        $calls = 0;

        $state->watch('x', function () use (&$calls): void {
            $calls++;
        });

        $state->x = 1; // same value — should not notify
        $this->assertSame(0, $calls);
    }

    // ---------------------------------------------------------------
    // Batch
    // ---------------------------------------------------------------

    public function test_batch_sets_multiple_keys(): void
    {
        $state = new State();
        $state->batch(['a' => 1, 'b' => 2, 'c' => 3]);

        $this->assertSame(1, $state->a);
        $this->assertSame(2, $state->b);
        $this->assertSame(3, $state->c);
    }

    // ---------------------------------------------------------------
    // Increment / Decrement
    // ---------------------------------------------------------------

    public function test_increment_increases_value(): void
    {
        $state = new State(['index' => 0]);
        $state->increment('index', 5);

        $this->assertSame(1, $state->index);
    }

    public function test_increment_clamps_at_max(): void
    {
        $state = new State(['index' => 5]);
        $state->increment('index', 5);

        $this->assertSame(5, $state->index);
    }

    public function test_decrement_decreases_value(): void
    {
        $state = new State(['index' => 3]);
        $state->decrement('index');

        $this->assertSame(2, $state->index);
    }

    public function test_decrement_clamps_at_zero(): void
    {
        $state = new State(['index' => 0]);
        $state->decrement('index');

        $this->assertSame(0, $state->index);
    }

    // ---------------------------------------------------------------
    // Toggle (multi-select)
    // ---------------------------------------------------------------

    public function test_toggle_adds_value_when_absent(): void
    {
        $state = new State(['selected' => []]);
        $state->toggle('selected', 'Auth');

        $this->assertSame(['Auth'], $state->selected);
    }

    public function test_toggle_removes_value_when_present(): void
    {
        $state = new State(['selected' => ['Auth', 'API']]);
        $state->toggle('selected', 'Auth');

        $this->assertSame(['API'], $state->selected);
    }

    public function test_toggle_re_indexes_array(): void
    {
        $state = new State(['selected' => ['A', 'B', 'C']]);
        $state->toggle('selected', 'B');

        $this->assertSame([0 => 'A', 1 => 'C'], $state->selected);
    }

    // ---------------------------------------------------------------
    // Watchers
    // ---------------------------------------------------------------

    public function test_watcher_fires_on_change(): void
    {
        $state  = new State(['score' => 0]);
        $newVal = null;
        $oldVal = null;

        $state->watch('score', function (mixed $new, mixed $old) use (&$newVal, &$oldVal): void {
            $newVal = $new;
            $oldVal = $old;
        });

        $state->score = 10;

        $this->assertSame(10, $newVal);
        $this->assertSame(0, $oldVal);
    }

    public function test_multiple_watchers_all_fire(): void
    {
        $state = new State(['x' => 0]);
        $calls = [];

        $state->watch('x', function () use (&$calls): void {
            $calls[] = 'first';
        });
        $state->watch('x', function () use (&$calls): void {
            $calls[] = 'second';
        });

        $state->x = 99;

        $this->assertSame(['first', 'second'], $calls);
    }

    public function test_watcher_receives_state_reference(): void
    {
        $state = new State(['x' => 0]);
        $capturedState = null;

        $state->watch('x', function (mixed $new, mixed $old, State $s) use (&$capturedState): void {
            $capturedState = $s;
        });

        $state->x = 1;

        $this->assertSame($state, $capturedState);
    }
}
