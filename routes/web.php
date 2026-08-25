<?php

use App\Http\Controllers\BuildController;
use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::get('builds/{publicId}', [BuildController::class, 'show'])->name('builds.show');
Route::get('builds/{publicId}/pob', [BuildController::class, 'pob'])->name('builds.pob');
Route::get('builds/{publicId}/build-file', [BuildController::class, 'buildFile'])->name('builds.build-file');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
