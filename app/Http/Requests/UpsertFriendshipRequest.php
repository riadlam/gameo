<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpsertFriendshipRequest extends FormRequest
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
            'user_one_id' => ['sometimes', 'exists:users,id'],
            'user_two_id' => [$this->isMethod('post') ? 'required' : 'sometimes', 'different:user_one_id', 'exists:users,id'],
            'status' => [$this->isMethod('post') ? 'required' : 'sometimes', 'in:pending,accepted,blocked'],
        ];
    }
}
