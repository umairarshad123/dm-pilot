<?php

use App\Http\Controllers\Public\LegalPageController;
use App\Http\Controllers\Public\MetaDataDeletionController;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;

/*
| Public pages (Privacy Policy, Terms, Data Deletion Instructions, About) and Meta's Data Deletion / Deauthorize
| callbacks. Loaded from bootstrap/app.php inside the `web` middleware group.
| The two POST callbacks are called server-to-server by Meta and authenticated by signed_request, so CSRF is
| disabled for those routes only.
*/

Route::get('privacy', [LegalPageController::class, 'privacy'])->name('public.privacy');
Route::get('terms', [LegalPageController::class, 'terms'])->name('public.terms');
Route::get('data-deletion', [LegalPageController::class, 'dataDeletion'])->name('public.data-deletion');
Route::get('about', [LegalPageController::class, 'about'])->name('public.about');

Route::prefix('meta')->name('meta.')->middleware('throttle:60,1')->group(function (): void {
    Route::post('data-deletion', [MetaDataDeletionController::class, 'callback'])
        ->withoutMiddleware([ValidateCsrfToken::class])
        ->name('data-deletion');

    Route::get('data-deletion/{code}', [MetaDataDeletionController::class, 'status'])
        ->where('code', '[A-Za-z0-9]{8,64}')
        ->name('data-deletion.status');

    Route::post('deauthorize', [MetaDataDeletionController::class, 'deauthorize'])
        ->withoutMiddleware([ValidateCsrfToken::class])
        ->name('deauthorize');
});
