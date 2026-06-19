<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ! $this->user();
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:150'],
            'last_name' => ['required', 'string', 'max:150'],
            'username' => ['required', 'string', 'max:150', 'unique:utenti,nome_utente'],
            'email' => ['required', 'email', 'max:254', 'unique:utenti,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ];
    }
}
