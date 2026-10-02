<?php

use App\Http\Controllers\WhatsAppWebhookController;
use App\Http\Middleware\VerifyWhatsAppWebhookSignature;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| WhatsApp Webhook Routes (P08-W01)
|--------------------------------------------------------------------------
|
| This file is registered without the `web` middleware group, so it is
| stateless: no session, no CSRF token. Requests must carry a valid WAHA
| HMAC signature and are rate limited.
|
*/

Route::post(config('whatsapp.webhook.path'), WhatsAppWebhookController::class)
    ->middleware([VerifyWhatsAppWebhookSignature::class, 'throttle:60,1'])
    ->name('whatsapp.webhook');
