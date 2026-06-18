<?php

use App\Application\Exceptions\LeagueNotFound;
use App\Application\Exceptions\SeasonComplete;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

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

        $exceptions->dontReport([
            LeagueNotFound::class,
            SeasonComplete::class,
        ]);

        $exceptions->report(function (Throwable $e): void {
            $request = request();

            Log::error($e->getMessage(), [
                'exception' => $e,
                'method' => $request->method(),
                'path' => $request->path(),
            ]);
        })->stop();

        $exceptions->render(fn (LeagueNotFound $e) => response()->json(['message' => $e->getMessage()], 404));
        $exceptions->render(fn (SeasonComplete $e) => response()->json(['message' => $e->getMessage()], 409));

        $exceptions->render(function (Throwable $e, Request $request): ?JsonResponse {
            if (! $request->is('api/*') || $e instanceof ValidationException) {
                return null;
            }

            if ($e instanceof HttpExceptionInterface) {
                return response()->json(
                    ['message' => $e->getMessage() !== '' ? $e->getMessage() : 'Request failed.'],
                    $e->getStatusCode(),
                );
            }

            return response()->json(['message' => 'An unexpected error occurred. Please try again later.'], 500);
        });
    })->create();
