<?php

declare(strict_types=1);

namespace App\Domain\Evaluation;

use App\Domain\League\Fixture;
use App\Domain\League\MatchResult;
use App\Domain\Team\Team;

/**
 * A single prediction problem the harness scores against: the league as it stands, the fixtures
 * left to play, and the seed that makes both the forecast and its ground truth reproducible.
 */
final readonly class Scenario
{
    /**
     * @param  Team[]  $teams
     * @param  MatchResult[]  $played
     * @param  Fixture[]  $remaining
     */
    public function __construct(
        public array $teams,
        public array $played,
        public array $remaining,
        public int $seed,
    ) {}
}
