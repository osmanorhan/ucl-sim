<?php

declare(strict_types=1);

namespace App\Domain\Simulation;

use App\Domain\Support\Guard;

final readonly class GoalExpectation
{
    public function __construct(public float $home, public float $away)
    {
        Guard::positiveFloat($home, 'Home expected goals');
        Guard::positiveFloat($away, 'Away expected goals');
    }
}
