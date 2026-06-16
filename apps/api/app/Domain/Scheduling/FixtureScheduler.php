<?php

declare(strict_types=1);

namespace App\Domain\Scheduling;

use App\Domain\League\Fixture;
use App\Domain\Team\Team;

interface FixtureScheduler
{
    /**
     * @param  Team[]  $teams
     * @return Fixture[]
     */
    public function schedule(array $teams): array;
}
