<?php

declare(strict_types=1);

namespace App\Domain\League;

use App\Domain\Team\Team;
use InvalidArgumentException;

final readonly class LeagueTable
{
    /**
     * @param  Team[]  $teams
     * @param  MatchResult[]  $results
     * @return Standing[]
     */
    public function project(array $teams, array $results): array
    {
        return $this->extend($this->baseline($teams), $results);
    }

    /**
     * Fresh records with `$played` folded in once. Reuse a baseline as the fixed starting point
     * for many independent projections (Monte Carlo) instead of re-folding the played matches.
     *
     * @param  Team[]  $teams
     * @param  MatchResult[]  $played
     * @return array<string, TeamRecord>
     */
    public function baseline(array $teams, array $played = []): array
    {
        $records = [];
        foreach ($teams as $team) {
            $records[$team->id] = new TeamRecord($team);
        }

        foreach ($played as $result) {
            $this->apply($records, $result);
        }

        return $records;
    }

    /**
     * Fold `$results` onto a private copy of the baseline — the baseline is left untouched, so
     * one baseline seeds any number of completed seasons.
     *
     * @param  array<string, TeamRecord>  $baseline
     * @param  MatchResult[]  $results
     * @return Standing[]
     */
    public function extend(array $baseline, array $results): array
    {
        $records = [];
        foreach ($baseline as $id => $record) {
            $records[$id] = clone $record;
        }

        foreach ($results as $result) {
            $this->apply($records, $result);
        }

        $standings = [];
        foreach ($records as $record) {
            $standings[] = $record->toStanding();
        }

        return $standings;
    }

    /** @param array<string, TeamRecord> $records */
    private function apply(array $records, MatchResult $result): void
    {
        if (! isset($records[$result->homeTeamId], $records[$result->awayTeamId])) {
            $unknown = array_diff([$result->homeTeamId, $result->awayTeamId], array_keys($records));

            throw new InvalidArgumentException('Result references unknown teams: '.implode(', ', $unknown));
        }

        $records[$result->homeTeamId]->record($result->outcomeFor($result->homeTeamId), $result->homeGoals, $result->awayGoals);
        $records[$result->awayTeamId]->record($result->outcomeFor($result->awayTeamId), $result->awayGoals, $result->homeGoals);
    }
}
