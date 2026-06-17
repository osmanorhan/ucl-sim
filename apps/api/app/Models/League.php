<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class League extends Model
{
    use HasUuids;

    protected $fillable = ['id', 'name', 'seed', 'version', 'snapshot'];

    protected $casts = [
        'seed' => 'integer',
        'version' => 'integer',
        'snapshot' => 'array',
    ];

    /** @return HasMany<Team, $this> */
    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }

    /** @return HasMany<MatchRecord, $this> */
    public function matches(): HasMany
    {
        return $this->hasMany(MatchRecord::class);
    }
}
