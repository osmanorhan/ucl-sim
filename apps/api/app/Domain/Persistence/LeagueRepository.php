<?php

declare(strict_types=1);

namespace App\Domain\Persistence;

use App\Domain\League\LeagueState;

/**
 * The persistence seam. The domain speaks only {@see LeagueState} and the derived snapshot array;
 * which engine stores them is an implementation detail behind this interface (ADR-02), so swapping
 * SQLite for Postgres is a binding change, not a domain change. The snapshot is persisted beside
 * the state as the precomputed read model (ADR-01) — written on change, served verbatim on read.
 */
interface LeagueRepository
{
    /** @param array<string, mixed> $snapshot */
    public function create(LeagueState $state, array $snapshot): void;

    /** @param array<string, mixed> $snapshot */
    public function save(LeagueState $state, array $snapshot): void;

    public function find(string $id): ?LeagueState;

    /** @return array<string, mixed>|null the stored read model, served without recomputation */
    public function snapshot(string $id): ?array;

    public function leagueIdForMatch(string $matchId): ?string;
}
