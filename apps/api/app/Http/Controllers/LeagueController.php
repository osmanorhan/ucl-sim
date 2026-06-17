<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\LeagueService;
use App\Application\StrategyEvaluator;
use App\Http\Requests\CreateLeagueRequest;
use Illuminate\Http\JsonResponse;

class LeagueController extends Controller
{
    public function __construct(private readonly LeagueService $leagues) {}

    public function store(CreateLeagueRequest $request): JsonResponse
    {
        return response()->json(
            $this->leagues->create($request->leagueName(), $request->teams(), $request->seed()),
            201,
        );
    }

    public function show(string $id): JsonResponse
    {
        return response()->json($this->leagues->snapshot($id));
    }

    public function table(string $id): JsonResponse
    {
        $snapshot = $this->leagues->snapshot($id);

        return response()->json(['version' => $snapshot['version'], 'table' => $snapshot['table']]);
    }

    public function fixtures(string $id): JsonResponse
    {
        $snapshot = $this->leagues->snapshot($id);

        return response()->json(['version' => $snapshot['version'], 'fixtures' => $snapshot['fixtures']]);
    }

    public function predictions(string $id): JsonResponse
    {
        $snapshot = $this->leagues->snapshot($id);

        abort_if($snapshot['predictions'] === null, 409, 'Predictions become available once four weeks are complete.');

        return response()->json(['version' => $snapshot['version'], 'predictions' => $snapshot['predictions']]);
    }

    public function playWeek(string $id): JsonResponse
    {
        return response()->json($this->leagues->playWeek($id));
    }

    public function playAll(string $id): JsonResponse
    {
        return response()->json($this->leagues->playAll($id));
    }

    public function evaluation(string $id, StrategyEvaluator $evaluator): JsonResponse
    {
        $scorecards = $evaluator->evaluate(
            $this->leagues->find($id),
            $this->configInt('league.evaluation.scenarios', 6),
            $this->configInt('league.evaluation.ground_truth_draws', 400),
        );

        return response()->json(['leagueId' => $id, 'scorecards' => $scorecards]);
    }

    private function configInt(string $key, int $default): int
    {
        $value = config($key);

        return is_int($value) ? $value : $default;
    }
}
