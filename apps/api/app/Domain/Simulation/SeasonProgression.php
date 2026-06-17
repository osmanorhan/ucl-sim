<?php

declare(strict_types=1);

namespace App\Domain\Simulation;

use App\Domain\League\Fixture;
use App\Domain\League\MatchResult;
use App\Domain\Random\SeededRandomSource;
use App\Domain\Team\Team;

/**
 * Plays the *actual* league forward, one week at a time, deterministically. Each week draws from a
 * source seeded purely from (leagueSeed, week), so a week's outcome is a stable function of those
 * two numbers alone. That gives two properties for free: a run is reproducible from the league
 * seed, and playing week-by-week yields byte-identical results to playing the whole remainder at
 * once — there is no hidden cross-week RNG state for incremental play to diverge from (ADR-06).
 */
final readonly class SeasonProgression
{
    private const WEEK_STRIDE = 1_000_003;

    public function __construct(private SeasonSimulator $simulator) {}

    /**
     * @param  Team[]  $teams
     * @param  Fixture[]  $weekFixtures
     * @return MatchResult[]
     */
    public function playWeek(array $teams, array $weekFixtures, int $seed, int $week): array
    {
        return $this->simulator->play($teams, $weekFixtures, new SeededRandomSource($seed + $week * self::WEEK_STRIDE));
    }
}
