<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Team extends Model
{
    use HasUuids;

    protected $fillable = ['id', 'league_id', 'team_id', 'name', 'power'];

    protected $casts = [
        'power' => 'float',
    ];
}
