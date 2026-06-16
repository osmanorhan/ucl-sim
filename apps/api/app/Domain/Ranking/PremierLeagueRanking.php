<?php

declare(strict_types=1);

namespace App\Domain\Ranking;

use App\Domain\League\LeagueTable;
use App\Domain\League\MatchResult;
use App\Domain\League\Standing;

final class PremierLeagueRanking implements Ranking
{
    private Comparator $ordinal;

    public function __construct(private readonly LeagueTable $table)
    {
        $this->ordinal = new CompositeComparator(
            new MetricComparator(static fn (Standing $s): int => $s->points, Direction::Descending),
            new MetricComparator(static fn (Standing $s): int => $s->goalDifference(), Direction::Descending),
            new MetricComparator(static fn (Standing $s): int => $s->goalsFor, Direction::Descending),
        );
    }

    public function rank(array $standings, array $results): array
    {
        usort($standings, $this->ordinal->compare(...));

        $groups = $this->groupByEqual($standings);
        $meetings = $this->meetingsByGroup($groups, $results);

        $ranked = [];

        foreach ($groups as $index => $tied) {
            array_push($ranked, ...$this->breakTie($tied, $meetings[$index] ?? []));
        }

        return $ranked;
    }

    /**
     * @param  Standing[]  $standings
     * @return array<int, Standing[]>
     */
    private function groupByEqual(array $standings): array
    {
        $groups = [];

        foreach ($standings as $standing) {
            $last = count($groups) - 1;

            if ($last >= 0 && $this->ordinal->compare($groups[$last][0], $standing) === 0) {
                $groups[$last][] = $standing;

                continue;
            }

            $groups[] = [$standing];
        }

        return $groups;
    }

    /**
     * Bucket each result into the tied group both its teams belong to, in a single
     * pass with O(1) membership — never a per-group scan of all results.
     *
     * @param  array<int, Standing[]>  $groups
     * @param  MatchResult[]  $results
     * @return array<int, MatchResult[]>
     */
    private function meetingsByGroup(array $groups, array $results): array
    {
        $groupOf = [];

        foreach ($groups as $index => $tied) {
            foreach ($tied as $standing) {
                $groupOf[$standing->team->id] = $index;
            }
        }

        $buckets = [];

        foreach ($results as $result) {
            $home = $groupOf[$result->homeTeamId] ?? null;

            if ($home !== null && $home === ($groupOf[$result->awayTeamId] ?? null)) {
                $buckets[$home][] = $result;
            }
        }

        return $buckets;
    }

    /**
     * Resolve a group level on every ordinal metric by ranking a mini-league of
     * only the matches its members played against each other.
     *
     * @param  Standing[]  $tied
     * @param  MatchResult[]  $meetings
     * @return Standing[]
     */
    private function breakTie(array $tied, array $meetings): array
    {
        if (count($tied) < 2) {
            return $tied;
        }

        $teams = array_map(static fn (Standing $s) => $s->team, $tied);

        $mini = $this->table->project($teams, $meetings);
        usort($mini, $this->ordinal->compare(...));
        $order = array_flip(array_map(static fn (Standing $s): string => $s->team->id, $mini));

        usort($tied, static fn (Standing $a, Standing $b): int => $order[$a->team->id] <=> $order[$b->team->id]);

        return $tied;
    }
}
