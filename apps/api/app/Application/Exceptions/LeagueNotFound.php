<?php

declare(strict_types=1);

namespace App\Application\Exceptions;

use RuntimeException;

final class LeagueNotFound extends RuntimeException
{
    public static function id(string $id): self
    {
        return new self("No league found for id {$id}.");
    }
}
