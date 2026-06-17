<?php

declare(strict_types=1);

/*
 * Polyfills for the multibyte trim functions introduced in PHP 8.4.
 * This library targets PHP 8.2+, so on PHP < 8.4 these functions are absent.
 * The implementations mirror the native behaviour: when no character list is
 * supplied they strip the same default whitespace set as the native functions.
 */

if (!function_exists('mb_trim')) {
    /**
     * @param string      $string
     * @param string|null $characters
     * @param string|null $encoding
     */
    function mb_trim(string $string, ?string $characters = null, ?string $encoding = null): string
    {
        return \AlfacodeTeam\PhpIoCli\Internal\MbTrim::run($string, $characters, true, true);
    }
}

if (!function_exists('mb_ltrim')) {
    function mb_ltrim(string $string, ?string $characters = null, ?string $encoding = null): string
    {
        return \AlfacodeTeam\PhpIoCli\Internal\MbTrim::run($string, $characters, true, false);
    }
}

if (!function_exists('mb_rtrim')) {
    function mb_rtrim(string $string, ?string $characters = null, ?string $encoding = null): string
    {
        return \AlfacodeTeam\PhpIoCli\Internal\MbTrim::run($string, $characters, false, true);
    }
}
