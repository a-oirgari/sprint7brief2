<?php

namespace App\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;

class CreateAccountRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'type'          => 'required|in:COURANT,EPARGNE,MINEUR',
            'interest_rate' => 'nullable|numeric|min:0|max:1',
            'guardian_id'   => 'nullable|exists:users,id',
        ];
    }

    public function messages(): array
    {
        return [
            'type.required' => 'Le type de compte est obligatoire.',
            'type.in'       => 'Le type doit être COURANT, EPARGNE ou MINEUR.',
            'guardian_id.exists' => 'Le tuteur spécifié n\'existe pas.',
        ];
    }
}