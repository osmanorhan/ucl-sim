<?php

declare(strict_types=1);

namespace App\Domain\League;

use App\Domain\Support\Guard;

final readonly class Fixture
{
    public function __construct(
        public string $homeTeamId,
        public string $awayTeamId,
        public int $week,
    ) {
        Guard::distinct($homeTeamId, $awayTeamId, 'A fixture cannot pair a team with itself.');
        Guard::positive($week, 'Week');
    }
}
