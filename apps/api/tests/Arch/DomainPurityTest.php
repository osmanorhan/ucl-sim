<?php

declare(strict_types=1);

arch('the domain layer is framework-agnostic')
    ->expect('App\Domain')
    ->not->toUse(['Illuminate', 'Laravel'])
    ->group('arch');

arch('the domain layer does not depend on outer layers')
    ->expect('App\Domain')
    ->not->toUse(['App\Models', 'App\Http', 'App\Application', 'App\Infrastructure', 'App\Providers'])
    ->group('arch');

arch('the domain layer declares strict types')
    ->expect('App\Domain')
    ->toUseStrictTypes()
    ->group('arch');
