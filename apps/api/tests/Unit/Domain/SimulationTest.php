<?php

declare(strict_types=1);

use App\Domain\League\MatchResult;
use App\Domain\Random\PoissonDistribution;
use App\Domain\Random\SeededRandomSource;
use App\Domain\Scheduling\BergerRoundRobinScheduler;
use App\Domain\Simulation\GoalExpectation;
use App\Domain\Simulation\PoissonGoalModel;
use App\Domain\Simulation\PoissonMatchSimulator;
use App\Domain\Simulation\SeasonSimulator;

function simulator(): PoissonMatchSimulator
{
    return new PoissonMatchSimulator(new PoissonGoalModel);
}

it('rejects a non-positive Poisson lambda and goal expectation', function () {
    expect(fn () => new PoissonDistribution(0.0))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new GoalExpectation(1.0, -0.1))->toThrow(InvalidArgumentException::class);
});

it('samples a Poisson mean and variance that approach lambda', function () {
    $lambda = 2.3;
    $distribution = new PoissonDistribution($lambda);
    $source = new SeededRandomSource(99);

    $samples = array_map(static fn (): int => $distribution->sample($source), range(1, 50_000));
    $mean = array_sum($samples) / count($samples);
    $variance = array_sum(array_map(static fn (int $k): float => ($k - $mean) ** 2, $samples)) / count($samples);

    expect($mean)->toEqualWithDelta($lambda, 0.05)
        ->and($variance)->toEqualWithDelta($lambda, 0.1);
});

it('scales expected goals by the power ratio with a home lift', function () {
    $model = new PoissonGoalModel(baseGoals: 1.35, homeAdvantage: 1.25);

    $even = $model->expected(team('h', 50.0), team('a', 50.0));
    expect($even->home)->toEqualWithDelta(1.35 * 1.25, 1e-9)
        ->and($even->away)->toEqualWithDelta(1.35, 1e-9);

    $mismatch = $model->expected(team('h', 80.0), team('a', 40.0));
    expect($mismatch->home)->toEqualWithDelta(1.35 * 2 * 1.25, 1e-9)
        ->and($mismatch->away)->toEqualWithDelta(1.35 / 2, 1e-9);
});

it('simulates the same match identically for the same seed', function () {
    $home = team('h', 60.0);
    $away = team('a', 40.0);

    $first = simulator()->prepare($home, $away)->sample(new SeededRandomSource(7));
    $second = simulator()->prepare($home, $away)->sample(new SeededRandomSource(7));

    expect([$first->homeGoals, $first->awayGoals])->toBe([$second->homeGoals, $second->awayGoals]);
});

it('lets the stronger side outscore the weaker over many matches', function () {
    $match = simulator()->prepare(team('strong', 85.0), team('weak', 35.0));
    $source = new SeededRandomSource(2024);

    $homeGoals = 0;
    $awayGoals = 0;
    foreach (range(1, 5_000) as $_) {
        $result = $match->sample($source);
        $homeGoals += $result->homeGoals;
        $awayGoals += $result->awayGoals;
    }

    expect($homeGoals)->toBeGreaterThan($awayGoals);
});

it('plays every fixture of a season once and stays deterministic', function () {
    $teams = [team('a'), team('b'), team('c'), team('d')];
    $fixtures = (new BergerRoundRobinScheduler)->schedule($teams);
    $season = new SeasonSimulator(simulator());

    $results = $season->play($teams, $fixtures, new SeededRandomSource(11));

    expect($results)->toHaveCount(count($fixtures))
        ->and($results[0])->toBeInstanceOf(MatchResult::class);

    $replay = $season->play($teams, $fixtures, new SeededRandomSource(11));
    $scoreline = static fn (MatchResult $r): array => [$r->homeTeamId, $r->awayTeamId, $r->homeGoals, $r->awayGoals];
    expect(array_map($scoreline, $results))->toBe(array_map($scoreline, $replay));
});

it('rejects a fixture referencing an unknown team', function () {
    $fixtures = (new BergerRoundRobinScheduler)->schedule([team('a'), team('b')]);
    $season = new SeasonSimulator(simulator());

    $season->play([team('a')], $fixtures, new SeededRandomSource(1));
})->throws(InvalidArgumentException::class);
