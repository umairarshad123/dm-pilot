<?php

/*
 | Automations: welcome message, keyword rules, "test a message", welcome screen & ice breakers.
 | Loaded inside the admin group (prefix /admin, names admin.*).
 */

use App\Http\Controllers\Admin\AutomationController;
use App\Http\Controllers\Admin\AutomationToolsController;
use App\Http\Controllers\Admin\MessengerProfileController;
use Illuminate\Support\Facades\Route;

Route::prefix('automations')->name('automations.')->group(function (): void {
    Route::get('/', [AutomationController::class, 'index'])->name('index');
    Route::post('rules', [AutomationController::class, 'store'])->name('store');
    Route::post('rules/reorder', [AutomationController::class, 'reorder'])->name('reorder');
    Route::put('rules/{rule}', [AutomationController::class, 'update'])->name('update');
    Route::delete('rules/{rule}', [AutomationController::class, 'destroy'])->name('destroy');
    Route::post('rules/{rule}/duplicate', [AutomationController::class, 'duplicate'])->name('duplicate');
    Route::patch('rules/{rule}/toggle', [AutomationController::class, 'toggle'])->name('toggle');
    Route::put('welcome', [AutomationController::class, 'saveWelcome'])->name('welcome');

    Route::get('messenger-profile/{metaAccount}', [MessengerProfileController::class, 'show'])->name('profile.show');
    Route::put('messenger-profile/{metaAccount}', [MessengerProfileController::class, 'update'])->name('profile.update');

    Route::prefix('api')->name('api.')->group(function (): void {
        Route::post('regex', [AutomationToolsController::class, 'regex'])->name('regex');
        Route::post('preview', [AutomationToolsController::class, 'preview'])->name('preview');
        Route::post('test', [AutomationToolsController::class, 'test'])->name('test');
    });
});
