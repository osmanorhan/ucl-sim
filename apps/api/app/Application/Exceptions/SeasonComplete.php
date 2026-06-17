<?php

declare(strict_types=1);

namespace App\Application\Exceptions;

use RuntimeException;

final class SeasonComplete extends RuntimeException
{
    public static function id(string $id): self
    {
        return new self("Season for league {$id} is already complete; nothing left to play.");
    }
}
