<?php

declare(strict_types=1);

use App\Domain\League\Fixture;
use App\Domain\League\LeagueTable;
use App\Domain\League\MatchResult;
use App\Domain\Prediction\ChampionProbabilities;
use App\Domain\Prediction\DeterministicClincher;
use App\Domain\Prediction\MonteCarloPredictor;
use App\Domain\Random\SeededRandomSource;
use App\Domain\Ranking\PremierLeagueRanking;
use App\Domain\Scheduling\BergerRoundRobinScheduler;
use App\Domain\Simulation\PoissonGoalModel;
use App\Domain\Simulation\PoissonMatchSimulator;
use App\Domain\Simulation\SeasonSimulator;

function clincher(): DeterministicClincher
{
    return new DeterministicClincher(new LeagueTable);
}

function monteCarlo(int $seed, int $iterations): MonteCarloPredictor
{
    return new MonteCarloPredictor(
        new SeasonSimulator(new PoissonMatchSimulator(new PoissonGoalModel)),
        new LeagueTable,
        new PremierLeagueRanking(new LeagueTable),
        new SeededRandomSource($seed),
        $iterations,
    );
}

/** A title already won by 'a': nine points, with rivals unable to reach it. */
function decidedSeason(): array
{
    return [
        'teams' => [team('a'), team('b'), team('c'), team('d')],
        'played' => [
            new MatchResult('a', 'b', 1, 0),
            new MatchResult('a', 'c', 1, 0),
            new MatchResult('a', 'd', 1, 0),
        ],
        'remaining' => [
            new Fixture('b', 'c', 2),
            new Fixture('b', 'd', 2),
            new Fixture('c', 'd', 2),
        ],
    ];
}

it('rejects a probability outside the unit interval', function () {
    expect(fn () => new ChampionProbabilities(['a' => 1.4]))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new ChampionProbabilities(['a' => -0.01]))->toThrow(InvalidArgumentException::class);
});

it('reports zero for a team it has never heard of', function () {
    expect((new ChampionProbabilities(['a' => 1.0]))->for('ghost'))->toBe(0.0);
});

it('spreads the title uniformly across all contenders at kick-off', function () {
    $teams = [team('a'), team('b'), team('c'), team('d')];
    $remaining = (new BergerRoundRobinScheduler)->schedule($teams);

    $odds = clincher()->predict($teams, [], $remaining);

    expect($odds->toArray())->each->toBe(0.25);
});

it('collapses a clinched title to a certainty and eliminates the rest', function () {
    $season = decidedSeason();

    $odds = clincher()->predict($season['teams'], $season['played'], $season['remaining']);

    expect($odds->for('a'))->toBe(1.0)
        ->and($odds->for('b'))->toBe(0.0)
        ->and($odds->for('c'))->toBe(0.0)
        ->and($odds->for('d'))->toBe(0.0);
});

it('produces a Monte Carlo distribution that sums to one', function () {
    $teams = [team('a'), team('b'), team('c'), team('d')];
    $remaining = (new BergerRoundRobinScheduler)->schedule($teams);

    $odds = monteCarlo(seed: 3, iterations: 400)->predict($teams, [], $remaining);

    expect(array_sum($odds->toArray()))->toEqualWithDelta(1.0, 1e-9);
});

it('gives the same Monte Carlo distribution for the same seed', function () {
    $teams = [team('a'), team('b'), team('c'), team('d')];
    $remaining = (new BergerRoundRobinScheduler)->schedule($teams);

    $first = monteCarlo(seed: 5, iterations: 200)->predict($teams, [], $remaining);
    $second = monteCarlo(seed: 5, iterations: 200)->predict($teams, [], $remaining);

    expect($first->toArray())->toBe($second->toArray());
});

it('ranks the strongest team as the most likely champion', function () {
    $teams = [team('a', 90.0), team('b', 70.0), team('c', 50.0), team('d', 30.0)];
    $remaining = (new BergerRoundRobinScheduler)->schedule($teams);

    $odds = monteCarlo(seed: 17, iterations: 800)->predict($teams, [], $remaining);

    expect($odds->for('a'))->toBeGreaterThan($odds->for('d'));
});

it('agrees with the clincher once the title is mathematically settled', function () {
    $season = decidedSeason();

    $simulated = monteCarlo(seed: 1, iterations: 100)
        ->predict($season['teams'], $season['played'], $season['remaining']);
    $deterministic = clincher()
        ->predict($season['teams'], $season['played'], $season['remaining']);

    expect($simulated->for('a'))->toBe(1.0)
        ->and($deterministic->for('a'))->toBe(1.0);
});
