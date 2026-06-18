<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Team\PowerRating;
use App\Rules\PlainText;
use Illuminate\Foundation\Http\FormRequest;
use LogicException;

class CreateLeagueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', new PlainText],
            'seed' => ['required', 'integer'],
            'teams' => ['required', 'array', 'size:4'],
            'teams.*.id' => ['required', 'string', 'max:64', 'distinct', 'alpha_dash:ascii'],
            'teams.*.name' => ['required', 'string', 'max:255', new PlainText],
            'teams.*.power' => ['required', 'numeric', 'gt:0', 'max:'.PowerRating::MAX],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'teams.size' => 'A Champions League group has exactly four teams.',
        ];
    }

    public function leagueName(): string
    {
        return $this->string('name')->toString();
    }

    public function seed(): int
    {
        return $this->integer('seed');
    }

    /**
     * Validation has already guaranteed the shape; the guards below narrow the validated mixed back
     * to concrete types for the typed domain boundary, and fail loudly if that contract is ever
     * broken upstream.
     *
     * @return array<int, array{id: string, name: string, power: float}>
     */
    public function teams(): array
    {
        $teams = $this->validated('teams');

        return array_map(
            fn (mixed $team): array => [
                'id' => $this->asString($team, 'id'),
                'name' => $this->asString($team, 'name'),
                'power' => $this->asFloat($team, 'power'),
            ],
            is_array($teams) ? array_values($teams) : [],
        );
    }

    private function asString(mixed $team, string $key): string
    {
        $value = is_array($team) ? ($team[$key] ?? null) : null;

        return is_string($value) ? $value : throw new LogicException("Validated team {$key} was not a string.");
    }

    private function asFloat(mixed $team, string $key): float
    {
        $value = is_array($team) ? ($team[$key] ?? null) : null;

        if (is_int($value) || is_float($value) || (is_string($value) && is_numeric($value))) {
            return (float) $value;
        }

        throw new LogicException("Validated team {$key} was not numeric.");
    }
}
