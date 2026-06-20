<?php

use App\Http\Controllers\SearchController;
use App\Http\Controllers\SipController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the application and will be assigned to the "api"
| middleware group. Make something great!
|
*/

// SIP Integration endpoints (called by Google Sheets Apps Script)
Route::prefix('sip')->group(function () {
    Route::get('/queue', [SipController::class, 'getQueue'])->name('api.sip.queue');
    Route::post('/update', [SipController::class, 'updateSip'])->name('api.sip.update');
});

// Search endpoints (require authentication)
// Use web middleware group to enable session-based authentication
Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/search', [SearchController::class, 'search'])->name('api.search');
    Route::get('/search/tournaments', [SearchController::class, 'tournaments'])->name('api.search.tournaments');
    Route::get('/search/users', [SearchController::class, 'users'])->name('api.search.users');
});
