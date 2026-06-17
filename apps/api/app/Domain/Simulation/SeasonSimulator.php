<?php

declare(strict_types=1);

namespace App\Domain\Simulation;

use App\Domain\League\Fixture;
use App\Domain\League\MatchResult;
use App\Domain\Random\RandomSource;
use App\Domain\Team\Team;
use InvalidArgumentException;

final readonly class SeasonSimulator
{
    public function __construct(private MatchSimulator $simulator) {}

    /**
     * @param  Team[]  $teams
     * @param  Fixture[]  $fixtures
     * @return MatchResult[]
     */
    public function play(array $teams, array $fixtures, RandomSource $random): array
    {
        return $this->compile($teams, $fixtures)->sample($random);
    }

    /**
     * Resolve fixtures to prepared matchups once, so the result can be sampled many times
     * (the Monte Carlo loop) without re-resolving teams or rebuilding goal distributions.
     *
     * @param  Team[]  $teams
     * @param  Fixture[]  $fixtures
     */
    public function compile(array $teams, array $fixtures): SeasonSampler
    {
        $byId = $this->index($teams);

        $matches = [];
        foreach ($fixtures as $fixture) {
            $matches[] = $this->simulator->prepare(
                $this->resolve($byId, $fixture->homeTeamId),
                $this->resolve($byId, $fixture->awayTeamId),
            );
        }

        return new SeasonSampler($matches);
    }

    /**
     * @param  Team[]  $teams
     * @return array<string, Team>
     */
    private function index(array $teams): array
    {
        $byId = [];
        foreach ($teams as $team) {
            $byId[$team->id] = $team;
        }

        return $byId;
    }

    /** @param array<string, Team> $byId */
    private function resolve(array $byId, string $teamId): Team
    {
        return $byId[$teamId] ?? throw new InvalidArgumentException("Fixture references unknown team: {$teamId}.");
    }
}
