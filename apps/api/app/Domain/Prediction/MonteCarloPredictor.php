<?php

declare(strict_types=1);

namespace App\Domain\Prediction;

use App\Domain\Random\RandomSource;
use App\Domain\Support\Guard;

/**
 * Completes the remaining fixtures many times, ranks each finished season with the league's own
 * ranking, and reports how often each team finishes top. Reproducible from the supplied
 * RandomSource; accuracy trades against iteration count.
 */
final readonly class MonteCarloPredictor implements ChampionPredictor
{
    public function __construct(
        private ChampionSamplerFactory $samplers,
        private int $iterations = 10_000,
    ) {
        Guard::positive($iterations, 'Iterations');
    }

    public function predict(array $teams, array $played, array $remaining, RandomSource $random): ChampionProbabilities
    {
        $titles = [];
        foreach ($teams as $team) {
            $titles[$team->id] = 0;
        }

        $sampler = $this->samplers->compile($teams, $played, $remaining);

        for ($i = 0; $i < $this->iterations; $i++) {
            $titles[$sampler->draw($random)]++;
        }

        return ChampionProbabilities::fromTeams(
            $teams,
            fn ($team): float => $titles[$team->id] / $this->iterations,
        );
    }
}
