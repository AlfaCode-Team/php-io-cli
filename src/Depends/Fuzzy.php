<?php

declare(strict_types=1);

namespace AlfacodeTeam\PhpIoCli\Depends;

final class Fuzzy
{
    /**
     * Filter and rank items based on a query.
     */
    public static function filter(array $items, string $query, int $minScore = 0): array
    {
        if ($query === '') {
            return $items;
        }

        $scored = [];
        foreach ($items as $index => $item) {
            $score = self::score($query, (string) $item, $index);

            // Only include items that meet a minimum relevancy threshold
            if ($score > $minScore) {
                $scored[$index] = $score;
            }
        }

        arsort($scored, SORT_NUMERIC);

        return array_map(
            static fn($i) => $items[$i],
            array_keys($scored),
        );
    }

    /**
     * Calculate a ranking score for a value against a query.
     */
    public static function score(string $query, string $value, int $tieBreaker = 0): int
    {
        $query = mb_strtolower(mb_trim($query));
        $value = mb_strtolower(mb_trim($value));

        if ($query === '') {
            return 0;
        }
        if ($query === $value) {
            return 10000;
        }

        $score = 0;

        // 1. Prefix Match
        if (str_starts_with($value, $query)) {
            $score = 9000 - mb_strlen($value);
        }
        // 2. Substring Match
        elseif (str_contains($value, $query)) {
            $score = 8000 - mb_strlen($value);
        }
        // 3. Abbreviation / "In-order" Match (e.g., "gc" matches "git commit")
        elseif (self::isAbbreviation($query, $value)) {
            $score = 7000 - mb_strlen($value);
        }
        // No other matches
        else {
            return -1 - $tieBreaker;
        }

        // 4. Token-based match bonus (multi-word)
        $queryTokens = explode(' ', $query);
        $valueTokens = explode(' ', $value);
        $score += self::tokenScore($queryTokens, $valueTokens);

        // Final score minus tiebreaker for stable sorting
        return $score - $tieBreaker;
    }

    /**
     * Checks if characters in the query appear in order within the value.
     */
    private static function isAbbreviation(string $query, string $value): bool
    {
        $qLen = mb_strlen($query);
        $vLen = mb_strlen($value);

        if ($qLen > $vLen) {
            return false;
        }

        $qIdx = 0;
        for ($vIdx = 0; $vIdx < $vLen; $vIdx++) {
            if ($value[$vIdx] === $query[$qIdx]) {
                $qIdx++;
            }
            if ($qIdx === $qLen) {
                return true;
            }
        }

        return false;
    }

    private static function tokenScore(array $queryTokens, array $valueTokens): int
    {
        $score = 0;
        foreach ($queryTokens as $q) {
            if ($q === '') {
                continue;
            }
            foreach ($valueTokens as $v) {
                if ($q === $v) {
                    $score += 500;
                } elseif (str_starts_with($v, $q)) {
                    $score += 200;
                }
            }
        }

        return $score;
    }
}
