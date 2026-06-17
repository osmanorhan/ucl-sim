<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\LeagueService;
use App\Http\Requests\UpdateMatchRequest;
use Illuminate\Http\JsonResponse;

class MatchController extends Controller
{
    public function __construct(private readonly LeagueService $leagues) {}

    public function update(UpdateMatchRequest $request, string $id): JsonResponse
    {
        return response()->json($this->leagues->edit($id, $request->homeGoals(), $request->awayGoals()));
    }
}
