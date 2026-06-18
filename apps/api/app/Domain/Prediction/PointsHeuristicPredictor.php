<?php

declare(strict_types=1);

namespace App\Domain\Prediction;

use App\Domain\League\LeagueTable;
use App\Domain\Random\RandomSource;

/**
 * The baseline to beat: title odds proportional to current points, blind to who plays whom in
 * the games that remain. Laplace smoothing keeps every probability strictly positive (so log-loss
 * stays finite) and a winless table from collapsing. It captures "the leader is favourite" and
 * nothing more — which is exactly why a real simulation should outscore it on a proper rule.
 */
final readonly class PointsHeuristicPredictor implements ChampionPredictor
{
    private const SMOOTHING = 1.0;

    public function __construct(private LeagueTable $table) {}

    public function predict(array $teams, array $played, array $remaining, RandomSource $random): ChampionProbabilities
    {
        $weights = [];
        foreach ($this->table->project($teams, $played) as $standing) {
            $weights[$standing->team->id] = $standing->points + self::SMOOTHING;
        }

        $total = array_sum($weights);

        return ChampionProbabilities::fromTeams(
            $teams,
            static fn ($team): float => $weights[$team->id] / $total,
        );
    }
}
