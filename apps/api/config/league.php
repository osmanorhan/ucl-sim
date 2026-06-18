<?php

declare(strict_types=1);
use App\Domain\Simulation\PoissonGoalModel;

return [
    /*
     * Trials the live Monte Carlo predictor runs per recompute. Cost lands on writes, not reads
     * (ADR-01); raise it for sharper odds, lower it for snappier writes.
     */
    'prediction_iterations' => (int) env('LEAGUE_PREDICTION_ITERATIONS', 10_000),

    /*
     * The explicit strategy bake-off (GET /evaluation) — deliberately the heavy analysis path.
     * Scenarios fan the current state across decorrelated seeds; draws set ground-truth resolution.
     */
    'evaluation' => [
        'scenarios' => (int) env('LEAGUE_EVALUATION_SCENARIOS', 6),
        'ground_truth_draws' => (int) env('LEAGUE_EVALUATION_DRAWS', 400),
    ],

    /*
     * Poisson goal-model knobs. Expected goals scale with the power ratio, lifted on the home
     * side; tune the league baseline and home advantage rather than hard-coding them. Defaults
     * mirror the Domain class so the pure model stays usable on its own.
     */
    'goal_model' => [
        'base_goals' => (float) env('LEAGUE_BASE_GOALS', PoissonGoalModel::DEFAULT_BASE_GOALS),
        'home_advantage' => (float) env('LEAGUE_HOME_ADVANTAGE', PoissonGoalModel::DEFAULT_HOME_ADVANTAGE),
    ],

    'rate_limits' => [
        'mutations_per_minute' => (int) env('LEAGUE_MUTATION_RATE_LIMIT', 30),
        'evaluation_per_minute' => (int) env('LEAGUE_EVALUATION_RATE_LIMIT', 6),
    ],
];
