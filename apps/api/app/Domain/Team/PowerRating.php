<?php

declare(strict_types=1);

namespace App\Domain\Team;

use InvalidArgumentException;

final readonly class PowerRating
{
    public function __construct(public float $value)
    {
        if ($value <= 0.0) {
            throw new InvalidArgumentException('Power rating must be positive.');
        }
    }
}
