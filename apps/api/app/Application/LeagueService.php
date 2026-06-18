<?php

declare(strict_types=1);

namespace App\Application;

use App\Application\Exceptions\LeagueNotFound;
use App\Application\Exceptions\MatchNotEditable;
use App\Application\Exceptions\SeasonComplete;
use App\Domain\League\LeagueState;
use App\Domain\League\MatchResult;
use App\Domain\League\ResultOrigin;
use App\Domain\League\ScheduledMatch;
use App\Domain\Persistence\LeagueRepository;
use App\Domain\Scheduling\FixtureScheduler;
use App\Domain\Simulation\SeasonProgression;
use App\Domain\Team\PowerRating;
use App\Domain\Team\Team;
use Illuminate\Support\Str;

/**
 * Orchestrates the league use-cases: create, play a week, play out the season, edit a result. Each
 * write recomputes one versioned snapshot from a single server state and persists state + snapshot
 * together (ADR-01/03). Edits are correct by construction — there is no stored table to keep in
 * sync; the snapshot is re-derived by re-folding the (mutated) match facts (ADR-06).
 */
final readonly class LeagueService
{
    public function __construct(
        private LeagueRepository $repository,
        private FixtureScheduler $scheduler,
        private SeasonProgression $progression,
        private SnapshotAssembler $assembler,
    ) {}

    /**
     * @param  array<int, array{id: string, name: string, power: float}>  $teams
     * @return array<string, mixed>
     */
    public function create(string $name, array $teams, int $seed): array
    {
        $domainTeams = array_map(
            static fn (array $team): Team => new Team($team['id'], $team['name'], new PowerRating($team['power'])),
            $teams,
        );

        $matches = array_map(
            static fn ($fixture): ScheduledMatch => new ScheduledMatch(Str::uuid()->toString(), $fixture),
            $this->scheduler->schedule($domainTeams),
        );

        $state = new LeagueState(Str::uuid()->toString(), $name, $seed, 1, $domainTeams, $matches);

        return $this->persist($state);
    }

    /** @return array<string, mixed> */
    public function playWeek(string $leagueId): array
    {
        $state = $this->load($leagueId);
        $week = $state->nextWeek() ?? throw SeasonComplete::id($leagueId);

        return $this->persist($this->apply($state, $this->simulateWeek($state, $week)));
    }

    /** @return array<string, mixed> */
    public function playAll(string $leagueId): array
    {
        $state = $this->load($leagueId);

        if ($state->nextWeek() === null) {
            throw SeasonComplete::id($leagueId);
        }

        $results = [];
        foreach ($this->unplayedWeeks($state) as $week) {
            $results += $this->simulateWeek($state, $week);
        }

        return $this->persist($this->apply($state, $results));
    }

    /** @return array<string, mixed> */
    public function edit(string $matchId, int $homeGoals, int $awayGoals): array
    {
        $leagueId = $this->repository->leagueIdForMatch($matchId) ?? throw LeagueNotFound::id($matchId);
        $state = $this->load($leagueId);
        $target = $this->match($state, $matchId);

        if (! $target->isPlayed()) {
            throw MatchNotEditable::id($matchId);
        }

        $matches = array_map(
            static fn (ScheduledMatch $match): ScheduledMatch => $match->id === $matchId
                ? $match->withResult(new MatchResult($match->fixture->homeTeamId, $match->fixture->awayTeamId, $homeGoals, $awayGoals), ResultOrigin::Manual)
                : $match,
            $state->matches,
        );

        return $this->persist($state->withMatches($matches));
    }

    /** @return array<string, mixed> the stored read model, served without recomputation (ADR-01) */
    public function snapshot(string $id): array
    {
        return $this->repository->snapshot($id) ?? throw LeagueNotFound::id($id);
    }

    public function find(string $id): LeagueState
    {
        return $this->load($id);
    }

    private function load(string $id): LeagueState
    {
        return $this->repository->find($id) ?? throw LeagueNotFound::id($id);
    }

    private function match(LeagueState $state, string $matchId): ScheduledMatch
    {
        foreach ($state->matches as $match) {
            if ($match->id === $matchId) {
                return $match;
            }
        }

        throw LeagueNotFound::id($matchId);
    }

    /** @return array<string, MatchResult> match id => simulated result */
    private function simulateWeek(LeagueState $state, int $week): array
    {
        $targets = array_values(array_filter(
            $state->matches,
            static fn (ScheduledMatch $match): bool => ! $match->isPlayed() && $match->fixture->week === $week,
        ));

        $played = $this->progression->playWeek(
            $state->teams,
            array_map(static fn (ScheduledMatch $match) => $match->fixture, $targets),
            $state->seed,
            $week,
        );

        $byId = [];
        foreach ($targets as $index => $match) {
            $byId[$match->id] = $played[$index];
        }

        return $byId;
    }

    /** @return int[] distinct unplayed weeks, ascending */
    private function unplayedWeeks(LeagueState $state): array
    {
        $weeks = [];
        foreach ($state->matches as $match) {
            if (! $match->isPlayed()) {
                $weeks[$match->fixture->week] = true;
            }
        }
        $weeks = array_keys($weeks);
        sort($weeks);

        return $weeks;
    }

    /**
     * @param  array<string, MatchResult>  $results  match id => result
     */
    private function apply(LeagueState $state, array $results): LeagueState
    {
        $matches = array_map(
            static fn (ScheduledMatch $match): ScheduledMatch => isset($results[$match->id])
                ? $match->withResult($results[$match->id], ResultOrigin::Simulated)
                : $match,
            $state->matches,
        );

        return $state->withMatches($matches);
    }

    /** @return array<string, mixed> */
    private function persist(LeagueState $state): array
    {
        $snapshot = $this->assembler->assemble($state);
        $this->repository->save($state, $snapshot);

        return $snapshot;
    }
}
