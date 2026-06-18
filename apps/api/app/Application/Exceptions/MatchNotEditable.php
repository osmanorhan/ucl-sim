<?php

declare(strict_types=1);

namespace App\Application\Exceptions;

use RuntimeException;

final class MatchNotEditable extends RuntimeException
{
    public static function id(string $id): self
    {
        return new self("Match {$id} cannot be edited before it has been played.");
    }
}
