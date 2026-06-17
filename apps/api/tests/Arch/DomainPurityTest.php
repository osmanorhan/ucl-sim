<?php

declare(strict_types=1);

const DOMAIN_NAMESPACE = 'App\Domain';

arch('the domain layer is framework-agnostic')
    ->expect(DOMAIN_NAMESPACE)
    ->not->toUse(['Illuminate', 'Laravel'])
    ->group('arch');

arch('the domain layer does not depend on outer layers')
    ->expect(DOMAIN_NAMESPACE)
    ->not->toUse(['App\Models', 'App\Http', 'App\Application', 'App\Infrastructure', 'App\Providers'])
    ->group('arch');

arch('the domain layer declares strict types')
    ->expect(DOMAIN_NAMESPACE)
    ->toUseStrictTypes()
    ->group('arch');
