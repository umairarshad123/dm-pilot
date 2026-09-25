<?php

/*
 | Live Chat / inbox: the 3-pane page (list, thread, contact) + the JSON API it polls.
 | Loaded inside the admin group (prefix /admin, names admin.*).
 |
 | Page:  GET conversations (list), GET conversations/{id} (deep link: same page with that conversation open).
 | Forms: the POST routes below redirect back (no-JS fallback); the page itself uses the JSON API.
 */

use App\Http\Controllers\Admin\Api\ConversationApiController;
use App\Http\Controllers\Admin\ConversationController;
use App\Http\Controllers\Admin\InboxContactController;
use Illuminate\Support\Facades\Route;

Route::get('conversations', [ConversationController::class, 'index'])->name('conversations.index');
Route::get('conversations/{conversation}', [ConversationController::class, 'show'])->name('conversations.show');
Route::post('conversations/{conversation}/reply', [ConversationController::class, 'reply'])->name('conversations.reply');
Route::post('conversations/{conversation}/bot', [ConversationController::class, 'bot'])->name('conversations.bot');
Route::post('conversations/{conversation}/takeover', [ConversationController::class, 'takeover'])->name('conversations.takeover');
Route::post('conversations/{conversation}/clear-pause', [ConversationController::class, 'clearPause'])->name('conversations.clear-pause');
Route::post('conversations/{conversation}/status', [ConversationController::class, 'status'])->name('conversations.status');

// JSON API (same session auth + admin check; CSRF applies to POST/PATCH)
Route::prefix('api')->name('api.')->group(function (): void {
    Route::get('conversations', [ConversationApiController::class, 'index'])->name('conversations.index');
    Route::get('conversations/{conversation}', [ConversationApiController::class, 'show'])->name('conversations.show');
    Route::get('conversations/{conversation}/messages', [ConversationApiController::class, 'messages'])->name('conversations.messages');
    Route::post('conversations/{conversation}/reply', [ConversationApiController::class, 'reply'])->name('conversations.reply');
    Route::post('conversations/{conversation}/read', [ConversationApiController::class, 'read'])->name('conversations.read');
    Route::post('conversations/{conversation}/bot', [ConversationApiController::class, 'bot'])->name('conversations.bot');
    Route::post('conversations/{conversation}/takeover', [ConversationApiController::class, 'takeover'])->name('conversations.takeover');
    Route::post('conversations/{conversation}/clear-pause', [ConversationApiController::class, 'clearPause'])->name('conversations.clear-pause');
    Route::post('conversations/{conversation}/status', [ConversationApiController::class, 'status'])->name('conversations.status');
    Route::patch('conversations/{conversation}/contact', [InboxContactController::class, 'update'])->name('conversations.contact');
    Route::post('conversations/{conversation}/refresh-profile', [InboxContactController::class, 'refreshProfile'])->name('conversations.refresh-profile');
});
