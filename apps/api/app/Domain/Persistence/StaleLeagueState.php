<?php

declare(strict_types=1);

namespace App\Domain\Persistence;

use RuntimeException;

final class StaleLeagueState extends RuntimeException
{
    public static function id(string $id, int $expectedVersion): self
    {
        return new self("League {$id} changed before this write could be saved; expected version {$expectedVersion}.");
    }
}
