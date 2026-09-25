<?php

/*
 | Bot Studio: global bot settings + per-page overrides + "Test your bot" playground.
 | Loaded inside the admin group (prefix /admin, names admin.*).
 */

use App\Http\Controllers\Admin\BotSettingController;
use App\Http\Controllers\Admin\BotStudioPlaygroundController;
use Illuminate\Support\Facades\Route;

Route::get('bot-settings', [BotSettingController::class, 'edit'])->name('bot-settings.edit');
Route::put('bot-settings', [BotSettingController::class, 'update'])->name('bot-settings.update');
Route::post('bot-settings/playground', BotStudioPlaygroundController::class)
    ->middleware('throttle:30,1')
    ->name('bot-settings.playground');
Route::get('bot-settings/accounts/{metaAccount}', [BotSettingController::class, 'editAccount'])->name('bot-settings.account.edit');
Route::put('bot-settings/accounts/{metaAccount}', [BotSettingController::class, 'updateAccount'])->name('bot-settings.account.update');
Route::delete('bot-settings/accounts/{metaAccount}', [BotSettingController::class, 'destroyAccount'])->name('bot-settings.account.destroy');
