<?php

declare(strict_types=1);

namespace App\Domain\Evaluation;

use App\Domain\Prediction\ChampionProbabilities;

/**
 * Mean squared error of the forecast against the one-hot outcome, summed over teams: a perfect
 * 1.0-on-the-winner forecast scores 0, and confidence in the wrong team is punished quadratically.
 */
final readonly class BrierScore implements ScoringRule
{
    public function score(ChampionProbabilities $forecast, string $actualChampionId): float
    {
        $sum = 0.0;
        foreach ($forecast->toArray() as $teamId => $probability) {
            $outcome = $teamId === $actualChampionId ? 1.0 : 0.0;
            $sum += ($probability - $outcome) ** 2;
        }

        return $sum;
    }
}
