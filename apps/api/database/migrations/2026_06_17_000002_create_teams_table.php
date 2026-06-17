<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teams', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('league_id')->constrained()->cascadeOnDelete();
            $table->string('team_id');
            $table->string('name');
            $table->float('power');
            $table->timestamps();

            $table->unique(['league_id', 'team_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teams');
    }
};
