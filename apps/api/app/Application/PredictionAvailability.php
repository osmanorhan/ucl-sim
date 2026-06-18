<?php

declare(strict_types=1);

namespace App\Application;

use App\Domain\League\LeagueState;

final readonly class PredictionAvailability
{
    private const MIN_COMPLETED_WEEKS = 4;

    /** @return array{available: bool, availableAfterCompletedWeeks: int} */
    public function for(LeagueState $state): array
    {
        return [
            'available' => $this->availableFor($state),
            'availableAfterCompletedWeeks' => self::MIN_COMPLETED_WEEKS,
        ];
    }

    public function availableFor(LeagueState $state): bool
    {
        return $state->completedWeeks() >= self::MIN_COMPLETED_WEEKS;
    }
}
