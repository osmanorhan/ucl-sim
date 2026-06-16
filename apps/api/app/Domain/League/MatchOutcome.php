<?php

declare(strict_types=1);

namespace App\Domain\League;

enum MatchOutcome
{
    case Win;
    case Draw;
    case Loss;

    public function points(): int
    {
        return match ($this) {
            self::Win => 3,
            self::Draw => 1,
            self::Loss => 0,
        };
    }
}
