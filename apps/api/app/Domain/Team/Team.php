<?php

declare(strict_types=1);

namespace App\Domain\Team;

final readonly class Team
{
    public function __construct(
        public string $id,
        public string $name,
        public PowerRating $power,
    ) {}
}
