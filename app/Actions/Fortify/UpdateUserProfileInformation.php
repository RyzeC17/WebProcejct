<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;

class UpdateUserProfileInformation implements UpdatesUserProfileInformation
{
    /**
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function update(User $user, array $input): void
    {
        Validator::make($input, [
            'name' => ['nullable', 'string', 'max:255'],
            'first_name' => ['nullable', 'string', 'max:150'],
            'last_name' => ['nullable', 'string', 'max:150'],
            'username' => [
                'nullable',
                'string',
                'max:150',
                Rule::unique('utenti', 'nome_utente')->ignore($user->id),
            ],
            'email' => [
                'required',
                'string',
                'email',
                'max:254',
                Rule::unique('utenti', 'email')->ignore($user->id),
            ],
        ])->validateWithBag('updateProfileInformation');

        $updates = [
            'email' => Str::lower($input['email']),
        ];

        if (array_key_exists('username', $input) && trim((string) $input['username']) !== '') {
            $updates['nome_utente'] = trim((string) $input['username']);
        }

        if (array_key_exists('first_name', $input) || array_key_exists('last_name', $input)) {
            $updates['nome'] = trim((string) ($input['first_name'] ?? $user->nome));
            $updates['cognome'] = trim((string) ($input['last_name'] ?? $user->cognome));
        } elseif (array_key_exists('name', $input)) {
            $parts = preg_split('/\s+/', trim((string) $input['name']), 2);
            $updates['nome'] = $parts[0] ?? '';
            $updates['cognome'] = $parts[1] ?? '';
        }

        $user->forceFill($updates)->save();
    }
}
