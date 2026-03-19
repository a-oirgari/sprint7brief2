<?php
namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'first_name'    => 'required|string|max:100',
            'last_name'     => 'required|string|max:100',
            'email'         => 'required|email|unique:users,email',
            'password'      => 'required|string|min:8|confirmed',
            'date_of_birth' => 'required|date|before:today',
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique'             => 'Cette adresse email est déjà utilisée.',
            'password.confirmed'       => 'Les mots de passe ne correspondent pas.',
            'date_of_birth.before'     => 'La date de naissance doit être dans le passé.',
        ];
    }
}