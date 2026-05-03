<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpsertUserGameStatRequest extends FormRequest
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
            'user_id' => ['sometimes', 'exists:users,id'],
            'game_id' => [$this->isMethod('post') ? 'required' : 'sometimes', 'exists:games,id'],
            'stat_definition_id' => [$this->isMethod('post') ? 'required' : 'sometimes', 'exists:game_stat_definitions,id'],
            'season' => [$this->isMethod('post') ? 'nullable' : 'sometimes', 'nullable', 'string', 'max:50'],
            'value_int' => [$this->isMethod('post') ? 'nullable' : 'sometimes', 'nullable', 'integer'],
            'value_decimal' => [$this->isMethod('post') ? 'nullable' : 'sometimes', 'nullable', 'numeric'],
            'value_text' => [$this->isMethod('post') ? 'nullable' : 'sometimes', 'nullable', 'string'],
            'value_rank_tier_id' => [$this->isMethod('post') ? 'nullable' : 'sometimes', 'nullable', 'exists:game_rank_tiers,id'],
        ];
    }
}
