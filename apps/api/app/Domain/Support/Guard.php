<?php

declare(strict_types=1);

namespace App\Domain\Support;

use InvalidArgumentException;

final class Guard
{
    public static function distinct(string $a, string $b, string $message): void
    {
        if ($a === $b) {
            throw new InvalidArgumentException($message);
        }
    }

    public static function nonNegative(int $value, string $field): void
    {
        if ($value < 0) {
            throw new InvalidArgumentException("{$field} cannot be negative.");
        }
    }

    public static function positive(int $value, string $field): void
    {
        if ($value < 1) {
            throw new InvalidArgumentException("{$field} must be a positive integer.");
        }
    }

    public static function positiveFloat(float $value, string $field): void
    {
        if ($value <= 0.0) {
            throw new InvalidArgumentException("{$field} must be greater than zero.");
        }
    }

    public static function nonNegativeFloat(float $value, string $field): void
    {
        if ($value < 0.0) {
            throw new InvalidArgumentException("{$field} cannot be negative.");
        }
    }
}
