<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\EventManagementController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\NotificationController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::prefix('account')->name('accounts.')->group(function () {
    Route::get('/login/', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login/', [AuthController::class, 'login'])->name('login.store');
    Route::get('/register/', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register/', [AuthController::class, 'register'])->name('register.store');
    Route::post('/logout/', [AuthController::class, 'logout'])->name('logout');
});

Route::prefix('eventi')->name('events.')->group(function () {
    Route::get('/', [EventController::class, 'index'])->name('list');
    Route::get('/calendario/', [EventController::class, 'calendar'])->name('calendar');

    Route::middleware('auth')->group(function () {
        Route::get('/area-personale/adesioni/', [EventController::class, 'myRegistrations'])->name('my-registrations');
        Route::post('/adesioni/{registrationId}/aggiorna/', [EventController::class, 'updateRegistration'])->name('update-registration');
        Route::post('/adesioni/{registrationId}/annulla/', [EventController::class, 'cancelRegistration'])->name('cancel-registration');
        Route::post('/{slug}/iscrizione/', [EventController::class, 'registerToEvent'])->name('register');
        Route::post('/{slug}/feedback/', [EventController::class, 'submitFeedback'])->name('submit-feedback');
    });

    Route::middleware(['auth', 'staff'])->prefix('gestione/eventi')->group(function () {
        Route::get('/', [EventManagementController::class, 'index'])->name('manage-list');
        Route::get('/nuovo/', [EventManagementController::class, 'create'])->name('manage-create');
        Route::post('/nuovo/', [EventManagementController::class, 'store'])->name('manage-store');
        Route::get('/{eventId}/modifica/', [EventManagementController::class, 'edit'])->name('manage-update');
        Route::post('/{eventId}/modifica/', [EventManagementController::class, 'update'])->name('manage-update.store');
        Route::post('/{eventId}/elimina/', [EventManagementController::class, 'destroy'])->name('manage-delete');
        Route::post('/{eventId}/cambia-stato/', [EventManagementController::class, 'changeStatus'])->name('manage-status');
        Route::get('/{eventId}/iscritti/', [EventManagementController::class, 'registrants'])->name('manage-registrants');
        Route::get('/{eventId}/storico/', [EventManagementController::class, 'history'])->name('manage-history');
    });

    Route::get('/{slug}/', [EventController::class, 'detail'])->name('detail');
});

Route::middleware('auth')->prefix('notifiche')->name('notifications.')->group(function () {
    Route::get('/', [NotificationController::class, 'index'])->name('list');
    Route::get('/pannello/', [NotificationController::class, 'panel'])->name('panel')->withoutMiddleware('auth');
    Route::get('/summary/', [NotificationController::class, 'summary'])->name('summary')->withoutMiddleware('auth');
    Route::post('/{id}/leggi/', [NotificationController::class, 'markRead'])->name('mark-read');
    Route::post('/leggi-tutte/', [NotificationController::class, 'markAllRead'])->name('mark-all-read');
});

Route::get('/admin/', fn () => redirect()->route('events.manage-list'))->middleware(['auth', 'staff'])->name('admin.redirect');
