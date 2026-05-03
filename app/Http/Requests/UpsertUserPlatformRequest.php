<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpsertUserPlatformRequest extends FormRequest
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
            'platform_id' => [$this->isMethod('post') ? 'required' : 'sometimes', 'exists:platforms,id'],
            'game_platform_id' => ['sometimes', 'nullable', 'exists:game_platform,id'],
            'username_on_platform' => [$this->isMethod('post') ? 'nullable' : 'sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
