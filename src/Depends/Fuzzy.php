<?php
declare(strict_types=1);

namespace AlfacodeTeam\PhpIoCli\Depends;

/* =========================================================
   LEVENSHTEIN FUZZY MATCH (Laravel Prompts-like ranking)
========================================================= */
final class Fuzzy
{
    /* =========================================================
       PUBLIC API
    ========================================================= */

    public static function filter(array $items, string $query): array
    {
        if ($query === '') {
            return $items;
        }

        $scored = [];

        foreach ($items as $index => $item) {
            $scored[$index] = self::score($query, (string) $item, $index);
        }

        arsort($scored, SORT_NUMERIC);

        return array_map(
            fn ($i) => $items[$i],
            array_keys($scored)
        );
    }

    /* =========================================================
       CORE SCORING
    ========================================================= */

    public static function score(string $query, string $value, int $tieBreaker = 0): int
    {
        $query = strtolower(trim($query));
        $value = strtolower(trim($value));

        if ($query === '') {
            return 0;
        }

        // 1. exact match
        if ($query === $value) {
            return 10000;
        }

        // 2. prefix match (very strong)
        if (str_starts_with($value, $query)) {
            return 9000 - strlen($value);
        }

        // 3. substring match
        if (str_contains($value, $query)) {
            return 8000 - strlen($value);
        }

        // 4. token-based match (important for multi-word search)
        $queryTokens = explode(' ', $query);
        $valueTokens = explode(' ', $value);

        $tokenScore = self::tokenScore($queryTokens, $valueTokens);

        // 5. levenshtein fallback (last resort)
        $distance = levenshtein($query, $value);
        $levScore = max(0, 5000 - ($distance * 10));

        // final weighted score
        $score = $tokenScore + $levScore;

        // tie breaker for stable ordering
        return $score - $tieBreaker;
    }

    /* =========================================================
       TOKEN MATCHING (fzf-style behavior)
    ========================================================= */

    private static function tokenScore(array $queryTokens, array $valueTokens): int
    {
        $score = 0;

        foreach ($queryTokens as $q) {
            foreach ($valueTokens as $v) {

                if ($q === $v) {
                    $score += 200;
                } elseif (str_starts_with($v, $q)) {
                    $score += 120;
                } elseif (str_contains($v, $q)) {
                    $score += 80;
                }
            }
        }

        return $score;
    }
}