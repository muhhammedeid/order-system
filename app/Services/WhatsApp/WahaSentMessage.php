<?php

namespace App\Services\WhatsApp;

use App\Support\WhatsApp\Inbox\ProviderMessageId;
use App\Support\WhatsApp\SentMessage;

class WahaSentMessage
{
    public static function fromArray(array $response): SentMessage
    {
        $id = $response['id'] ?? $response['key']['id'] ?? $response['_data']['id']['id'] ?? '';
        $timestamp = $response['timestamp'] ?? $response['messageTimestamp'] ?? null;

        return new SentMessage(
            providerId: ProviderMessageId::normalize(is_string($id) ? $id : null) ?? '',
            timestamp: is_numeric($timestamp) ? (int) $timestamp : null,
        );
    }
}
