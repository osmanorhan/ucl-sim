<?php

declare(strict_types=1);

namespace App\Domain\Ranking;

use App\Domain\League\Standing;

final readonly class CompositeComparator implements Comparator
{
    /** @var Comparator[] */
    private array $comparators;

    public function __construct(Comparator ...$comparators)
    {
        $this->comparators = $comparators;
    }

    public function compare(Standing $a, Standing $b): int
    {
        foreach ($this->comparators as $comparator) {
            $ordering = $comparator->compare($a, $b);

            if ($ordering !== 0) {
                return $ordering;
            }
        }

        return 0;
    }
}
