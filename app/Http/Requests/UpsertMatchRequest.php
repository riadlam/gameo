<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpsertMatchRequest extends FormRequest
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
            'target_user_id' => [$this->isMethod('post') ? 'required' : 'sometimes', 'different:user_id', 'exists:users,id'],
            'game_platform_id' => [$this->isMethod('post') ? 'required' : 'sometimes', 'integer', 'exists:game_platform,id'],
            'status' => [$this->isMethod('post') ? 'required' : 'sometimes', 'in:pending,liked,matched,rejected'],
        ];
    }
}
