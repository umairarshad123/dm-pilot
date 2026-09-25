<?php

/*
 | Pages & Channels: connected Facebook Pages / Instagram accounts + the connect wizard.
 | Loaded inside the admin group (prefix /admin, names admin.*).
 */

use App\Http\Controllers\Admin\MetaAccountController;
use App\Http\Controllers\Admin\MetaConnectController;
use Illuminate\Support\Facades\Route;

// Connect wizard first so "connect" is not captured as an id.
Route::get('meta-accounts/connect', [MetaConnectController::class, 'create'])->name('meta-accounts.connect');
Route::post('meta-accounts/connect', [MetaConnectController::class, 'pages'])->name('meta-accounts.connect.pages');
Route::get('meta-accounts/connect/pages', [MetaConnectController::class, 'showPages'])->name('meta-accounts.connect.show-pages');
Route::post('meta-accounts/connect/save', [MetaConnectController::class, 'store'])->name('meta-accounts.connect.store');
Route::get('meta-accounts/connect/done', [MetaConnectController::class, 'done'])->name('meta-accounts.connect.done');

// "Continue with Facebook" (Facebook Login for Business). The callback URL must be listed in the Meta
// dashboard under Facebook Login (for Business) → Settings → Valid OAuth Redirect URIs.
Route::get('meta-accounts/connect/facebook', [MetaConnectController::class, 'redirectToFacebook'])->name('meta-accounts.oauth.redirect');
Route::get('meta-accounts/connect/facebook/callback', [MetaConnectController::class, 'callback'])->name('meta-accounts.oauth.callback');

Route::resource('meta-accounts', MetaAccountController::class)->except('show')->parameters(['meta-accounts' => 'metaAccount']);
Route::post('meta-accounts/{metaAccount}/toggle', [MetaAccountController::class, 'toggle'])->name('meta-accounts.toggle');
Route::post('meta-accounts/{metaAccount}/test', [MetaAccountController::class, 'test'])->name('meta-accounts.test');
Route::post('meta-accounts/{metaAccount}/subscribe', [MetaAccountController::class, 'subscribe'])->name('meta-accounts.subscribe');
Route::post('meta-accounts/{metaAccount}/open', [MetaAccountController::class, 'open'])->name('meta-accounts.open');
