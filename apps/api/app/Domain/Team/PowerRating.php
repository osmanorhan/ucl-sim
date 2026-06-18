<?php

declare(strict_types=1);

namespace App\Domain\Team;

use InvalidArgumentException;

final readonly class PowerRating
{
    public const MAX = 100;

    public function __construct(public float $value)
    {
        if ($value <= 0.0) {
            throw new InvalidArgumentException('Power rating must be positive.');
        }

        if ($value > self::MAX) {
            throw new InvalidArgumentException('Power rating cannot exceed '.self::MAX.'.');
        }
    }
}
