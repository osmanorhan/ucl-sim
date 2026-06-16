<?php

declare(strict_types=1);

namespace App\Domain\Ranking;

use App\Domain\League\Standing;
use Closure;

final readonly class MetricComparator implements Comparator
{
    /** @param Closure(Standing): (int|float) $metric */
    public function __construct(
        private Closure $metric,
        private Direction $direction,
    ) {}

    public function compare(Standing $a, Standing $b): int
    {
        return $this->direction->order(($this->metric)($a), ($this->metric)($b));
    }
}
