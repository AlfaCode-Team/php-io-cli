<?php

declare(strict_types=1);

namespace AlfacodeTeam\PhpIoCli\Components;

use AlfacodeTeam\PhpIoCli\Depends\Colors;

/**
 * Renders attention-grabbing banners / alert boxes.
 *
 * Usage:
 *   Alert::success('Deployment complete!');
 *   Alert::error('Build failed', 'Check the logs at /var/log/build.log');
 *   Alert::warning('Low disk space', ['Used: 89%', 'Available: 4.2 GB']);
 *   Alert::info('New version available: 2.4.1');
  * Enterprise Alert Banners
 * Renders ANSI-safe, styled notification boxes.
 */
final class Alert
{
    private function __construct() {}

    public static function success(string $title, string|array $body = []): void
    {
        self::render($title, (array) $body, '✔', Colors::GREEN);
    }

    public static function error(string $title, string|array $body = []): void
    {
        self::render($title, (array) $body, '✘', Colors::RED);
    }

    public static function warning(string $title, string|array $body = []): void
    {
        self::render($title, (array) $body, '!', Colors::YELLOW);
    }

    public static function info(string $title, string|array $body = []): void
    {
        self::render($title, (array) $body, 'i', Colors::CYAN);
    }

    /**
     * Renders a solid background block (Enterprise Error Style)
     */
    public static function block(string $title, string|array $body = [], string $color = Colors::RED): void
    {
        $body = (array) $body;
        $visualWidth = self::calculateMaxWidth($title, $body) + 4;

        echo PHP_EOL;
        // Top Padding
        echo Colors::wrap(str_repeat(' ', $visualWidth), $color . '48') . PHP_EOL; // 48 = BG

        // Title Line
        $titleLine = "  " . strtoupper($title);
        echo Colors::wrap(self::padVisual($titleLine, $visualWidth), [Colors::BOLD, $color . '48', Colors::WHITE]) . PHP_EOL;

        // Body
        foreach ($body as $line) {
            echo Colors::wrap(self::padVisual("  " . $line, $visualWidth), $color . '48') . PHP_EOL;
        }

        // Bottom Padding
        echo Colors::wrap(str_repeat(' ', $visualWidth), $color . '48') . PHP_EOL . PHP_EOL;
    }

    /* =========================================================
       Internal Rendering Engine
    ========================================================= */

    private static function render(string $title, array $body, string $icon, string $color): void
    {
        $titleText = " {$icon} {$title}";
        $innerWidth = self::calculateMaxWidth($titleText, $body) + 2;

        $t = Colors::muted($color); // Border color

        // 1. Top Border
        echo PHP_EOL . Colors::wrap('┌' . str_repeat('─', $innerWidth) . '┐', $t) . PHP_EOL;

        // 2. Title Line
        $formattedTitle = " " . Colors::wrap($icon, [$color, Colors::BOLD]) . " " . Colors::wrap($title, Colors::BOLD);
        echo Colors::wrap('│', $t) . self::padVisual($formattedTitle, $innerWidth) . Colors::wrap('│', $t) . PHP_EOL;

        // 3. Body Content
        if (!empty($body)) {
            echo Colors::wrap('├' . str_repeat('─', $innerWidth) . '┤', $t) . PHP_EOL;
            foreach ($body as $line) {
                echo Colors::wrap('│', $t) . self::padVisual("  " . $line, $innerWidth) . Colors::wrap('│', $t) . PHP_EOL;
            }
        }

        // 4. Bottom Border
        echo Colors::wrap('└' . str_repeat('─', $innerWidth) . '┘', $t) . PHP_EOL . PHP_EOL;
    }

    private static function calculateMaxWidth(string $title, array $body): int
    {
        $max = mb_strlen(Colors::strip($title));
        foreach ($body as $line) {
            $max = max($max, mb_strlen(Colors::strip((string) $line)));
        }
        return $max;
    }

    /**
     * ANSI-Safe Padding
     * Ensures visual alignment even when strings contain hidden escape codes.
     */
    private static function padVisual(string $text, int $width): string
    {
        $visualLen = mb_strlen(Colors::strip($text));
        return $text . str_repeat(' ', max(0, $width - $visualLen));
    }
}
