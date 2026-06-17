<?php

declare(strict_types=1);

namespace App\Domain\League;

use App\Domain\Team\Team;

/**
 * The authoritative, immutable state of one league: its teams, its scheduled matches (some played,
 * some not), the seed its simulation is reproducible from, and a monotonic version. Every derived
 * view — table, predictions, snapshot — is a pure projection of this; nothing derived is stored as
 * truth (ADR-06). The played/remaining split the domain math consumes is computed on demand here,
 * so callers pass one aggregate rather than three parallel arrays.
 */
final readonly class LeagueState
{
    /**
     * @param  Team[]  $teams
     * @param  ScheduledMatch[]  $matches
     */
    public function __construct(
        public string $id,
        public string $name,
        public int $seed,
        public int $version,
        public array $teams,
        public array $matches,
    ) {}

    /** @return MatchResult[] */
    public function played(): array
    {
        $results = [];
        foreach ($this->matches as $match) {
            if ($match->result !== null) {
                $results[] = $match->result;
            }
        }

        return $results;
    }

    /** @return Fixture[] */
    public function remaining(): array
    {
        $fixtures = [];
        foreach ($this->matches as $match) {
            if (! $match->isPlayed()) {
                $fixtures[] = $match->fixture;
            }
        }

        return $fixtures;
    }

    public function nextWeek(): ?int
    {
        $weeks = [];
        foreach ($this->matches as $match) {
            if (! $match->isPlayed()) {
                $weeks[] = $match->fixture->week;
            }
        }

        return $weeks === [] ? null : min($weeks);
    }

    public function completedWeeks(): int
    {
        $pending = [];
        $weeks = [];
        foreach ($this->matches as $match) {
            $weeks[$match->fixture->week] = true;
            if (! $match->isPlayed()) {
                $pending[$match->fixture->week] = true;
            }
        }

        return count(array_diff_key($weeks, $pending));
    }

    public function totalWeeks(): int
    {
        $max = 0;
        foreach ($this->matches as $match) {
            $max = max($max, $match->fixture->week);
        }

        return $max;
    }

    /** @param ScheduledMatch[] $matches */
    public function withMatches(array $matches): self
    {
        return new self($this->id, $this->name, $this->seed, $this->version + 1, $this->teams, $matches);
    }
}
