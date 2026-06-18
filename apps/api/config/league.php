<?php

declare(strict_types=1);

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

    'rate_limits' => [
        'mutations_per_minute' => (int) env('LEAGUE_MUTATION_RATE_LIMIT', 30),
        'evaluation_per_minute' => (int) env('LEAGUE_EVALUATION_RATE_LIMIT', 6),
    ],
];
