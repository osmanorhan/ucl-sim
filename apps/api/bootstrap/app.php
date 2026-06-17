<?php

use App\Application\Exceptions\LeagueNotFound;
use App\Application\Exceptions\SeasonComplete;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (): void {})
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->render(fn (LeagueNotFound $e) => response()->json(['message' => $e->getMessage()], 404));
        $exceptions->render(fn (SeasonComplete $e) => response()->json(['message' => $e->getMessage()], 409));
    })->create();
