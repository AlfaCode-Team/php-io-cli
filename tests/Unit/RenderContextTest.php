<?php

declare(strict_types=1);

namespace AlfacodeTeam\PhpIoCli\Tests\Unit;

use AlfacodeTeam\PhpIoCli\Depends\RenderContext;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AlfacodeTeam\PhpIoCli\Depends\RenderContext
 */
final class RenderContextTest extends TestCase
{
    public function test_default_dirty_is_true(): void
    {
        $ctx = new RenderContext();

        $this->assertTrue($ctx->dirty);
    }

    public function test_mark_dirty_sets_dirty_true(): void
    {
        $ctx = new RenderContext(dirty: false);
        $ctx->markDirty();

        $this->assertTrue($ctx->dirty);
    }

    public function test_clear_sets_dirty_false(): void
    {
        $ctx = new RenderContext();
        $ctx->clear();

        $this->assertFalse($ctx->dirty);
        $this->assertFalse($ctx->shouldRender());
    }

    public function test_should_render_reflects_dirty_flag(): void
    {
        $ctx = new RenderContext(dirty: false);
        $this->assertFalse($ctx->shouldRender());

        $ctx->markDirty();
        $this->assertTrue($ctx->shouldRender());
    }

    public function test_set_and_get_meta(): void
    {
        $ctx = new RenderContext();
        $ctx->set('step', 3);

        $this->assertSame(3, $ctx->get('step'));
    }

    public function test_get_meta_returns_default_when_missing(): void
    {
        $ctx = new RenderContext();

        $this->assertSame('default', $ctx->get('nonexistent', 'default'));
        $this->assertNull($ctx->get('nonexistent'));
    }

    public function test_mark_dirty_returns_self(): void
    {
        $ctx = new RenderContext(dirty: false);

        $this->assertSame($ctx, $ctx->markDirty());
    }

    public function test_clear_returns_self(): void
    {
        $ctx = new RenderContext();

        $this->assertSame($ctx, $ctx->clear());
    }

    public function test_default_dimensions_are_positive(): void
    {
        $ctx = new RenderContext();

        $this->assertGreaterThan(0, $ctx->width);
        $this->assertGreaterThan(0, $ctx->height);
    }
}
