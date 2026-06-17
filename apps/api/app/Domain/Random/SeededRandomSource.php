<?php

declare(strict_types=1);

namespace App\Domain\Random;

use Random\Engine\Mt19937;
use Random\IntervalBoundary;
use Random\Randomizer;

/**
 * A seeded Mersenne Twister wrapped in PHP's Randomizer. The engine is an object that holds its
 * own state — no global mt_srand — so the same seed always replays the same sequence, and two
 * sources never interfere. That object-scoped determinism is the whole point of the seam.
 */
final class SeededRandomSource implements RandomSource
{
    private readonly Randomizer $randomizer;

    public function __construct(int $seed)
    {
        $this->randomizer = new Randomizer(new Mt19937($seed));
    }

    public function nextFloat(): float
    {
        return $this->randomizer->getFloat(0.0, 1.0, IntervalBoundary::ClosedOpen);
    }
}
