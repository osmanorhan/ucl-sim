<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('matches', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('league_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sequence');
            $table->unsignedInteger('week');
            $table->string('home_team_id');
            $table->string('away_team_id');
            $table->unsignedInteger('home_goals')->nullable();
            $table->unsignedInteger('away_goals')->nullable();
            $table->string('origin')->nullable();
            $table->timestamps();

            $table->unique(['league_id', 'sequence']);

            $table->foreign(['league_id', 'home_team_id'])
                ->references(['league_id', 'team_id'])->on('teams')
                ->cascadeOnDelete();
            $table->foreign(['league_id', 'away_team_id'])
                ->references(['league_id', 'team_id'])->on('teams')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matches');
    }
};
