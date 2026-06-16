<?php

declare(strict_types=1);

namespace App\Domain\Scheduling;

use App\Domain\League\Fixture;
use App\Domain\Team\Team;
use InvalidArgumentException;

final class BergerRoundRobinScheduler implements FixtureScheduler
{
    public function schedule(array $teams): array
    {
        $count = count($teams);

        if ($count < 2 || $count % 2 !== 0) {
            throw new InvalidArgumentException('A round-robin schedule requires an even number of at least two teams.');
        }

        $ids = array_values(array_map(static fn (Team $team): string => $team->id, $teams));
        $firstLeg = $this->firstLeg($ids);

        return array_merge($firstLeg, $this->secondLeg($firstLeg, $count - 1));
    }

    /**
     * @param  string[]  $ids
     * @return Fixture[]
     */
    private function firstLeg(array $ids): array
    {
        $count = count($ids);
        $half = intdiv($count, 2);
        $pivot = $ids[0];
        $rotators = array_values(array_slice($ids, 1));
        $fixtures = [];

        for ($round = 0; $round < $count - 1; $round++) {
            $row = $this->seatsForRound($pivot, $rotators, $round);

            for ($seat = 0; $seat < $half; $seat++) {
                $home = $row[$seat];
                $away = $row[$count - 1 - $seat];
                [$home, $away] = ($round + $seat) % 2 === 0 ? [$home, $away] : [$away, $home];
                $fixtures[] = new Fixture($home, $away, $round + 1);
            }
        }

        return $fixtures;
    }

    /**
     * @param  string[]  $rotators
     * @return string[]
     */
    private function seatsForRound(string $pivot, array $rotators, int $round): array
    {
        $size = count($rotators);
        $rotated = array_map(
            static fn (int $seat): string => $rotators[($seat + $round) % $size],
            range(0, $size - 1),
        );

        return [$pivot, ...$rotated];
    }

    /**
     * @param  Fixture[]  $firstLeg
     * @return Fixture[]
     */
    private function secondLeg(array $firstLeg, int $weeksPerLeg): array
    {
        return array_map(
            static fn (Fixture $fixture): Fixture => new Fixture(
                $fixture->awayTeamId,
                $fixture->homeTeamId,
                $fixture->week + $weeksPerLeg,
            ),
            $firstLeg,
        );
    }
}
