<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpsertGamePlatformRequest extends FormRequest
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
            'game_id' => [$this->isMethod('post') ? 'required' : 'sometimes', 'exists:games,id'],
            'platform_id' => [$this->isMethod('post') ? 'required' : 'sometimes', 'exists:platforms,id'],
        ];
    }
}
