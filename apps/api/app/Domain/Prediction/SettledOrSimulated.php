<?php

declare(strict_types=1);

namespace App\Domain\Prediction;

use App\Domain\Random\RandomSource;

/**
 * The live predictor: exact when it can be, simulated when it must be. If the title is already
 * mathematically decided the clincher returns a certainty (some team at 1.0) and we trust it —
 * no point sampling a settled question. Otherwise we fall through to Monte Carlo for the real
 * distribution. Composition, not a conditional inside either strategy: each stays single-purpose
 * and this decorator owns the "exact beats sampled when available" policy (ADR-06).
 */
final readonly class SettledOrSimulated implements ChampionPredictor
{
    public function __construct(
        private DeterministicClincher $clincher,
        private MonteCarloPredictor $simulator,
    ) {}

    public function predict(array $teams, array $played, array $remaining, RandomSource $random): ChampionProbabilities
    {
        $clinched = $this->clincher->predict($teams, $played, $remaining, $random);
        $odds = array_column($clinched->entries(), 'probability');

        if ($odds !== [] && max($odds) === 1.0) {
            return $clinched;
        }

        return $this->simulator->predict($teams, $played, $remaining, $random);
    }
}
