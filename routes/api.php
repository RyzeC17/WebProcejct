<?php

use App\Http\Controllers\Api\EventApiController;
use App\Http\Controllers\Api\RegistrationApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/events/', [EventApiController::class, 'index']);
    Route::get('/events/calendar/', [EventApiController::class, 'calendar']);
    Route::get('/events/{eventId}/', [EventApiController::class, 'show']);
    Route::get('/events/{eventId}/feedback/', [EventApiController::class, 'feedback']);

    Route::middleware(['auth', 'role:admin'])->group(function () {
        Route::post('/events/', [EventApiController::class, 'store']);
        Route::patch('/events/{eventId}/', [EventApiController::class, 'update']);
        Route::delete('/events/{eventId}/', [EventApiController::class, 'destroy']);
        Route::get('/events/{eventId}/history/', [EventApiController::class, 'history']);
    });

    Route::middleware('auth')->group(function () {
        Route::post('/events/{eventId}/registrations/', [RegistrationApiController::class, 'storeForEvent']);
        Route::post('/events/{eventId}/feedback/', [EventApiController::class, 'submitFeedback']);
        Route::get('/me/registrations/', [RegistrationApiController::class, 'mine']);
        Route::get('/me/registrations/{registrationId}/', [RegistrationApiController::class, 'showMine']);
        Route::patch('/me/registrations/{registrationId}/', [RegistrationApiController::class, 'updateMine']);
        Route::delete('/me/registrations/{registrationId}/', [RegistrationApiController::class, 'deleteMine']);
    });
});
