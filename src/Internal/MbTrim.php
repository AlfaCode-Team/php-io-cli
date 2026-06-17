<?php

declare(strict_types=1);

namespace AlfacodeTeam\PhpIoCli\Internal;

/**
 * Backing implementation for the mb_trim/mb_ltrim/mb_rtrim polyfills (PHP < 8.4).
 *
 * @internal
 */
final class MbTrim
{
    /** Default whitespace set, matching the native PHP 8.4 mb_trim behaviour. */
    private const DEFAULT = " \f\n\r\t\x0B\x00";

    public static function run(string $string, ?string $characters, bool $left, bool $right): string
    {
        $characters ??= self::DEFAULT;

        if ($characters === '') {
            return $string;
        }

        // Split the character list into individual (possibly multibyte) characters.
        $chars = preg_split('//u', $characters, -1, PREG_SPLIT_NO_EMPTY);
        if ($chars === false || $chars === []) {
            return $string;
        }

        $class = '';
        foreach ($chars as $char) {
            $class .= preg_quote($char, '/');
        }

        $pattern = '';
        if ($left) {
            $pattern .= '^[' . $class . ']+';
        }
        if ($left && $right) {
            $pattern .= '|';
        }
        if ($right) {
            $pattern .= '[' . $class . ']+$';
        }

        $result = preg_replace('/' . $pattern . '/u', '', $string);

        return $result ?? $string;
    }
}
