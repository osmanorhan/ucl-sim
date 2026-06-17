<?php

declare(strict_types=1);

namespace App\Domain\Simulation;

use App\Domain\Random\PoissonDistribution;
use App\Domain\Team\Team;

final readonly class PoissonMatchSimulator implements MatchSimulator
{
    public function __construct(private GoalModel $model) {}

    public function prepare(Team $home, Team $away): MatchSampler
    {
        $expected = $this->model->expected($home, $away);

        return new PreparedMatch(
            $home->id,
            $away->id,
            new PoissonDistribution($expected->home),
            new PoissonDistribution($expected->away),
        );
    }
}
