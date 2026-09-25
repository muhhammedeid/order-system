<?php

namespace App\Enums;

enum WhatsAppTemplateType: string
{
    case Marketing = 'marketing';
    case General = 'general';
    case Order = 'order';

    public function label(): string
    {
        return __("admin.whatsapp.templates.types.{$this->value}");
    }
}
