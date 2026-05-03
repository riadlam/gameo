<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpsertUserGameRequest extends FormRequest
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
            'skill_level' => [$this->isMethod('post') ? 'required' : 'sometimes', 'integer', 'between:1,5'],
            'play_time_hours' => [$this->isMethod('post') ? 'nullable' : 'sometimes', 'nullable', 'integer', 'min:0'],
        ];
    }
}
