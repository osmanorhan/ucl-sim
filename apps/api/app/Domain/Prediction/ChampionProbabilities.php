<?php

declare(strict_types=1);

namespace App\Domain\Prediction;

use InvalidArgumentException;

final readonly class ChampionProbabilities
{
    /** @param array<string, float> $byTeam */
    public function __construct(private array $byTeam)
    {
        foreach ($byTeam as $teamId => $probability) {
            if ($probability < 0.0 || $probability > 1.0) {
                throw new InvalidArgumentException("Probability for {$teamId} must lie in [0, 1].");
            }
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
