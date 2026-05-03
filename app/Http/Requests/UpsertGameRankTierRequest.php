<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpsertGameRankTierRequest extends FormRequest
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
            'game_platform_id' => [$this->isMethod('post') ? 'nullable' : 'sometimes', 'nullable', 'exists:game_platform,id'],
            'code' => [$this->isMethod('post') ? 'required' : 'sometimes', 'string', 'max:100'],
            'label' => [$this->isMethod('post') ? 'required' : 'sometimes', 'string', 'max:100'],
            'order_index' => [$this->isMethod('post') ? 'nullable' : 'sometimes', 'nullable', 'integer', 'min:0'],
        ];
    }
}
