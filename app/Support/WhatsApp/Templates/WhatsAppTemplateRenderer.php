<?php

namespace App\Support\WhatsApp\Templates;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Setting;
use App\Models\WhatsAppTemplate;

/**
 * Minimal deterministic renderer: allowlisted plain-text substitution in a
 * single pass. No Blade/Twig/PHP/expression evaluation, and inserted values
 * are never re-scanned for tokens.
 */
class WhatsAppTemplateRenderer
{
    public function render(WhatsAppTemplate $template, Customer $customer, ?Product $product = null): string
    {
        $unknown = WhatsAppTemplateVariables::unknownTokens($template->body);

        if ($unknown !== []) {
            throw WhatsAppTemplateException::unknownTokens($unknown);
        }

        if ($product === null && WhatsAppTemplateVariables::usesProductContext($template->body)) {
            throw WhatsAppTemplateException::missingProductContext();
        }

        $replacements = [
            '{{'.WhatsAppTemplateVariables::CUSTOMER_NAME.'}}' => $this->sanitize((string) $customer->name),
            '{{'.WhatsAppTemplateVariables::BUSINESS_NAME.'}}' => $this->sanitize($this->businessName()),
        ];

        if ($product !== null) {
            $replacements['{{'.WhatsAppTemplateVariables::PRODUCT_NAME.'}}'] = $this->sanitize((string) $product->name);
            $replacements['{{'.WhatsAppTemplateVariables::PRODUCT_CODE.'}}'] = $this->sanitize((string) $product->product_code);
        }

        return strtr(WhatsAppTemplateVariables::canonicalize($template->body), $replacements);
    }

    private function businessName(): string
    {
        $configured = Setting::get('business_name');

        return filled($configured) ? (string) $configured : (string) config('app.name');
    }

    private function sanitize(string $value): string
    {
        $stripped = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);

        return trim($stripped ?? $value);
    }
}
