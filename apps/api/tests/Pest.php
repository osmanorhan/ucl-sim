<?php

declare(strict_types=1);

use App\Domain\League\MatchOutcome;
use App\Domain\Team\PowerRating;
use App\Domain\Team\Team;
use Tests\TestCase;

pest()->extend(TestCase::class)->in('Feature');

function team(string $id, float $power = 50.0): Team
{
    return new Team($id, ucfirst($id), new PowerRating($power));
}

function outcomePoints(MatchOutcome $outcome): int
{
    return $outcome->points();
}
