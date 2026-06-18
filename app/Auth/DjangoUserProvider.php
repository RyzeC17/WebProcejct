<?php

namespace App\Auth;

use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable as UserContract;
use Illuminate\Contracts\Hashing\Hasher as HasherContract;
use Illuminate\Support\Facades\Hash;

class DjangoUserProvider extends EloquentUserProvider
{
    public function __construct(HasherContract $hasher, string $model, private readonly DjangoPasswordHasher $djangoHasher)
    {
        parent::__construct($hasher, $model);
    }

    public function validateCredentials(UserContract $user, array $credentials): bool
    {
        $plain = $credentials['password'] ?? null;
        if (! is_string($plain) || $plain === '') {
            return false;
        }

        $stored = (string) $user->getAuthPassword();
        if (! $this->djangoHasher->check($plain, $stored)) {
            return false;
        }

        if ($this->djangoHasher->needsRehash($stored)) {
            $user->forceFill(['password' => Hash::make($plain)])->save();
        }

        return true;
    }
}
