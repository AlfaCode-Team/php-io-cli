<?php

declare(strict_types=1);

namespace AlfacodeTeam\PhpIoCli\Tests\Unit;

use AlfacodeTeam\PhpIoCli\Depends\Fuzzy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Fuzzy::class)]
final class FuzzyTest extends TestCase
{
    // ---------------------------------------------------------------
    // filter — empty query
    // ---------------------------------------------------------------

    public function test_filter_empty_query_returns_all_items(): void
    {
        $items = ['PHP', 'Python', 'Go', 'Rust'];

        $this->assertSame($items, Fuzzy::filter($items, ''));
    }

    // ---------------------------------------------------------------
    // filter — exact match wins
    // ---------------------------------------------------------------

    public function test_filter_exact_match_scores_highest(): void
    {
        $items = ['Go', 'Golang', 'Django', 'Ruby'];
        $results = Fuzzy::filter($items, 'Go');

        $this->assertSame('Go', $results[0]);
    }

    // ---------------------------------------------------------------
    // filter — prefix match
    // ---------------------------------------------------------------

    public function test_filter_prefix_match_appears_early(): void
    {
        $items = ['auth-service', 'api-gateway', 'authentication', 'database'];
        $results = Fuzzy::filter($items, 'auth');

        $this->assertContains('auth-service', array_slice($results, 0, 2));
        $this->assertContains('authentication', array_slice($results, 0, 2));
        $this->assertNotContains('database', $results);
    }

    // ---------------------------------------------------------------
    // filter — substring match
    // ---------------------------------------------------------------

    public function test_filter_substring_match_is_included(): void
    {
        $items = ['user-management', 'payment-api', 'management-console'];
        $results = Fuzzy::filter($items, 'management');

        $this->assertContains('user-management', $results);
        $this->assertContains('management-console', $results);
        $this->assertNotContains('payment-api', $results);
    }

    // ---------------------------------------------------------------
    // filter — abbreviation / in-order match
    // ---------------------------------------------------------------

    public function test_filter_abbreviation_match_included(): void
    {
        $items = ['git commit', 'git checkout', 'grep content'];
        $results = Fuzzy::filter($items, 'gc');

        $this->assertContains('git commit', $results);
        $this->assertContains('git checkout', $results);
    }

    // ---------------------------------------------------------------
    // filter — no match returns empty
    // ---------------------------------------------------------------

    public function test_filter_no_match_returns_empty_array(): void
    {
        $items = ['Alpha', 'Beta', 'Gamma'];
        $results = Fuzzy::filter($items, 'zzzzzz');

        $this->assertEmpty($results);
    }

    // ---------------------------------------------------------------
    // score
    // ---------------------------------------------------------------

    public function test_score_exact_match_is_10000(): void
    {
        $score = Fuzzy::score('php', 'php');

        $this->assertSame(10000, $score);
    }

    public function test_score_prefix_is_higher_than_substring(): void
    {
        $prefixScore = Fuzzy::score('php', 'php-framework');
        $substringScore = Fuzzy::score('php', 'my-php-app');

        $this->assertGreaterThan($substringScore, $prefixScore);
    }

    public function test_score_empty_query_returns_zero(): void
    {
        $score = Fuzzy::score('', 'anything');

        $this->assertSame(0, $score);
    }

    public function test_score_is_case_insensitive(): void
    {
        $lower = Fuzzy::score('php', 'php');
        $upper = Fuzzy::score('PHP', 'PHP');

        // Both should be exact matches scoring 10000
        $this->assertSame($lower, $upper);
    }

    // ---------------------------------------------------------------
    // filter — preserves original casing
    // ---------------------------------------------------------------

    public function test_filter_preserves_original_item_casing(): void
    {
        $items = ['Laravel', 'Symfony', 'SlimFramework'];
        $results = Fuzzy::filter($items, 'slim');

        $this->assertContains('SlimFramework', $results);
    }
}
