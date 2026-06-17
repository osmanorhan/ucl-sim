<?php

declare(strict_types=1);

namespace App\Domain\Prediction;

use App\Domain\League\LeagueTable;
use App\Domain\Random\RandomSource;
use App\Domain\Ranking\Ranking;
use App\Domain\Simulation\SeasonSimulator;
use App\Domain\Support\Guard;
use App\Domain\Team\Team;

/**
 * Completes the remaining fixtures many times, ranks each finished season with the league's
 * own ranking, and reports how often each team finishes top. Reproducible from the injected
 * RandomSource; accuracy trades against iteration count.
 */
final readonly class MonteCarloPredictor implements ChampionPredictor
{
    public function __construct(
        private SeasonSimulator $simulator,
        private LeagueTable $table,
        private Ranking $ranking,
        private RandomSource $random,
        private int $iterations = 10_000,
    ) {
        Guard::positive($iterations, 'Iterations');
    }

    public function predict(array $teams, array $played, array $remaining): ChampionProbabilities
    {
        $titles = array_fill_keys(array_map(static fn (Team $t): string => $t->id, $teams), 0);

        $season = $this->simulator->compile($teams, $remaining);
        $baseline = $this->table->baseline($teams, $played);

        for ($i = 0; $i < $this->iterations; $i++) {
            $simulated = $season->sample($this->random);
            $standings = $this->table->extend($baseline, $simulated);
            $ranked = $this->ranking->rank($standings, array_merge($played, $simulated));
            $titles[$ranked[0]->team->id]++;
        }

        return new ChampionProbabilities(
            array_map(fn (int $count): float => $count / $this->iterations, $titles),
        );
    }
}
