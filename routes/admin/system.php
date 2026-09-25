<?php

/*
 | System: settings hub, webhook event log, page switcher, UI style guide.
 | Loaded inside the admin group (prefix /admin, names admin.*).
 */

use App\Http\Controllers\Admin\PageSwitchController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\WebhookEventController;
use Illuminate\Support\Facades\Route;

// Settings hub (?tab=connection|ai|health|privacy|app-review)
Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');

// Webhook events (shown as the "Webhook events" tab of Settings)
Route::get('webhook-events', [WebhookEventController::class, 'index'])->name('webhook-events.index');
Route::get('webhook-events/{webhookEvent}', [WebhookEventController::class, 'show'])->name('webhook-events.show');

// Page context switcher (sidebar dropdown)
Route::post('page-switch', PageSwitchController::class)->name('page-switch');

// Component style guide for page builders
Route::view('_ui', 'admin._ui')->name('ui');
