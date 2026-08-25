<?php

use App\Http\Controllers\Auth\LoginLinkController;
use App\Http\Controllers\BuildController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\OgImageController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('og.png', OgImageController::class)->name('og-image');

Route::get('builds/{publicId}', [BuildController::class, 'show'])->name('builds.show');
Route::get('builds/{publicId}/og.png', [BuildController::class, 'ogImage'])->name('builds.og-image');
Route::get('builds/{publicId}/pob', [BuildController::class, 'pob'])->name('builds.pob');
Route::get('builds/{publicId}/build-file', [BuildController::class, 'buildFile'])->name('builds.build-file');

Route::middleware('guest')->group(function () {
    Route::post('login-link', [LoginLinkController::class, 'store'])
        ->middleware('throttle:login-link')
        ->name('login-link.store');

    Route::get('login-link/{token}', [LoginLinkController::class, 'consume'])
        ->middleware('throttle:login-link')
        ->name('login-link.consume');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
});

require __DIR__.'/settings.php';
