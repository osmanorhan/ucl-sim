<?php

declare(strict_types=1);

use App\Domain\Scheduling\BergerRoundRobinScheduler;

function pairKey(string $a, string $b): string
{
    return implode('-', [min($a, $b), max($a, $b)]);
}

function fixturesFor(int $teamCount): array
{
    $teams = array_map(static fn (int $i) => team(sprintf('t%02d', $i)), range(1, $teamCount));

    return (new BergerRoundRobinScheduler)->schedule($teams);
}

dataset('even team counts', [2, 4, 8, 32]);

it('produces every team pair exactly once home and once away', function (int $n) {
    $fixtures = fixturesFor($n);

    expect($fixtures)->toHaveCount($n * ($n - 1));

    $ordered = array_map(static fn ($f) => "{$f->homeTeamId}>{$f->awayTeamId}", $fixtures);
    $unordered = array_map(static fn ($f) => pairKey($f->homeTeamId, $f->awayTeamId), $fixtures);

    expect(array_unique($ordered))->toHaveCount($n * ($n - 1))
        ->and(array_count_values($unordered))->each->toBe(2);
})->with('even team counts');

it('spans exactly 2*(n-1) weeks with every team playing once per week', function (int $n) {
    $fixtures = fixturesFor($n);
    $weeks = 2 * ($n - 1);

    expect(max(array_map(static fn ($f) => $f->week, $fixtures)))->toBe($weeks);

    $byWeek = [];
    foreach ($fixtures as $fixture) {
        $byWeek[$fixture->week][] = $fixture->homeTeamId;
        $byWeek[$fixture->week][] = $fixture->awayTeamId;
    }

    foreach ($byWeek as $playing) {
        expect($playing)->toHaveCount($n)
            ->and(array_unique($playing))->toHaveCount($n);
    }

    expect($byWeek)->toHaveCount($weeks);
})->with('even team counts');

it('rejects an odd number of teams', function () {
    fixturesFor(3);
})->throws(InvalidArgumentException::class);

it('rejects fewer than two teams', function () {
    (new BergerRoundRobinScheduler)->schedule([]);
})->throws(InvalidArgumentException::class);
