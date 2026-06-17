<?php

declare(strict_types=1);

namespace App\Domain\Prediction;

use App\Domain\League\Fixture;
use App\Domain\League\MatchResult;
use App\Domain\Random\RandomSource;
use App\Domain\Team\Team;

interface ChampionPredictor
{
    /**
     * Randomness is supplied per call, not held: the caller owns seeding, so a run is
     * reproducible from the source it is handed. Deterministic predictors ignore it.
     *
     * @param  Team[]  $teams
     * @param  MatchResult[]  $played
     * @param  Fixture[]  $remaining
     */
    public function predict(array $teams, array $played, array $remaining, RandomSource $random): ChampionProbabilities;
}
