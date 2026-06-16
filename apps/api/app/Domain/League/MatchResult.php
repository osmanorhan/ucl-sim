<?php

declare(strict_types=1);

namespace App\Domain\League;

use App\Domain\Support\Guard;

final readonly class MatchResult
{
    public function __construct(
        public string $homeTeamId,
        public string $awayTeamId,
        public int $homeGoals,
        public int $awayGoals,
    ) {
        Guard::distinct($homeTeamId, $awayTeamId, 'A match cannot be between a team and itself.');
        Guard::nonNegative($homeGoals, 'Home goals');
        Guard::nonNegative($awayGoals, 'Away goals');
    }

    public function isDraw(): bool
    {
        return $this->homeGoals === $this->awayGoals;
    }

    public function winnerId(): ?string
    {
        return match (true) {
            $this->homeGoals > $this->awayGoals => $this->homeTeamId,
            $this->awayGoals > $this->homeGoals => $this->awayTeamId,
            default => null,
        };
    }

    public function involves(string $teamId): bool
    {
        return $teamId === $this->homeTeamId || $teamId === $this->awayTeamId;
    }

    public function outcomeFor(string $teamId): MatchOutcome
    {
        $scored = $teamId === $this->homeTeamId ? $this->homeGoals : $this->awayGoals;
        $conceded = $teamId === $this->homeTeamId ? $this->awayGoals : $this->homeGoals;

        return match (true) {
            $scored > $conceded => MatchOutcome::Win,
            $scored < $conceded => MatchOutcome::Loss,
            default => MatchOutcome::Draw,
        };
    }
}
