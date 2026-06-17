<?php

declare(strict_types=1);

namespace App\Domain\Simulation;

use App\Domain\League\MatchResult;
use App\Domain\Random\PoissonDistribution;
use App\Domain\Random\RandomSource;

/**
 * A matchup whose goal distributions are fixed once (powers don't change), so repeated samples
 * cost only the random draws — the unit that makes the Monte Carlo loop allocation-light.
 */
final readonly class PreparedMatch implements MatchSampler
{
    public function __construct(
        private string $homeTeamId,
        private string $awayTeamId,
        private PoissonDistribution $home,
        private PoissonDistribution $away,
    ) {}

    public function sample(RandomSource $random): MatchResult
    {
        return new MatchResult(
            $this->homeTeamId,
            $this->awayTeamId,
            $this->home->sample($random),
            $this->away->sample($random),
        );
    }
}
