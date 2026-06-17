<?php

declare(strict_types=1);

namespace App\Domain\Simulation;

use App\Domain\Support\Guard;
use App\Domain\Team\Team;

/**
 * Expected goals scale with the ratio of the two power ratings, lifted on the home side by
 * a fixed advantage factor. Both knobs (league baseline, home advantage) are parameters, so
 * the model is tunable and comparable rather than a buried constant.
 */
final readonly class PoissonGoalModel implements GoalModel
{
    public function __construct(
        private float $baseGoals = 1.35,
        private float $homeAdvantage = 1.25,
    ) {
        Guard::positiveFloat($baseGoals, 'Base goals');
        Guard::positiveFloat($homeAdvantage, 'Home advantage');
    }

    public function expected(Team $home, Team $away): GoalExpectation
    {
        $ratio = $home->power->value / $away->power->value;

        return new GoalExpectation(
            $this->baseGoals * $ratio * $this->homeAdvantage,
            $this->baseGoals / $ratio,
        );
    }
}
