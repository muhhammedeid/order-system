<?php

return [

    /*
    |--------------------------------------------------------------------------
    | WhatsApp Provider
    |--------------------------------------------------------------------------
    |
    | Phase 08 uses WAHA as the WhatsApp provider. The provider is accessed
    | only through App\Contracts\WhatsAppGateway so business logic never
    | depends on vendor-specific APIs. Only one provider is supported.
    |
    */

    'enabled' => (bool) env('WHATSAPP_ENABLED', false),

    'provider' => env('WHATSAPP_PROVIDER', 'waha'),

    /*
    |--------------------------------------------------------------------------
    | WAHA Connection
    |--------------------------------------------------------------------------
    |
    | The base URL is expected to be reachable over a private network. The
    | API key is sent as the X-Api-Key header and must never be logged.
    |
    */

    'base_url' => rtrim((string) env('WHATSAPP_BASE_URL', 'http://127.0.0.1:3000'), '/'),

    'api_key' => env('WHATSAPP_API_KEY'),

    'session' => env('WHATSAPP_SESSION', 'default'),

    /*
    |--------------------------------------------------------------------------
    | Customer-Facing Locale
    |--------------------------------------------------------------------------
    |
    | Locale used for customer-facing values such as the order status label in
    | automatic notifications. Kept separate from the session locale so an
    | admin working in English never changes the language of customer texts.
    |
    */

    'order_locale' => env('WHATSAPP_ORDER_LOCALE', 'ar'),

    'timeout' => (int) env('WHATSAPP_TIMEOUT', 10),

    'verify_ssl' => (bool) env('WHATSAPP_VERIFY_SSL', true),

    /*
    |--------------------------------------------------------------------------
    | Inbound Webhook
    |--------------------------------------------------------------------------
    |
    | WAHA signs the raw request body with HMAC (sha512). The secret is
    | required; when it is missing the endpoint fails closed.
    |
    */

    'webhook' => [
        'path' => env('WHATSAPP_WEBHOOK_PATH', 'webhooks/whatsapp'),
        'secret' => env('WHATSAPP_WEBHOOK_SECRET'),
        'tolerance' => (int) env('WHATSAPP_WEBHOOK_TOLERANCE', 300),
    ],

];
