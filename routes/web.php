<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\NoteHeartController;
use Laravel\Fortify\Features;
use Livewire\Volt\Volt;
use App\Http\Controllers\NoteHeartController;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('profile.edit');
    Volt::route('settings/password', 'settings.password')->name('user-password.edit');
    Volt::route('settings/appearance', 'settings.appearance')->name('appearance.edit');

    Volt::route('settings/two-factor', 'settings.two-factor')
        ->middleware(
            when(
                Features::canManageTwoFactorAuthentication()
                    && Features::optionEnabled(Features::twoFactorAuthentication(), 'confirmPassword'),
                ['password.confirm'],
                [],
            ),
        )
        ->name('two-factor.show');

    // Notes UI (Livewire)
    Route\:get('/notes', \App\Livewire\Notes\Index::class)
        ->name('notes.index');
});

// Public endpoint for note hearts via token in email
Route::get('/h/{token}', NoteHeartController::class)->name('notes.heart');
// Public endpoint for note hearts via token in email
Route::get('/h/{token}', NoteHeartController::class)->name('notes.heart');
