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
        $records = array_combine(
            array_map(static fn (Team $team): string => $team->id, $teams),
            array_map(static fn (Team $team): TeamRecord => new TeamRecord($team), $teams),
        );

        array_walk($results, fn (MatchResult $result) => $this->apply($records, $result));

        return array_map(static fn (TeamRecord $record): Standing => $record->toStanding(), array_values($records));
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
