<?php

declare(strict_types=1);

namespace App\Domain\Random;

use App\Domain\Support\Guard;

/**
 * Knuth's multiplicative sampler: O(lambda) uniform draws per sample, which is cheap for
 * football scorelines (lambda well below 5). The `exp(-lambda)` threshold is computed once at
 * construction, so a prepared distribution can be sampled repeatedly with no transcendental
 * cost in the hot loop. The side effect — consuming entropy — is explicit in the RandomSource.
 */
final readonly class PoissonDistribution
{
    private float $threshold;

    public function __construct(float $lambda)
    {
        Guard::positiveFloat($lambda, 'Poisson lambda');

        $this->threshold = exp(-$lambda);
    }

    public function sample(RandomSource $random): int
    {
        $product = 1.0;
        $count = 0;

        do {
            $count++;
            $product *= $random->nextFloat();
        } while ($product > $this->threshold);

        return $count - 1;
    }
}
