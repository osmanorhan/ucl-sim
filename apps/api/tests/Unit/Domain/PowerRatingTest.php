<?php

declare(strict_types=1);

use App\Domain\Team\PowerRating;

it('accepts a positive rating up to the maximum', function () {
    expect((new PowerRating((float) PowerRating::MAX))->value)->toBe((float) PowerRating::MAX);
});

it('rejects a non-positive rating', function () {
    new PowerRating(0.0);
})->throws(InvalidArgumentException::class);

it('rejects a rating above the maximum', function () {
    new PowerRating(PowerRating::MAX + 1);
})->throws(InvalidArgumentException::class);
