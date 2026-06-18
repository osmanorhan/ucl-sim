<?php

declare(strict_types=1);

use App\Domain\Evaluation\BrierScore;
use App\Domain\Evaluation\EvaluationHarness;
use App\Domain\Evaluation\LogLoss;
use App\Domain\Evaluation\Scenario;
use App\Domain\Evaluation\StrategyScorecard;
use App\Domain\League\Fixture;
use App\Domain\League\LeagueTable;
use App\Domain\League\MatchResult;
use App\Domain\Prediction\ChampionProbabilities;
use App\Domain\Prediction\PointsHeuristicPredictor;
use App\Domain\Scheduling\BergerRoundRobinScheduler;

function harness(): EvaluationHarness
{
    return new EvaluationHarness(samplerFactory(), new BrierScore, new LogLoss);
}

function heuristic(): PointsHeuristicPredictor
{
    return new PointsHeuristicPredictor(new LeagueTable);
}

/** A clear favourite field, every fixture still to play. */
function openFieldScenario(int $seed): Scenario
{
    $teams = [team('a', 90.0), team('b', 65.0), team('c', 45.0), team('d', 30.0)];

    return new Scenario($teams, [], (new BergerRoundRobinScheduler)->schedule($teams), $seed);
}

/** 'a' has nine points with rivals unable to reach it — the title is mathematically settled. */
function settledScenario(int $seed): Scenario
{
    $teams = [team('a'), team('b'), team('c'), team('d')];
    $played = [new MatchResult('a', 'b', 1, 0), new MatchResult('a', 'c', 1, 0), new MatchResult('a', 'd', 1, 0)];
    $remaining = [new Fixture('b', 'c', 2), new Fixture('b', 'd', 2), new Fixture('c', 'd', 2)];

    return new Scenario($teams, $played, $remaining, $seed);
}

/** @return array<string, StrategyScorecard> */
function byLabel(array $cards): array
{
    $byLabel = [];
    foreach ($cards as $card) {
        $byLabel[$card->label] = $card;
    }

    return $byLabel;
}

it('scores Brier as squared error against the one-hot outcome', function () {
    $forecast = new ChampionProbabilities(['a' => 0.7, 'b' => 0.3]);
    $brier = new BrierScore;

    expect($brier->score($forecast, 'a'))->toEqualWithDelta(0.18, 1e-9)
        ->and($brier->score($forecast, 'b'))->toEqualWithDelta(0.98, 1e-9);
});

it('scores numeric string champion identifiers as strings', function () {
    $forecast = new ChampionProbabilities(['1' => 0.7, '2' => 0.3]);

    expect((new BrierScore)->score($forecast, '1'))->toEqualWithDelta(0.18, 1e-9);
});

it('scores log loss as the negative log of the realised champion probability', function () {
    $forecast = new ChampionProbabilities(['a' => 0.25, 'b' => 0.75]);

    expect((new LogLoss)->score($forecast, 'a'))->toEqualWithDelta(-log(0.25), 1e-9)
        ->and((new LogLoss)->score($forecast, 'b'))->toEqualWithDelta(-log(0.75), 1e-9);
});

it('penalises a zero-probability outcome with a large but finite log loss', function () {
    $forecast = new ChampionProbabilities(['a' => 1.0, 'b' => 0.0]);
    $loss = (new LogLoss)->score($forecast, 'b');

    expect($loss)->toBeGreaterThan(30.0)
        ->and(is_finite($loss))->toBeTrue();
});

it('rejects a scorecard carrying a negative metric', function () {
    expect(fn () => new StrategyScorecard('x', -0.1, 0.0, 0.0, true))->toThrow(InvalidArgumentException::class);
});

it('requires predictors, at least one scenario, and a positive number of ground-truth draws', function () {
    expect(fn () => harness()->compare([], [openFieldScenario(1)], 100))->toThrow(InvalidArgumentException::class)
        ->and(fn () => harness()->compare(['mc' => monteCarlo(50)], [], 100))->toThrow(InvalidArgumentException::class)
        ->and(fn () => harness()->compare(['mc' => monteCarlo(50)], [openFieldScenario(1)], 0))->toThrow(InvalidArgumentException::class);
});

it('scores a real simulation above the points heuristic on Brier', function () {
    $cards = byLabel(harness()->compare([
        'monte-carlo' => monteCarlo(iterations: 400),
        'points-heuristic' => heuristic(),
    ], [openFieldScenario(7)], groundTruthDraws: 400));

    expect($cards['monte-carlo']->brier)->toBeLessThan($cards['points-heuristic']->brier)
        ->and($cards['monte-carlo']->logLoss)->toBeLessThan($cards['points-heuristic']->logLoss);
});

it('flags both a sampled and a deterministic predictor as reproducible under a fixed seed', function () {
    $cards = byLabel(harness()->compare([
        'monte-carlo' => monteCarlo(iterations: 200),
        'clincher' => clincher(),
    ], [openFieldScenario(3)], groundTruthDraws: 200));

    expect($cards['monte-carlo']->deterministic)->toBeTrue()
        ->and($cards['clincher']->deterministic)->toBeTrue();
});

it('benchmarks leagues with numeric string team identifiers', function () {
    $teams = [team('1', 90.0), team('2', 65.0), team('3', 45.0), team('4', 30.0)];
    $scenario = new Scenario($teams, [], (new BergerRoundRobinScheduler)->schedule($teams), 11);

    $cards = harness()->compare([
        'monte-carlo' => monteCarlo(iterations: 100),
        'clincher' => clincher(),
    ], [$scenario], groundTruthDraws: 100);

    expect($cards)->toHaveCount(2);
});

it('rewards the clincher with a zero Brier once the title is settled, and ranks it first', function () {
    $cards = harness()->compare([
        'points-heuristic' => heuristic(),
        'clincher' => clincher(),
    ], [settledScenario(9)], groundTruthDraws: 200);

    $clincher = byLabel($cards)['clincher'];

    expect($clincher->brier)->toBe(0.0)
        ->and($clincher->logLoss)->toBe(0.0)
        ->and($cards[0]->label)->toBe('clincher')
        ->and(byLabel($cards)['points-heuristic']->brier)->toBeGreaterThan(0.0);
});

it('measures a positive latency for the Monte Carlo predictor', function () {
    $cards = byLabel(harness()->compare([
        'monte-carlo' => monteCarlo(iterations: 400),
    ], [openFieldScenario(2)], groundTruthDraws: 100));

    expect($cards['monte-carlo']->meanLatencyMs)->toBeGreaterThan(0.0);
});
