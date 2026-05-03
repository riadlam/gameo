<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertGameRequest extends FormRequest
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
        $gameId = $this->route('game')?->id ?? $this->route('game');

        return [
            'name' => [
                $this->isMethod('post') ? 'required' : 'sometimes',
                'string',
                'max:255',
                Rule::unique('games', 'name')->ignore($gameId),
            ],
            'image' => [$this->isMethod('post') ? 'nullable' : 'sometimes', 'nullable', 'string', 'max:2048'],
        ];
    }
}
