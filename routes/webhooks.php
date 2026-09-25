<?php

use App\Http\Controllers\MetaWebhookController;
use App\Http\Middleware\VerifyMetaSignature;
use Illuminate\Support\Facades\Route;

/*
| Meta (Messenger / Instagram) webhooks. Loaded from bootstrap/app.php WITHOUT the `web`
| middleware group: no session, cookies or CSRF — requests are authenticated by HMAC signature.
*/

Route::prefix('webhooks')->group(function (): void {
    Route::get('meta', [MetaWebhookController::class, 'verify'])->name('meta.webhook.verify');

    Route::post('meta', [MetaWebhookController::class, 'receive'])
        ->middleware(VerifyMetaSignature::class)
        ->name('meta.webhook.receive');
});
