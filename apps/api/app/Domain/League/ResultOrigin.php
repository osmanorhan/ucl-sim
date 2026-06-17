<?php

declare(strict_types=1);

namespace App\Domain\League;

/**
 * Where a match result came from: rolled by the simulation, or set by hand through an edit. Backed
 * because it is persisted; a new origin (imported, forfeit, …) is a new case, not a schema change.
 */
enum ResultOrigin: string
{
    case Simulated = 'simulated';
    case Manual = 'manual';
}
