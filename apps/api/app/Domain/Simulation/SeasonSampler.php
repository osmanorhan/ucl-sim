<?php

declare(strict_types=1);

namespace App\Domain\Simulation;

use App\Domain\League\MatchResult;
use App\Domain\Random\RandomSource;

/**
 * A remaining season compiled to its fixed matchups. Each `sample()` replays the whole set
 * from fresh random draws — the constant setup (fixture→team resolution, goal distributions)
 * is paid once at compile time, not per Monte Carlo trial.
 */
final readonly class SeasonSampler
{
    /** @param MatchSampler[] $matches */
    public function __construct(private array $matches) {}

    /** @return MatchResult[] */
    public function sample(RandomSource $random): array
    {
        $results = [];

        foreach ($this->matches as $match) {
            $results[] = $match->sample($random);
        }

        return $results;
    }
}
