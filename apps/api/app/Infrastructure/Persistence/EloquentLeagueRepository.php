<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\League\Fixture;
use App\Domain\League\LeagueState;
use App\Domain\League\MatchResult;
use App\Domain\League\ScheduledMatch;
use App\Domain\Persistence\LeagueRepository;
use App\Domain\Team\PowerRating;
use App\Domain\Team\Team as DomainTeam;
use App\Models\League;
use App\Models\MatchRecord;
use App\Models\Team;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * Maps the {@see LeagueState} aggregate to and from three tables. All the framework lives here, on
 * the persistence edge; the domain that calls it stays Illuminate-free. A save is one transaction
 * so the snapshot and the facts it summarises can never be observed out of step (ADR-03).
 */
final class EloquentLeagueRepository implements LeagueRepository
{
    public function save(LeagueState $state, array $snapshot): void
    {
        DB::transaction(function () use ($state, $snapshot): void {
            League::updateOrCreate(
                ['id' => $state->id],
                ['name' => $state->name, 'seed' => $state->seed, 'version' => $state->version, 'snapshot' => $snapshot],
            );

            foreach ($state->teams as $team) {
                Team::updateOrCreate(
                    ['league_id' => $state->id, 'team_id' => $team->id],
                    ['name' => $team->name, 'power' => $team->power->value],
                );
            }

            foreach (array_values($state->matches) as $sequence => $match) {
                MatchRecord::updateOrCreate(
                    ['id' => $match->id],
                    [
                        'league_id' => $state->id,
                        'sequence' => $sequence,
                        'week' => $match->fixture->week,
                        'home_team_id' => $match->fixture->homeTeamId,
                        'away_team_id' => $match->fixture->awayTeamId,
                        'home_goals' => $match->result?->homeGoals,
                        'away_goals' => $match->result?->awayGoals,
                        'origin' => $match->origin,
                    ],
                );
            }
        });
    }

    public function find(string $id): ?LeagueState
    {
        $league = League::with(['teams', 'matches' => fn (HasMany $query) => $query->orderBy('sequence')])->find($id);

        if ($league === null) {
            return null;
        }

        return new LeagueState(
            $league->id,
            $league->name,
            $league->seed,
            $league->version,
            $league->teams->map($this->toDomainTeam(...))->all(),
            $league->matches->map($this->toScheduledMatch(...))->all(),
        );
    }

    public function snapshot(string $id): ?array
    {
        $stored = League::find($id)?->snapshot;

        if (! is_array($stored)) {
            return null;
        }

        $snapshot = [];
        foreach ($stored as $key => $value) {
            $snapshot[(string) $key] = $value;
        }

        return $snapshot;
    }

    public function leagueIdForMatch(string $matchId): ?string
    {
        return MatchRecord::find($matchId)?->league_id;
    }

    private function toDomainTeam(Team $team): DomainTeam
    {
        return new DomainTeam($team->team_id, $team->name, new PowerRating($team->power));
    }

    private function toScheduledMatch(MatchRecord $match): ScheduledMatch
    {
        $fixture = new Fixture($match->home_team_id, $match->away_team_id, $match->week);

        $result = $match->home_goals === null || $match->away_goals === null
            ? null
            : new MatchResult($match->home_team_id, $match->away_team_id, $match->home_goals, $match->away_goals);

        return new ScheduledMatch($match->id, $fixture, $result, $match->origin);
    }
}
