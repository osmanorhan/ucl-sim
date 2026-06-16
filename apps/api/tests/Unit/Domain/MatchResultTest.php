<?php

declare(strict_types=1);

use App\Domain\League\MatchOutcome;
use App\Domain\League\MatchResult;

it('identifies the home winner', function () {
    $result = new MatchResult('a', 'b', 2, 1);

    expect($result->winnerId())->toBe('a')
        ->and($result->isDraw())->toBeFalse();
});

it('identifies the away winner', function () {
    expect((new MatchResult('a', 'b', 0, 3))->winnerId())->toBe('b');
});

it('identifies a draw', function () {
    $result = new MatchResult('a', 'b', 1, 1);

    expect($result->winnerId())->toBeNull()
        ->and($result->isDraw())->toBeTrue();
});

it('reports the outcome from each team perspective', function () {
    $win = new MatchResult('a', 'b', 2, 0);
    $draw = new MatchResult('a', 'b', 1, 1);

    expect($win->outcomeFor('a'))->toBe(MatchOutcome::Win)
        ->and($win->outcomeFor('b'))->toBe(MatchOutcome::Loss)
        ->and($draw->outcomeFor('a'))->toBe(MatchOutcome::Draw);
});

it('scores outcomes by the three-one-zero rule', function () {
    expect(outcomePoints(MatchOutcome::Win))->toBe(3)
        ->and(outcomePoints(MatchOutcome::Draw))->toBe(1)
        ->and(outcomePoints(MatchOutcome::Loss))->toBe(0);
});

it('rejects a match of a team against itself', function () {
    new MatchResult('a', 'a', 1, 0);
})->throws(InvalidArgumentException::class);

it('rejects negative goals', function () {
    new MatchResult('a', 'b', -1, 0);
})->throws(InvalidArgumentException::class);
