<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MatchCandidatesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'game_id' => ['required', 'exists:games,id'],
            'platform_id' => ['nullable', 'integer', 'exists:platforms,id'],
            'game_platform_id' => ['required', 'integer', 'exists:game_platform,id'],
            'rank_required' => ['nullable', 'boolean'],
            'game_rank_tier_id' => ['nullable', 'integer', 'exists:game_rank_tiers,id', 'required_if:rank_required,1,true'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
