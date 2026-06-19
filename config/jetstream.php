<?php

use Laravel\Jetstream\Http\Middleware\AuthenticateSession;

return [
    'stack' => 'livewire',
    'middleware' => ['web'],
    'auth_session' => AuthenticateSession::class,
    'guard' => 'web',

    'features' => [],
];
