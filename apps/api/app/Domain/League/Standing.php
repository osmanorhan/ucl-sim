<?php

declare(strict_types=1);

namespace App\Domain\League;

use App\Domain\Team\Team;

final readonly class Standing
{
    public function __construct(
        public Team $team,
        public int $played,
        public int $won,
        public int $drawn,
        public int $lost,
        public int $goalsFor,
        public int $goalsAgainst,
        public int $points,
    ) {}

    public function goalDifference(): int
    {
        return $this->goalsFor - $this->goalsAgainst;
    }
}
