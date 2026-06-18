<?php

declare(strict_types=1);

namespace App\Domain\Prediction;

use App\Domain\League\Fixture;
use App\Domain\League\LeagueTable;
use App\Domain\Random\RandomSource;
use App\Domain\Team\Team;

/**
 * A zero-simulation baseline: a team can still win iff its best attainable points (current +
 * three per remaining game) reaches the best current points any rival already holds. The title
 * is spread uniformly over those contenders — so a mathematically clinched leader collapses to
 * 1.0 on its own, and eliminated teams to 0, without ever rolling a die.
 */
final readonly class DeterministicClincher implements ChampionPredictor
{
    private const POINTS_PER_WIN = 3;

    public function __construct(private LeagueTable $table) {}

    public function predict(array $teams, array $played, array $remaining, RandomSource $random): ChampionProbabilities
    {
        $points = [];
        foreach ($this->table->project($teams, $played) as $standing) {
            $points[$standing->team->id] = $standing->points;
        }

        $ceiling = $this->ceilings($points, $remaining);
        $rivalBest = $this->rivalBest($points);

        $contenders = [];
        foreach ($teams as $team) {
            $id = $team->id;
            if ($ceiling[$id] >= $rivalBest($id)) {
                $contenders[$id] = true;
            }
        }

        $share = 1.0 / count($contenders);

        $probabilities = [];
        foreach ($teams as $team) {
            $probabilities[$team->id] = isset($contenders[$team->id]) ? $share : 0.0;
        }

        return new ChampionProbabilities($probabilities);
    }

    /**
     * @param  array<string, int>  $points
     * @param  Fixture[]  $remaining
     * @return array<string, int>
     */
    private function ceilings(array $points, array $remaining): array
    {
        $games = array_fill_keys(array_keys($points), 0);

        foreach ($remaining as $fixture) {
            $games[$fixture->homeTeamId]++;
            $games[$fixture->awayTeamId]++;
        }

        $ceiling = [];
        foreach ($points as $id => $current) {
            $ceiling[$id] = $current + $games[$id] * self::POINTS_PER_WIN;
        }

        return $ceiling;
    }

    /**
     * The highest points held by any team other than the one asked about — derived from a
     * single top-two scan, so it stays O(1) per team rather than rescanning the table.
     *
     * @param  array<string, int>  $points
     * @return callable(string): int
     */
    private function rivalBest(array $points): callable
    {
        $values = array_values($points);
        rsort($values);
        $highest = $values[0] ?? 0;
        $second = $values[1] ?? 0;
        $highestIsUnique = count(array_keys($points, $highest, true)) === 1;

        return static fn (string $id): int => ($highestIsUnique && $points[$id] === $highest) ? $second : $highest;
    }
}
