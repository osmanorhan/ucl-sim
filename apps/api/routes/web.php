<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

$spa = fn () => response()->file(public_path('index.html'));

Route::get('/', $spa);

Route::fallback(function () use ($spa) {
    abort_if(request()->is('api/*'), 404);

    return $spa();
});
