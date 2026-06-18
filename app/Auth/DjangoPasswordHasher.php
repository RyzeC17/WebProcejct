<?php

namespace App\Auth;

use Illuminate\Support\Facades\Hash;

class DjangoPasswordHasher
{
    public function check(string $plain, string $stored): bool
    {
        if ($this->looksLikeLaravelHash($stored)) {
            return Hash::check($plain, $stored);
        }

        if (str_starts_with($stored, 'pbkdf2_sha256$') || str_starts_with($stored, 'pbkdf2_sha1$')) {
            return $this->checkPbkdf2($plain, $stored);
        }

        return false;
    }

    public function needsRehash(string $stored): bool
    {
        return ! $this->looksLikeLaravelHash($stored) || Hash::needsRehash($stored);
    }

    private function looksLikeLaravelHash(string $stored): bool
    {
        return str_starts_with($stored, '$2y$')
            || str_starts_with($stored, '$2a$')
            || str_starts_with($stored, '$argon2i$')
            || str_starts_with($stored, '$argon2id$');
    }

    private function checkPbkdf2(string $plain, string $stored): bool
    {
        $parts = explode('$', $stored);
        if (count($parts) !== 4) {
            return false;
        }

        [$algorithm, $iterations, $salt, $expected] = $parts;
        $hashAlgorithm = $algorithm === 'pbkdf2_sha1' ? 'sha1' : 'sha256';
        $binaryLength = strlen((string) base64_decode($expected, true));
        if ($binaryLength <= 0) {
            return false;
        }

        $computed = base64_encode(hash_pbkdf2($hashAlgorithm, $plain, $salt, (int) $iterations, $binaryLength, true));

        return hash_equals($expected, $computed);
    }
}
