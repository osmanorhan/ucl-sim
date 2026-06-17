<?php

declare(strict_types=1);

use App\Domain\Random\SeededRandomSource;

/** @return list<float> */
function draws(int $seed, int $count): array
{
    $source = new SeededRandomSource($seed);

    return array_map(static fn (): float => $source->nextFloat(), range(1, $count));
}

it('produces an identical sequence for the same seed', function () {
    expect(draws(42, 50))->toBe(draws(42, 50));
});

it('produces different sequences for different seeds', function () {
    expect(draws(1, 50))->not->toBe(draws(2, 50));
});

it('decorrelates even adjacent seeds and a zero seed', function () {
    expect(draws(0, 20))->not->toBe(draws(1, 20))
        ->and(draws(0, 20))->not->toEqual(array_fill(0, 20, 0.0));
});

it('keeps every draw within the half-open unit interval', function () {
    foreach (draws(7, 1000) as $value) {
        expect($value)->toBeGreaterThanOrEqual(0.0)->toBeLessThan(1.0);
    }
});

it('is uniform: the mean approaches one half over many draws', function () {
    $values = draws(123, 100_000);

    expect(array_sum($values) / count($values))->toBeGreaterThan(0.49)->toBeLessThan(0.51);
});

it('is uniform: ten equal buckets each receive close to a tenth of the mass', function () {
    $buckets = array_fill(0, 10, 0);

    foreach (draws(456, 100_000) as $value) {
        $buckets[(int) ($value * 10)]++;
    }

    foreach ($buckets as $count) {
        expect($count)->toBeGreaterThan(9_000)->toBeLessThan(11_000);
    }
});
