<?php

declare(strict_types=1);

namespace App\Domain\Evaluation;

use App\Domain\Prediction\ChampionProbabilities;

/**
 * Scores a forecast against the single outcome that actually happened. Implementations are
 * *proper* scoring rules: expected score is minimised by reporting the true distribution, so a
 * predictor cannot game them by being over- or under-confident. The metric is itself a strategy.
 */
interface ScoringRule
{
    public function score(ChampionProbabilities $forecast, string $actualChampionId): float;
}
