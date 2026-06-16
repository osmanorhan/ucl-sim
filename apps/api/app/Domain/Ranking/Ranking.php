<?php

declare(strict_types=1);

namespace App\Domain\Ranking;

use App\Domain\League\MatchResult;
use App\Domain\League\Standing;

interface Ranking
{
    /**
     * @param  Standing[]  $standings
     * @param  MatchResult[]  $results
     * @return Standing[]
     */
    public function rank(array $standings, array $results): array;
}
