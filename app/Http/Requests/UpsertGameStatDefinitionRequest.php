<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpsertGameStatDefinitionRequest extends FormRequest
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
            'key' => [$this->isMethod('post') ? 'required' : 'sometimes', 'string', 'max:120'],
            'label' => [$this->isMethod('post') ? 'required' : 'sometimes', 'string', 'max:255'],
            'value_type' => [$this->isMethod('post') ? 'required' : 'sometimes', 'in:int,decimal,text,rank'],
            'unit' => [$this->isMethod('post') ? 'nullable' : 'sometimes', 'nullable', 'string', 'max:50'],
            'is_seasonal' => [$this->isMethod('post') ? 'nullable' : 'sometimes', 'nullable', 'boolean'],
        ];
    }
}
