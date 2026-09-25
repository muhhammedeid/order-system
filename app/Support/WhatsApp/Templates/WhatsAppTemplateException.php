<?php

namespace App\Support\WhatsApp\Templates;

use RuntimeException;

/**
 * Sanitized template failure. Messages contain only variable names or a
 * fixed reason; never template bodies or customer/product data.
 */
class WhatsAppTemplateException extends RuntimeException
{
    /**
     * @param  array<int, string>  $tokens
     */
    public static function unknownTokens(array $tokens): self
    {
        return new self(__('admin.whatsapp.templates.errors.unknown_tokens', [
            'tokens' => implode(', ', $tokens),
        ]));
    }

    public static function missingProductContext(): self
    {
        return new self(__('admin.whatsapp.templates.errors.missing_product'));
    }

    public static function missingOrderContext(): self
    {
        return new self(__('admin.whatsapp.templates.errors.missing_order'));
    }
}
