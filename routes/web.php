<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Middleware\EnsureUserIsAdmin;
use Illuminate\Support\Facades\Route;

// Public home page = the About page (Meta domain verification scrapes the home page <head>).
Route::get('/', [\App\Http\Controllers\Public\LegalPageController::class, 'about'])->name('home');

Route::middleware('guest')->group(function (): void {
    Route::get('login', [LoginController::class, 'show'])->name('login');
    Route::post('login', [LoginController::class, 'store'])->name('login.store');
});

Route::post('logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

/*
 | Admin area: /admin/*, route names admin.*, session auth + is_admin.
 | Each file below owns one area of the product (one owner per file).
 */
Route::middleware(['auth', EnsureUserIsAdmin::class])->prefix('admin')->name('admin.')->group(function (): void {
    require __DIR__.'/admin/dashboard.php';
    require __DIR__.'/admin/inbox.php';
    require __DIR__.'/admin/contacts.php';
    require __DIR__.'/admin/bot.php';
    require __DIR__.'/admin/automations.php';
    require __DIR__.'/admin/pages.php';
    require __DIR__.'/admin/system.php';
});
