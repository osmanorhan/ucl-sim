<?php

declare(strict_types=1);

namespace App\Domain\Prediction;

use App\Domain\Team\Team;
use InvalidArgumentException;

final readonly class ChampionProbabilities
{
    private const SUM_TOLERANCE = 1e-9;

    private array $byTeam;

    private array $entries;

    /** @param array<string, float> $byTeam */
    public function __construct(array $byTeam)
    {
        $entries = [];

        foreach ($byTeam as $teamId => $probability) {
            if ($probability < 0.0 || $probability > 1.0) {
                throw new InvalidArgumentException("Probability for {$teamId} must lie in [0, 1].");
            }

            $entries[] = ['teamId' => (string) $teamId, 'probability' => $probability];
        }

        $this->byTeam = $byTeam;
        $this->entries = $entries;

        $total = array_sum($byTeam);
        if (abs($total - 1.0) > self::SUM_TOLERANCE) {
            throw new InvalidArgumentException("Champion probabilities must form a distribution summing to 1.0; got {$total}.");
        }
    }

    /**
     * @param  Team[]  $teams
     * @param  callable(Team): float  $probability
     */
    public static function fromTeams(array $teams, callable $probability): self
    {
        $byTeam = [];
        foreach ($teams as $team) {
            $byTeam[$team->id] = $probability($team);
        }

        return new self($byTeam);
    }

    public function for(string $teamId): float
    {
        return $this->byTeam[$teamId] ?? 0.0;
    }

    /** @return array<int, array{teamId: string, probability: float}> */
    public function entries(): array
    {
        return $this->entries;
    }

    /** @return array<string, float> */
    public function toArray(): array
    {
        return $this->byTeam;
    }
}
