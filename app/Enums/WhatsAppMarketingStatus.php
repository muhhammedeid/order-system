<?php

namespace App\Enums;

enum WhatsAppMarketingStatus: string
{
    case Unknown = 'unknown';
    case Subscribed = 'subscribed';
    case Unsubscribed = 'unsubscribed';

    public function label(): string
    {
        return __("admin.whatsapp.marketing.statuses.{$this->value}");
    }
}
