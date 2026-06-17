<?php

declare(strict_types=1);

namespace App\Domain\Random;

interface RandomSource
{
    /** A uniform draw in the half-open interval [0, 1). */
    public function nextFloat(): float;
}
