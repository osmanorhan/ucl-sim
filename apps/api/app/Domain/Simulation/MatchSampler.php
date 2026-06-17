<?php

declare(strict_types=1);

namespace App\Domain\Simulation;

use App\Domain\League\MatchResult;
use App\Domain\Random\RandomSource;

interface MatchSampler
{
    public function sample(RandomSource $random): MatchResult;
}
