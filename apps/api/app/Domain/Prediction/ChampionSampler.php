<?php

declare(strict_types=1);

namespace App\Domain\Prediction;

use App\Domain\League\LeagueTable;
use App\Domain\League\MatchResult;
use App\Domain\League\TeamRecord;
use App\Domain\Random\RandomSource;
use App\Domain\Ranking\Ranking;
use App\Domain\Simulation\SeasonSampler;

/**
 * A remaining season compiled to a reusable draw: each `draw()` completes the fixtures from
 * fresh randomness, folds them onto the fixed baseline, ranks the finished table and yields the
 * champion's id. The constant work (fixture compilation, played baseline) is paid once, so a
 * Monte Carlo loop — and the evaluation harness's ground truth — both reduce to repeated draws.
 */
final readonly class ChampionSampler
{
    /**
     * @param  array<string, TeamRecord>  $baseline
     * @param  MatchResult[]  $played
     */
    public function __construct(
        private SeasonSampler $season,
        private array $baseline,
        private array $played,
        private LeagueTable $table,
        private Ranking $ranking,
    ) {}

    public function draw(RandomSource $random): string
    {
        $simulated = $this->season->sample($random);
        $standings = $this->table->extend($this->baseline, $simulated);
        $ranked = $this->ranking->rank($standings, array_merge($this->played, $simulated));

        return $ranked[0]->team->id;
    }
}
