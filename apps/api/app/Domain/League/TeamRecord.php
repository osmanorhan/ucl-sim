<?php

declare(strict_types=1);

namespace App\Domain\League;

use App\Domain\Team\Team;

final class TeamRecord
{
    private int $won = 0;

    private int $drawn = 0;

    private int $lost = 0;

    private int $goalsFor = 0;

    private int $goalsAgainst = 0;

    private int $points = 0;

    public function __construct(private readonly Team $team) {}

    public function record(MatchOutcome $outcome, int $scored, int $conceded): void
    {
        $this->goalsFor += $scored;
        $this->goalsAgainst += $conceded;
        $this->points += $outcome->points();

        match ($outcome) {
            MatchOutcome::Win => $this->won++,
            MatchOutcome::Draw => $this->drawn++,
            MatchOutcome::Loss => $this->lost++,
        };
    }

    public function toStanding(): Standing
    {
        return new Standing(
            $this->team,
            $this->won + $this->drawn + $this->lost,
            $this->won,
            $this->drawn,
            $this->lost,
            $this->goalsFor,
            $this->goalsAgainst,
            $this->points,
        );
    }
}
