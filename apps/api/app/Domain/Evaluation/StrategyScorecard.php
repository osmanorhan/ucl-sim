<?php

declare(strict_types=1);

namespace App\Domain\Evaluation;

use App\Domain\Support\Guard;

/**
 * One predictor's report card across a benchmark: how accurate (Brier, log-loss), how fast
 * (mean latency per prediction), and whether the same seed reproduces the same forecast. The unit
 * that lets the harness answer "better / safer / faster" with numbers instead of opinion.
 */
final readonly class StrategyScorecard
{
    public function __construct(
        public string $label,
        public float $brier,
        public float $logLoss,
        public float $meanLatencyMs,
        public bool $deterministic,
    ) {
        Guard::nonNegativeFloat($brier, 'Brier score');
        Guard::nonNegativeFloat($logLoss, 'Log loss');
        Guard::nonNegativeFloat($meanLatencyMs, 'Mean latency');
    }
}
