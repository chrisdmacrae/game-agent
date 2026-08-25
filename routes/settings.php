<?php

use App\Http\Controllers\Settings\EmailChangeController;
use App\Http\Controllers\Settings\ProfileController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Confirming a new email address. Signed out on purpose: the link is opened
// wherever the new inbox is, and the token is the proof. As with sign-in
// links, the GET renders and the POST consumes.
Route::get('settings/email/confirm/{token}', [EmailChangeController::class, 'show'])
    ->name('profile.email.confirm');

Route::post('settings/email/confirm/{token}', [EmailChangeController::class, 'update'])
    ->middleware('throttle:6,1')
    ->name('profile.email.confirm.store');
