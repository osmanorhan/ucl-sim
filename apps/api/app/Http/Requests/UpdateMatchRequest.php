<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\League\MatchResult;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'homeGoals' => ['required', 'integer', 'min:0', 'max:'.MatchResult::MAX_GOALS],
            'awayGoals' => ['required', 'integer', 'min:0', 'max:'.MatchResult::MAX_GOALS],
        ];
    }

    public function homeGoals(): int
    {
        return $this->integer('homeGoals');
    }

    public function awayGoals(): int
    {
        return $this->integer('awayGoals');
    }
}
