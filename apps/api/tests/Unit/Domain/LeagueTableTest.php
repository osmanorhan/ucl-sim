<?php

declare(strict_types=1);

use App\Domain\League\Fixture;
use App\Domain\League\LeagueTable;
use App\Domain\League\MatchResult;
use App\Domain\Ranking\PremierLeagueRanking;
use App\Domain\Scheduling\BergerRoundRobinScheduler;

function project(array $teams, array $results): array
{
    $table = new LeagueTable;
    $standings = $table->project($teams, $results);

    return (new PremierLeagueRanking($table))->rank($standings, $results);
}

it('folds results into an ordered standings table', function () {
    $teams = [team('a'), team('b'), team('c'), team('d')];
    $results = [
        new MatchResult('a', 'b', 3, 1),
        new MatchResult('a', 'c', 2, 2),
        new MatchResult('a', 'd', 1, 0),
        new MatchResult('b', 'c', 0, 0),
        new MatchResult('b', 'd', 2, 1),
        new MatchResult('c', 'd', 4, 0),
    ];

    $table = project($teams, $results);
    $order = array_map(static fn ($standing) => $standing->team->id, $table);

    expect($order)->toBe(['a', 'c', 'b', 'd']);

    $leader = $table[0];
    expect($leader->points)->toBe(7)
        ->and($leader->goalDifference())->toBe(3)
        ->and($leader->played)->toBe(3);
});

it('preserves the points invariant: 3*wins + 1*draw across the table', function () {
    $teams = [team('a'), team('b'), team('c'), team('d')];
    $results = [
        new MatchResult('a', 'b', 3, 1),
        new MatchResult('c', 'd', 0, 0),
        new MatchResult('a', 'c', 1, 2),
        new MatchResult('b', 'd', 2, 2),
    ];

    $table = project($teams, $results);

    $decisive = count(array_filter($results, static fn ($r) => ! $r->isDraw()));
    $draws = count(array_filter($results, static fn ($r) => $r->isDraw()));
    $totalPoints = array_sum(array_map(static fn ($s) => $s->points, $table));

    expect($totalPoints)->toBe($decisive * 3 + $draws * 2);
});

it('rejects results referencing unknown teams', function () {
    project([team('a'), team('b')], [new MatchResult('a', 'z', 1, 0)]);
})->throws(InvalidArgumentException::class);

it('lists every team with a zero record when no matches are played', function () {
    $table = project([team('a'), team('b'), team('c'), team('d')], []);

    expect($table)->toHaveCount(4)
        ->and(array_map(static fn ($s) => $s->points, $table))->toBe([0, 0, 0, 0])
        ->and(array_map(static fn ($s) => $s->played, $table))->toBe([0, 0, 0, 0]);
});

it('leaves every team level when every match is drawn', function () {
    $teams = [team('a'), team('b'), team('c'), team('d')];
    $results = [
        new MatchResult('a', 'b', 1, 1),
        new MatchResult('c', 'd', 0, 0),
        new MatchResult('a', 'c', 2, 2),
        new MatchResult('b', 'd', 3, 3),
    ];

    $points = array_unique(array_map(static fn ($s) => $s->points, project($teams, $results)));

    expect($points)->toHaveCount(1);
});

it('projects and ranks a full 32-team season on the linear paths', function () {
    $teams = array_map(static fn (int $i) => team(sprintf('t%02d', $i), (float) $i), range(1, 32));
    $fixtures = (new BergerRoundRobinScheduler)->schedule($teams);

    // Deterministic ground truth: the higher-numbered (stronger) team always wins 1-0.
    $results = array_map(static function (Fixture $f): MatchResult {
        $homeWins = $f->homeTeamId > $f->awayTeamId;

        return new MatchResult($f->homeTeamId, $f->awayTeamId, (int) $homeWins, (int) ! $homeWins);
    }, $fixtures);

    $ranked = project($teams, $results);

    expect($ranked)->toHaveCount(32);
    expect($ranked[0]->team->id)->toBe('t32')
        ->and($ranked[31]->team->id)->toBe('t01');
    // t_k beats the (k-1) weaker teams home and away → 6*(k-1) points.
    expect($ranked[0]->points)->toBe(6 * 31)
        ->and($ranked[31]->points)->toBe(0)
        ->and($ranked[0]->played)->toBe(62);

    $goalsFor = array_sum(array_map(static fn ($s) => $s->goalsFor, $ranked));
    $goalsAgainst = array_sum(array_map(static fn ($s) => $s->goalsAgainst, $ranked));
    expect($goalsFor)->toBe($goalsAgainst);
});
