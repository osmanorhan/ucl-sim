<?php

declare(strict_types=1);

namespace App\Domain\Simulation;

use App\Domain\Team\Team;

interface GoalModel
{
    public function expected(Team $home, Team $away): GoalExpectation;
}
