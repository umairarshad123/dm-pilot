<?php

/*
 | Contacts (CRM): everyone who messaged a Page. List + board views, profile drawer, bulk actions, CSV export.
 | Loaded inside the admin group (prefix /admin, names admin.*).
 */

use App\Http\Controllers\Admin\ContactBulkController;
use App\Http\Controllers\Admin\ContactController;
use App\Http\Controllers\Admin\ContactExportController;
use Illuminate\Support\Facades\Route;

Route::get('contacts', [ContactController::class, 'index'])->name('contacts.index');
Route::match(['get', 'post'], 'contacts/export', ContactExportController::class)->name('contacts.export');
Route::post('contacts/bulk', ContactBulkController::class)->name('contacts.bulk');

Route::whereNumber('conversation')->group(function (): void {
    Route::get('contacts/{conversation}', [ContactController::class, 'show'])->name('contacts.show');
    Route::patch('contacts/{conversation}', [ContactController::class, 'update'])->name('contacts.update');
    Route::post('contacts/{conversation}/refresh', [ContactController::class, 'refresh'])->name('contacts.refresh');
    Route::delete('contacts/{conversation}', [ContactController::class, 'destroy'])->name('contacts.destroy');
});
