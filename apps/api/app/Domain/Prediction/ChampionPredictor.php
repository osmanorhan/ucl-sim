<?php

declare(strict_types=1);

namespace App\Domain\Prediction;

use App\Domain\League\Fixture;
use App\Domain\League\MatchResult;
use App\Domain\Team\Team;

interface ChampionPredictor
{
    /**
     * @param  Team[]  $teams
     * @param  MatchResult[]  $played
     * @param  Fixture[]  $remaining
     */
    public function predict(array $teams, array $played, array $remaining): ChampionProbabilities;
}
