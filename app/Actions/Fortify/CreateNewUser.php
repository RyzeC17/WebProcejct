<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Spatie\Permission\Models\Role;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            'name' => ['nullable', 'string', 'max:255'],
            'first_name' => ['nullable', 'string', 'max:150'],
            'last_name' => ['nullable', 'string', 'max:150'],
            'username' => [
                'nullable',
                'string',
                'max:150',
                Rule::unique('utenti', 'nome_utente'),
            ],
            'email' => [
                'required',
                'string',
                'email',
                'max:254',
                Rule::unique('utenti', 'email'),
            ],
            'password' => $this->passwordRules(),
        ])->validate();

        [$firstName, $lastName] = $this->nameParts($input);

        $user = User::query()->create([
            'nome_utente' => $this->uniqueUsername($input),
            'nome' => $firstName,
            'cognome' => $lastName,
            'email' => Str::lower($input['email']),
            'password' => Hash::make($input['password']),
            'attivo' => true,
            'data_iscrizione' => now(),
        ]);

        $user->assignRole(Role::query()->firstOrCreate([
            'name' => 'user',
            'guard_name' => 'web',
        ]));

        return $user;
    }

    /**
     * @param  array<string, string>  $input
     * @return array{0: string, 1: string}
     */
    private function nameParts(array $input): array
    {
        $firstName = trim((string) ($input['first_name'] ?? ''));
        $lastName = trim((string) ($input['last_name'] ?? ''));

        if ($firstName === '' && $lastName === '') {
            $parts = preg_split('/\s+/', trim((string) ($input['name'] ?? '')), 2);
            $firstName = $parts[0] ?? '';
            $lastName = $parts[1] ?? '';
        }

        return [$firstName, $lastName];
    }

    /**
     * @param  array<string, string>  $input
     */
    private function uniqueUsername(array $input): string
    {
        $base = trim((string) ($input['username'] ?? ''));

        if ($base === '') {
            $base = Str::before((string) $input['email'], '@');
        }

        $base = preg_replace('/[^A-Za-z0-9_.-]+/', '_', $base) ?: 'user';
        $base = Str::lower(trim($base, '_.-')) ?: 'user';
        $base = Str::limit($base, 140, '');
        $candidate = $base;
        $suffix = 1;

        while (User::query()->where('nome_utente', $candidate)->exists()) {
            $tail = '_'.$suffix++;
            $candidate = Str::limit($base, 150 - strlen($tail), '').$tail;
        }

        return $candidate;
    }
}
