<?php

declare(strict_types=1);

namespace App\Application;

use App\Domain\Evaluation\EvaluationHarness;
use App\Domain\Evaluation\Scenario;
use App\Domain\Evaluation\StrategyScorecard;
use App\Domain\League\LeagueState;
use App\Infrastructure\Registry\StrategyRegistry;

/**
 * Turns a league's current position into a strategy bake-off: it spreads the state across a fan of
 * decorrelated seeds and hands the registry's field to the {@see EvaluationHarness}. This is the
 * explicit "which strategy is better / safer / faster" call — deliberately the heavy analysis path,
 * not the hot read path that ADR-01 keeps O(1).
 */
final readonly class StrategyEvaluator
{
    private const SCENARIO_STRIDE = 31;

    public function __construct(
        private EvaluationHarness $harness,
        private StrategyRegistry $registry,
    ) {}

    /** @return array<int, array<string, mixed>> sorted best (lowest Brier) first */
    public function evaluate(LeagueState $state, int $scenarios, int $groundTruthDraws): array
    {
        $built = [];
        for ($i = 0; $i < $scenarios; $i++) {
            $built[] = new Scenario($state->teams, $state->played(), $state->remaining(), $state->seed + $i * self::SCENARIO_STRIDE);
        }

        return array_values(array_map(
            static fn (StrategyScorecard $card): array => [
                'strategy' => $card->label,
                'brier' => $card->brier,
                'logLoss' => $card->logLoss,
                'meanLatencyMs' => $card->meanLatencyMs,
                'deterministic' => $card->deterministic,
            ],
            $this->harness->compare($this->registry->all(), $built, $groundTruthDraws),
        ));
    }
}
