<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Human-readable free text: letters (any script), digits, combining marks, whitespace and a small
 * set of name punctuation. Everything else — angle brackets, slashes, control characters, the raw
 * material of HTML and markup injection — is rejected at the boundary.
 */
class PlainText implements ValidationRule
{
    private const ALLOWED = "/^[\\p{L}\\p{N}\\p{M}\\s.,'&()\\-]+$/u";

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || preg_match(self::ALLOWED, $value) !== 1) {
            $fail('The :attribute may only contain letters, numbers, spaces and basic punctuation.');
        }
    }
}
