<?php

declare(strict_types=1);

use App\Domain\League\Fixture;
use App\Domain\League\LeagueTable;
use App\Domain\League\MatchResult;
use App\Domain\Prediction\ChampionProbabilities;
use App\Domain\Prediction\ChampionSamplerFactory;
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

function samplerFactory(): ChampionSamplerFactory
{
    return new ChampionSamplerFactory(
        new SeasonSimulator(new PoissonMatchSimulator(new PoissonGoalModel)),
        new LeagueTable,
        new PremierLeagueRanking(new LeagueTable),
    );
}

function monteCarlo(int $iterations): MonteCarloPredictor
{
    return new MonteCarloPredictor(samplerFactory(), $iterations);
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

it('rejects probabilities that do not form a distribution summing to one', function () {
    expect(fn () => new ChampionProbabilities(['a' => 0.6, 'b' => 0.6]))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new ChampionProbabilities(['a' => 0.2, 'b' => 0.2]))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new ChampionProbabilities([]))->toThrow(InvalidArgumentException::class);
});

it('reports zero for a team it has never heard of', function () {
    expect((new ChampionProbabilities(['a' => 1.0]))->for('ghost'))->toBe(0.0);
});

it('exposes numeric string team identifiers without relying on array keys', function () {
    $odds = new ChampionProbabilities(['1' => 0.6, '2' => 0.4]);

    expect($odds->entries())->toBe([
        ['teamId' => '1', 'probability' => 0.6],
        ['teamId' => '2', 'probability' => 0.4],
    ]);
});

it('spreads the title uniformly across all contenders at kick-off', function () {
    $teams = [team('a'), team('b'), team('c'), team('d')];
    $remaining = (new BergerRoundRobinScheduler)->schedule($teams);

    $odds = clincher()->predict($teams, [], $remaining, new SeededRandomSource(1));

    expect($odds->toArray())->each->toBe(0.25);
});

it('collapses a clinched title to a certainty and eliminates the rest', function () {
    $season = decidedSeason();

    $odds = clincher()->predict($season['teams'], $season['played'], $season['remaining'], new SeededRandomSource(1));

    expect($odds->for('a'))->toBe(1.0)
        ->and($odds->for('b'))->toBe(0.0)
        ->and($odds->for('c'))->toBe(0.0)
        ->and($odds->for('d'))->toBe(0.0);
});

it('handles numeric string team identifiers', function () {
    $teams = [team('1'), team('2'), team('3'), team('4')];
    $remaining = (new BergerRoundRobinScheduler)->schedule($teams);

    $odds = clincher()->predict($teams, [], $remaining, new SeededRandomSource(1));

    expect($odds->for('1'))->toBe(0.25)
        ->and($odds->for('4'))->toBe(0.25);
});

it('produces a Monte Carlo distribution that sums to one', function () {
    $teams = [team('a'), team('b'), team('c'), team('d')];
    $remaining = (new BergerRoundRobinScheduler)->schedule($teams);

    $odds = monteCarlo(iterations: 400)->predict($teams, [], $remaining, new SeededRandomSource(3));

    expect(array_sum($odds->toArray()))->toEqualWithDelta(1.0, 1e-9);
});

it('gives the same Monte Carlo distribution for the same seed', function () {
    $teams = [team('a'), team('b'), team('c'), team('d')];
    $remaining = (new BergerRoundRobinScheduler)->schedule($teams);

    $first = monteCarlo(iterations: 200)->predict($teams, [], $remaining, new SeededRandomSource(5));
    $second = monteCarlo(iterations: 200)->predict($teams, [], $remaining, new SeededRandomSource(5));

    expect($first->toArray())->toBe($second->toArray());
});

it('ranks the strongest team as the most likely champion', function () {
    $teams = [team('a', 90.0), team('b', 70.0), team('c', 50.0), team('d', 30.0)];
    $remaining = (new BergerRoundRobinScheduler)->schedule($teams);

    $odds = monteCarlo(iterations: 800)->predict($teams, [], $remaining, new SeededRandomSource(17));

    expect($odds->for('a'))->toBeGreaterThan($odds->for('d'));
});

it('agrees with the clincher once the title is mathematically settled', function () {
    $season = decidedSeason();

    $simulated = monteCarlo(iterations: 100)
        ->predict($season['teams'], $season['played'], $season['remaining'], new SeededRandomSource(1));
    $deterministic = clincher()
        ->predict($season['teams'], $season['played'], $season['remaining'], new SeededRandomSource(1));

    expect($simulated->for('a'))->toBe(1.0)
        ->and($deterministic->for('a'))->toBe(1.0);
});
