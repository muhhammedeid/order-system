<?php

namespace App\Support\WhatsApp\Templates;

use App\Filament\Resources\OrderManagement\OrderManagementResource;
use App\Models\Customer;
use App\Models\Order;
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
        $this->assertKnownTokens($template->body);

        if (WhatsAppTemplateVariables::usesOrderContext($template->body)) {
            throw WhatsAppTemplateException::missingOrderContext();
        }

        if ($product === null && WhatsAppTemplateVariables::usesProductContext($template->body)) {
            throw WhatsAppTemplateException::missingProductContext();
        }

        $replacements = [
            '{{'.WhatsAppTemplateVariables::CUSTOMER_NAME.'}}' => $this->sanitizeLine((string) $customer->name),
            '{{'.WhatsAppTemplateVariables::BUSINESS_NAME.'}}' => $this->sanitizeLine($this->businessName()),
        ];

        if ($product !== null) {
            $replacements['{{'.WhatsAppTemplateVariables::PRODUCT_NAME.'}}'] = $this->sanitizeLine((string) $product->name);
            $replacements['{{'.WhatsAppTemplateVariables::PRODUCT_CODE.'}}'] = $this->sanitizeLine((string) $product->product_code);
            $replacements['{{'.WhatsAppTemplateVariables::PRODUCT_URL.'}}'] = route('product.show', $product);
        }

        $rendered = $this->substitute($template->body, $replacements);
        if (mb_strlen($rendered) > 4096) {
            throw new WhatsAppTemplateException('Rendered WhatsApp message exceeds 4096 characters.');
        }

        return $rendered;
    }

    /**
     * Automatic operational order update. Item details come exclusively from
     * the accepted order snapshots; prices, notes and production aggregates
     * are never available to the template.
     */
    public function renderForOrder(WhatsAppTemplate $template, Order $order): string
    {
        $ownerOnly = array_intersect(WhatsAppTemplateVariables::tokens($template->body), [
            WhatsAppTemplateVariables::CUSTOMER_PHONE,
            WhatsAppTemplateVariables::ADMIN_ORDER_URL,
        ]);
        if ($ownerOnly !== []) {
            throw WhatsAppTemplateException::unknownTokens(array_values($ownerOnly));
        }

        return $this->renderOrder($template, $order, []);
    }

    public function renderForOwner(WhatsAppTemplate $template, Order $order): string
    {
        $order->loadMissing('customer');
        $ownerValues = [
            '{{customer_phone}}' => $this->sanitizeLine((string) $order->customer?->phone),
            '{{admin_order_url}}' => OrderManagementResource::getUrl('view', ['record' => $order], panel: 'admin'),
        ];
        $body = $this->renderOrder($template, $order, $ownerValues);
        $required = [
            'order_number' => 'رقم الطلب: '.$this->sanitizeLine((string) $order->order_number),
            'customer_name' => 'العميل: '.$this->sanitizeLine((string) $order->customer?->name),
            'customer_phone' => 'هاتف العميل: '.$ownerValues['{{customer_phone}}'],
            'total_quantity' => 'إجمالي القطع: '.number_format((int) $order->total_quantity),
            'admin_order_url' => 'رابط الإدارة: '.$ownerValues['{{admin_order_url}}'],
        ];
        $tokens = WhatsAppTemplateVariables::tokens($template->body);
        foreach ($required as $token => $line) {
            if (! in_array($token, $tokens, true)) {
                $body .= "\n".$line;
            }
        }

        return $body;
    }

    private function renderOrder(WhatsAppTemplate $template, Order $order, array $ownerReplacements): string
    {
        $this->assertKnownTokens($template->body);

        if (WhatsAppTemplateVariables::usesProductContext($template->body)) {
            throw WhatsAppTemplateException::missingProductContext();
        }

        $order->loadMissing(['customer', 'items']);

        $delivered = (int) $order->items->sum('delivered_quantity');
        $total = (int) $order->total_quantity;

        $replacements = [
            '{{'.WhatsAppTemplateVariables::CUSTOMER_NAME.'}}' => $this->sanitizeLine((string) ($order->customer?->name ?? '')),
            '{{'.WhatsAppTemplateVariables::BUSINESS_NAME.'}}' => $this->sanitizeLine($this->businessName()),
            '{{'.WhatsAppTemplateVariables::ORDER_NUMBER.'}}' => $this->sanitizeLine((string) $order->order_number),
            '{{'.WhatsAppTemplateVariables::ORDER_STATUS.'}}' => $this->sanitizeLine($this->statusLabel($order)),
            '{{'.WhatsAppTemplateVariables::ORDER_ITEMS.'}}' => $this->sanitize($this->orderItems($order)),
            '{{'.WhatsAppTemplateVariables::TOTAL_QUANTITY.'}}' => number_format($total),
            '{{'.WhatsAppTemplateVariables::DELIVERED_QUANTITY.'}}' => number_format($delivered),
            '{{'.WhatsAppTemplateVariables::REMAINING_QUANTITY.'}}' => number_format(max(0, $total - $delivered)),
        ];

        return $this->substitute($template->body, array_merge($replacements, $ownerReplacements));
    }

    /**
     * One bullet line per ordered variant, from the immutable snapshots only.
     */
    private function orderItems(Order $order): string
    {
        $lines = [];

        foreach ($order->items as $item) {
            $parts = [$this->sanitizeLine((string) $item->product_name).' ('.$this->sanitizeLine((string) $item->product_code).')'];
            $variant = trim(implode(' / ', array_filter(
                [$this->sanitizeLine((string) $item->color), $this->sanitizeLine((string) $item->size)],
                fn (string $value): bool => $value !== '',
            )));

            if ($variant !== '') {
                $parts[] = $variant;
            }

            $parts[] = '× '.(int) $item->quantity;
            $lines[] = '• '.implode(' — ', $parts);
        }

        return implode("\n", $lines);
    }

    /**
     * Customer-facing status label, resolved in the dedicated WhatsApp order
     * locale so an admin session locale never changes the customer text.
     */
    private function statusLabel(Order $order): string
    {
        if ($order->status === null) {
            return '';
        }

        return (string) __(
            "domain.order_status.{$order->status->value}",
            [],
            (string) config('whatsapp.order_locale', 'ar'),
        );
    }

    private function assertKnownTokens(string $body): void
    {
        $unknown = WhatsAppTemplateVariables::unknownTokens($body);

        if ($unknown !== []) {
            throw WhatsAppTemplateException::unknownTokens($unknown);
        }
    }

    /**
     * @param  array<string, string>  $replacements
     */
    private function substitute(string $body, array $replacements): string
    {
        return strtr(WhatsAppTemplateVariables::canonicalize($body), $replacements);
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

    /**
     * Scalar values (names, codes, statuses) are collapsed to one safe line:
     * line breaks, tabs and Unicode format/bidi controls can never break the
     * message layout or spoof text in an automatic send.
     */
    private function sanitizeLine(string $value): string
    {
        $stripped = preg_replace('/[\x00-\x1F\x7F]|\p{Cf}/u', ' ', $value);
        $collapsed = preg_replace('/\s+/u', ' ', $stripped ?? $value);

        return trim($collapsed ?? $value);
    }
}
