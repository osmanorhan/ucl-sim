<?php

declare(strict_types=1);

namespace App\Domain\Evaluation;

use App\Domain\Prediction\ChampionPredictor;
use App\Domain\Prediction\ChampionSamplerFactory;
use App\Domain\Random\SeededRandomSource;
use App\Domain\Support\Guard;
use InvalidArgumentException;

/**
 * Scores predictors against ground truth we own. Because this is a simulation, the "true"
 * champion distribution is just the reference model rolled to season end many times. Each
 * predictor's forecast is graded against those same realised outcomes with proper scoring rules —
 * so the verdict is paired (common random numbers cancel outcome noise in the differences) and
 * the predictor that reports the truest distribution wins, without ever being shown it.
 *
 * The forecast seed and the ground-truth seed are decorrelated, so a Monte Carlo predictor is
 * never graded against the very draws it sampled.
 */
final readonly class EvaluationHarness
{
    private const GROUND_TRUTH_OFFSET = 1_000_003;

    public function __construct(
        private ChampionSamplerFactory $reference,
        private ScoringRule $brier,
        private ScoringRule $logLoss,
    ) {}

    /**
     * @param  array<string, ChampionPredictor>  $predictors
     * @param  Scenario[]  $scenarios
     * @return StrategyScorecard[] sorted best (lowest Brier) first
     */
    public function compare(array $predictors, array $scenarios, int $groundTruthDraws): array
    {
        Guard::positive($groundTruthDraws, 'Ground truth draws');

        if ($predictors === []) {
            throw new InvalidArgumentException('At least one predictor is required to compare.');
        }

        if ($scenarios === []) {
            throw new InvalidArgumentException('At least one scenario is required to compare predictors.');
        }

        $truth = [];
        foreach ($scenarios as $scenario) {
            $truth[] = $this->groundTruth($scenario, $groundTruthDraws);
        }

        $scorecards = [];
        foreach ($predictors as $label => $predictor) {
            $scorecards[] = $this->scoreOne($label, $predictor, $scenarios, $truth);
        }

        usort($scorecards, static fn (StrategyScorecard $a, StrategyScorecard $b): int => $a->brier <=> $b->brier);

        return $scorecards;
    }

    /**
     * @param  Scenario[]  $scenarios
     * @param  array<int, array<string, int>>  $truth  per scenario, champion id => times realised
     */
    private function scoreOne(string $label, ChampionPredictor $predictor, array $scenarios, array $truth): StrategyScorecard
    {
        $brierTotal = 0.0;
        $logLossTotal = 0.0;
        $samples = 0;
        $latencyNs = 0;

        foreach ($scenarios as $i => $scenario) {
            $start = hrtime(true);
            $forecast = $predictor->predict(
                $scenario->teams, $scenario->played, $scenario->remaining,
                new SeededRandomSource($scenario->seed),
            );
            $latencyNs += hrtime(true) - $start;

            foreach ($truth[$i] as $champion => $count) {
                $brierTotal += $count * $this->brier->score($forecast, $champion);
                $logLossTotal += $count * $this->logLoss->score($forecast, $champion);
                $samples += $count;
            }
        }

        return new StrategyScorecard(
            $label,
            $brierTotal / $samples,
            $logLossTotal / $samples,
            ($latencyNs / count($scenarios)) / 1e6,
            $this->isDeterministic($predictor, $scenarios[0]),
        );
    }

    /**
     * The realised champions collapse to a frequency table: the score of a forecast against an
     * outcome depends only on which team won, not which draw it was, so a predictor is graded once
     * per distinct champion and weighted by its count rather than re-scored on every identical draw.
     *
     * @return array<string, int> champion id => times realised across the draws
     */
    private function groundTruth(Scenario $scenario, int $draws): array
    {
        $sampler = $this->reference->compile($scenario->teams, $scenario->played, $scenario->remaining);
        $random = new SeededRandomSource($scenario->seed + self::GROUND_TRUTH_OFFSET);

        $counts = [];
        for ($i = 0; $i < $draws; $i++) {
            $champion = $sampler->draw($random);
            $counts[$champion] = ($counts[$champion] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * Reproducibility is an algorithmic property — a seeded predictor is deterministic regardless
     * of scenario — so it is spot-checked on one scenario rather than re-running every scenario
     * twice, which would double the benchmark's cost to re-verify the same fact.
     */
    private function isDeterministic(ChampionPredictor $predictor, Scenario $scenario): bool
    {
        $first = $predictor->predict($scenario->teams, $scenario->played, $scenario->remaining, new SeededRandomSource($scenario->seed));
        $second = $predictor->predict($scenario->teams, $scenario->played, $scenario->remaining, new SeededRandomSource($scenario->seed));

        return $first->toArray() === $second->toArray();
    }
}
