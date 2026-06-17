<?php

declare(strict_types=1);

namespace App\Domain\Simulation;

use App\Domain\Team\Team;

interface MatchSimulator
{
    public function prepare(Team $home, Team $away): MatchSampler;
}
