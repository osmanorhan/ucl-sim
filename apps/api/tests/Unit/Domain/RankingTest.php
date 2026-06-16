<?php

declare(strict_types=1);

use App\Domain\League\LeagueTable;
use App\Domain\League\MatchOutcome;
use App\Domain\League\MatchResult;
use App\Domain\League\Standing;
use App\Domain\Ranking\CompositeComparator;
use App\Domain\Ranking\Direction;
use App\Domain\Ranking\MetricComparator;
use App\Domain\Ranking\PremierLeagueRanking;

function standing(string $id, int $won, int $drawn, int $lost, int $goalsFor, int $goalsAgainst): Standing
{
    $points = $won * outcomePoints(MatchOutcome::Win) + $drawn * outcomePoints(MatchOutcome::Draw);

    return new Standing(team($id), $won + $drawn + $lost, $won, $drawn, $lost, $goalsFor, $goalsAgainst, $points);
}

function premierLeague(): PremierLeagueRanking
{
    return new PremierLeagueRanking(new LeagueTable);
}

function rankedIds(array $standings, array $results): array
{
    return array_map(static fn (Standing $s): string => $s->team->id, premierLeague()->rank($standings, $results));
}

it('ranks an empty league to an empty table', function () {
    expect(premierLeague()->rank([], []))->toBe([]);
});

it('ranks a single team to itself', function () {
    expect(rankedIds([standing('solo', 0, 0, 0, 0, 0)], []))->toBe(['solo']);
});

it('orders a single metric ascending and descending', function () {
    $weak = standing('weak', 0, 0, 2, 1, 5);
    $strong = standing('strong', 2, 0, 0, 5, 1);

    $descending = new MetricComparator(static fn (Standing $s): int => $s->goalDifference(), Direction::Descending);
    $ascending = new MetricComparator(static fn (Standing $s): int => $s->goalDifference(), Direction::Ascending);

    expect($descending->compare($strong, $weak))->toBeLessThan(0)
        ->and($ascending->compare($strong, $weak))->toBeGreaterThan(0);
});

it('directs raw values either way', function () {
    expect(Direction::Descending->order(1, 2))->toBeGreaterThan(0)
        ->and(Direction::Ascending->order(1, 2))->toBeLessThan(0)
        ->and(Direction::Descending->order(2, 2))->toBe(0);
});

it('returns the first discriminating comparison and zero when all tie', function () {
    $chain = new CompositeComparator(
        new MetricComparator(static fn (Standing $s): int => $s->points, Direction::Descending),
        new MetricComparator(static fn (Standing $s): int => $s->goalsFor, Direction::Descending),
    );

    $base = standing('a', 1, 0, 0, 2, 0);
    $sameEverything = standing('b', 1, 0, 0, 2, 0);
    $morePointsLessGoals = standing('c', 2, 0, 0, 1, 0);

    expect($chain->compare($base, $sameEverything))->toBe(0)
        ->and($chain->compare($morePointsLessGoals, $base))->toBeLessThan(0);
});

it('ranks by points, then goal difference, then goals scored', function () {
    $standings = [
        standing('gd', 1, 1, 1, 5, 3),     // 4 pts, GD +2, GF 5
        standing('top', 2, 0, 1, 6, 2),    // 6 pts
        standing('goals', 1, 1, 1, 7, 5),  // 4 pts, GD +2, GF 7
    ];

    expect(rankedIds($standings, []))->toBe(['top', 'goals', 'gd']);
});

it('breaks a three-way ordinal tie with a head-to-head mini-league', function () {
    $standings = [
        standing('c', 2, 1, 1, 5, 5),
        standing('a', 2, 1, 1, 5, 5),
        standing('b', 2, 1, 1, 5, 5),
    ];

    // Mini-league among the tied teams: a beats b and c, b beats c → a, b, c.
    $results = [
        new MatchResult('a', 'b', 1, 0),
        new MatchResult('a', 'c', 1, 0),
        new MatchResult('b', 'c', 1, 0),
    ];

    expect(rankedIds($standings, $results))->toBe(['a', 'b', 'c']);
});

it('keeps a non-separable cyclic tie in stable order without breaking the sort', function () {
    $standings = [
        standing('a', 1, 1, 1, 3, 3),
        standing('b', 1, 1, 1, 3, 3),
        standing('c', 1, 1, 1, 3, 3),
    ];

    // A cycle: a>b>c>a. Pairwise comparison is non-transitive; the mini-league is a dead heat.
    $results = [
        new MatchResult('a', 'b', 1, 0),
        new MatchResult('b', 'c', 1, 0),
        new MatchResult('c', 'a', 1, 0),
    ];

    expect(rankedIds($standings, $results))->toBe(['a', 'b', 'c']);
});
