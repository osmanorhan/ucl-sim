<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\League\ResultOrigin;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MatchRecord extends Model
{
    use HasUuids;

    protected $table = 'matches';

    protected $fillable = [
        'id', 'league_id', 'sequence', 'week', 'home_team_id', 'away_team_id', 'home_goals', 'away_goals', 'origin',
    ];

    protected $casts = [
        'sequence' => 'integer',
        'week' => 'integer',
        'home_goals' => 'integer',
        'away_goals' => 'integer',
        'origin' => ResultOrigin::class,
    ];
}
