<?php

use Laravel\Fortify\Features;

return [
    'guard' => 'web',
    'middleware' => ['web'],
    'auth_middleware' => 'auth',
    'passwords' => 'users',
    'username' => 'email',
    'email' => 'email',
    'lowercase_usernames' => true,
    'home' => '/eventi',
    'prefix' => '',
    'domain' => null,
    'views' => false,

    'limiters' => [
        'login' => 'login',
        'passkeys' => null,
    ],

    'passkeys' => [
        'relying_party_id' => parse_url(config('app.url'), PHP_URL_HOST),
        'allowed_origins' => [config('app.url')],
        'user_handle_secret' => env('PASSKEYS_USER_HANDLE_SECRET', config('app.key')),
        'timeout' => 60000,
    ],

    'features' => [
        Features::resetPasswords(),
        Features::updateProfileInformation(),
        Features::updatePasswords(),
    ],
];
