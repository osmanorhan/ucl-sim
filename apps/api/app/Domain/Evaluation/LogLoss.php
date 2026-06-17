<?php

declare(strict_types=1);

namespace App\Domain\Evaluation;

use App\Domain\Prediction\ChampionProbabilities;

/**
 * Negative log-likelihood of the team that actually won. Sharper than Brier on overconfidence:
 * a forecast that assigns the winner near-zero probability is penalised steeply. A zero would be
 * an infinite penalty, so the probability is floored at EPSILON — a finite, very large loss is
 * the honest reading of "ruled out something that then happened", not a swallowed error.
 */
final readonly class LogLoss implements ScoringRule
{
    private const EPSILON = 1e-15;

    public function score(ChampionProbabilities $forecast, string $actualChampionId): float
    {
        return -log(max($forecast->for($actualChampionId), self::EPSILON));
    }
}
