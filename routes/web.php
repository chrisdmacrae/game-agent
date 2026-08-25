<?php

use App\Http\Controllers\Auth\LoginLinkController;
use App\Http\Controllers\BuildController;
use App\Http\Controllers\GameHubController;
use App\Http\Controllers\GameVoteController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MyBuildsController;
use App\Http\Controllers\OgImageController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Static routes
|--------------------------------------------------------------------------
|
| Everything with a fixed first segment has to be declared before the
| `/{game}` wildcard at the bottom of this file, or the game hub swallows it
| and answers 404 for an unknown slug.
|
*/

Route::get('/', HomeController::class)->name('home');
Route::get('og.png', OgImageController::class)->name('og-image');

// The pre-namespaced build URLs. `builds/{publicId}` now redirects to the
// canonical `/{game}/build/{publicId}`; the export endpoints stay put.
Route::get('builds/{publicId}', [BuildController::class, 'legacyShow'])->name('builds.show');
Route::get('builds/{publicId}/og.png', [BuildController::class, 'ogImage'])->name('builds.og-image');
Route::get('builds/{publicId}/pob', [BuildController::class, 'pob'])->name('builds.pob');
Route::get('builds/{publicId}/build-file', [BuildController::class, 'buildFile'])->name('builds.build-file');

Route::middleware('guest')->group(function () {
    Route::post('login-link', [LoginLinkController::class, 'store'])
        ->middleware('throttle:login-link')
        ->name('login-link.store');

    Route::get('login/sent', [LoginLinkController::class, 'sent'])->name('login.sent');

    // The GET only renders; the page posts to consume. Mail scanners fetch
    // emailed links, and a single-use token must survive that.
    Route::get('login/verify/{token}', [LoginLinkController::class, 'verify'])->name('login.verify');

    Route::post('login/verify/{token}', [LoginLinkController::class, 'consume'])
        ->middleware('throttle:login-link')
        ->name('login.verify.store');
});

Route::middleware(['auth'])->group(function () {
    Route::get('my-builds', MyBuildsController::class)->name('my-builds');

    // There is no separate dashboard (scope §2); the name survives so
    // anything still pointing at it lands on the right page.
    Route::redirect('dashboard', '/my-builds')->name('dashboard');
});

require __DIR__.'/settings.php';

/*
|--------------------------------------------------------------------------
| Game routes
|--------------------------------------------------------------------------
|
| Declared last: `{game:slug}` matches any single segment, and an unknown
| slug 404s through implicit binding.
|
*/

Route::prefix('{game:slug}')->name('games.')->group(function () {
    Route::get('/', GameHubController::class)->name('show');

    Route::post('vote', [GameVoteController::class, 'store'])
        ->middleware('throttle:game-vote')
        ->name('vote');

    Route::prefix('build/{publicId}')->name('builds.')->group(function () {
        Route::get('/', [BuildController::class, 'show'])->name('show');

        Route::middleware('auth')->group(function () {
            Route::get('edit', [BuildController::class, 'edit'])->name('edit');
            Route::patch('/', [BuildController::class, 'update'])->name('update');

            Route::post('endorse', [BuildController::class, 'endorse'])->name('endorse');
            Route::delete('endorse', [BuildController::class, 'unendorse'])->name('endorse.destroy');
            Route::post('bookmark', [BuildController::class, 'bookmark'])->name('bookmark');
            Route::delete('bookmark', [BuildController::class, 'unbookmark'])->name('bookmark.destroy');
        });
    });
});
