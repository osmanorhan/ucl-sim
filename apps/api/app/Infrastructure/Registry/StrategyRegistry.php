<?php

declare(strict_types=1);

namespace App\Infrastructure\Registry;

use App\Domain\Prediction\ChampionPredictor;
use InvalidArgumentException;

/**
 * The plug-in directory: a key to its predictor. Adding a strategy is one line here (or one tagged
 * binding) — that single fact is the "no single algorithm wins" thesis made operational. The
 * evaluation endpoint enumerates {@see all()} to score the field; the live path resolves one.
 */
final readonly class StrategyRegistry
{
    /** @param array<string, ChampionPredictor> $predictors */
    public function __construct(private array $predictors) {}

    public function get(string $key): ChampionPredictor
    {
        return $this->predictors[$key] ?? throw new InvalidArgumentException("No predictor registered for '{$key}'.");
    }

    /** @return array<string, ChampionPredictor> */
    public function all(): array
    {
        return $this->predictors;
    }
}
