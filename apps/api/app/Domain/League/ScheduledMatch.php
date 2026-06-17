<?php

declare(strict_types=1);

namespace App\Domain\League;

use InvalidArgumentException;

/**
 * A fixture together with its result once played. The persisted unit of a league: identity and
 * week come from the {@see Fixture}; a null result means "not yet played". A played match also
 * carries where its result came from ({@see ResultOrigin}) — and the two are bound, so a result
 * without an origin (or an origin without a result) cannot be represented.
 */
final readonly class ScheduledMatch
{
    public function __construct(
        public string $id,
        public Fixture $fixture,
        public ?MatchResult $result = null,
        public ?ResultOrigin $origin = null,
    ) {
        if (($result === null) !== ($origin === null)) {
            throw new InvalidArgumentException('A match result and its origin are present together or not at all.');
        }
    }

    public function isPlayed(): bool
    {
        return $this->result !== null;
    }

    public function withResult(MatchResult $result, ResultOrigin $origin): self
    {
        return new self($this->id, $this->fixture, $result, $origin);
    }
}
