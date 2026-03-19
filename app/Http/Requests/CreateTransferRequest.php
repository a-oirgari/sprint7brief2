<?php

namespace App\Http\Requests\Transfer;

use Illuminate\Foundation\Http\FormRequest;

class CreateTransferRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'from_account_id' => 'required|exists:accounts,id',
            'to_account_id'   => 'required|exists:accounts,id|different:from_account_id',
            'amount'          => 'required|numeric|min:0.01',
            'description'     => 'nullable|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'from_account_id.required'    => 'Le compte source est obligatoire.',
            'from_account_id.exists'      => 'Le compte source n\'existe pas.',
            'to_account_id.required'      => 'Le compte destinataire est obligatoire.',
            'to_account_id.exists'        => 'Le compte destinataire n\'existe pas.',
            'to_account_id.different'     => 'Le virement vers le même compte est interdit.',
            'amount.required'             => 'Le montant est obligatoire.',
            'amount.min'                  => 'Le montant minimum est de 0.01 MAD.',
        ];
    }
}