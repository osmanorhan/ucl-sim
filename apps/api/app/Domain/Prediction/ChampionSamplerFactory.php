<?php

declare(strict_types=1);

namespace App\Domain\Prediction;

use App\Domain\League\Fixture;
use App\Domain\League\LeagueTable;
use App\Domain\League\MatchResult;
use App\Domain\Ranking\Ranking;
use App\Domain\Simulation\SeasonSimulator;
use App\Domain\Team\Team;

/**
 * Compiles a league state into a reusable {@see ChampionSampler}. The single seam through which
 * both the Monte Carlo predictor and the evaluation harness's ground truth obtain completed
 * seasons — so the "compile once, sample many" machinery lives in exactly one place.
 */
final readonly class ChampionSamplerFactory
{
    public function __construct(
        private SeasonSimulator $simulator,
        private LeagueTable $table,
        private Ranking $ranking,
    ) {}

    /**
     * @param  Team[]  $teams
     * @param  MatchResult[]  $played
     * @param  Fixture[]  $remaining
     */
    public function compile(array $teams, array $played, array $remaining): ChampionSampler
    {
        return new ChampionSampler(
            $this->simulator->compile($teams, $remaining),
            $this->table->baseline($teams, $played),
            $played,
            $this->table,
            $this->ranking,
        );
    }
}
