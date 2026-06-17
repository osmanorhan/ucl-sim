<?php

declare(strict_types=1);

namespace App\Domain\Prediction;

use InvalidArgumentException;

final readonly class ChampionProbabilities
{
    private const SUM_TOLERANCE = 1e-9;

    /** @param array<string, float> $byTeam */
    public function __construct(private array $byTeam)
    {
        foreach ($byTeam as $teamId => $probability) {
            if ($probability < 0.0 || $probability > 1.0) {
                throw new InvalidArgumentException("Probability for {$teamId} must lie in [0, 1].");
            }
        }

        $total = array_sum($byTeam);
        if (abs($total - 1.0) > self::SUM_TOLERANCE) {
            throw new InvalidArgumentException("Champion probabilities must form a distribution summing to 1.0; got {$total}.");
        }
    }

    public function for(string $teamId): float
    {
        return $this->byTeam[$teamId] ?? 0.0;
    }

    /** @return array<string, float> */
    public function toArray(): array
    {
        return $this->byTeam;
    }
}
