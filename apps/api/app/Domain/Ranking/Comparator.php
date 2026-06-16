<?php

declare(strict_types=1);

namespace App\Domain\Ranking;

use App\Domain\League\Standing;

interface Comparator
{
    public function compare(Standing $a, Standing $b): int;
}
