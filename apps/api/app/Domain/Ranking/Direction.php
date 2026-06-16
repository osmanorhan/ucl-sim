<?php

declare(strict_types=1);

namespace App\Domain\Ranking;

enum Direction
{
    case Ascending;
    case Descending;

    public function order(int|float $a, int|float $b): int
    {
        return match ($this) {
            self::Ascending => $a <=> $b,
            self::Descending => $b <=> $a,
        };
    }
}
