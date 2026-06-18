<?php

declare(strict_types=1);

use App\Http\Controllers\LeagueController;
use App\Http\Controllers\MatchController;
use Illuminate\Support\Facades\Route;

Route::get('leagues/{id}', [LeagueController::class, 'show']);
Route::get('leagues/{id}/table', [LeagueController::class, 'table']);
Route::get('leagues/{id}/fixtures', [LeagueController::class, 'fixtures']);
Route::get('leagues/{id}/predictions', [LeagueController::class, 'predictions']);

Route::middleware('throttle:league-evaluation')
    ->get('leagues/{id}/evaluation', [LeagueController::class, 'evaluation']);

Route::middleware('throttle:league-mutations')->group(function (): void {
    Route::post('leagues', [LeagueController::class, 'store']);
    Route::post('leagues/{id}/play-week', [LeagueController::class, 'playWeek']);
    Route::post('leagues/{id}/play-all', [LeagueController::class, 'playAll']);
    Route::put('matches/{id}', [MatchController::class, 'update']);
});
