<?php

namespace Database\Seeders;

use App\Enums\WhatsAppTemplateType;
use App\Models\WhatsAppTemplate;
use App\Support\WhatsApp\Order\OrderStatusNotifier;
use Illuminate\Database\Seeder;

/**
 * Operational order-update templates. Idempotent: an existing keyed template
 * is never overwritten, so admin edits survive re-seeding.
 */
class WhatsAppOrderTemplatesSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->templates() as $key => $template) {
            WhatsAppTemplate::query()->firstOrCreate(
                ['key' => $key],
                $template,
            );
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function templates(): array
    {
        return [
            OrderStatusNotifier::PLACED_CUSTOMER => [
                'name' => 'Order Placed - Customer',
                'type' => WhatsAppTemplateType::Order,
                'active' => true,
                'body' => implode("\n", [
                    'مرحباً {{customer_name}}،',
                    'تم استلام طلبك رقم {{order_number}} بنجاح.',
                    '',
                    'تفاصيل الطلب:',
                    '{{order_items}}',
                    '',
                    'إجمالي القطع: {{total_quantity}}',
                    'سيتواصل معك فريق العمليات لتأكيد الطلب. شكراً لتعاملك مع {{business_name}}.',
                ]),
            ],
            OrderStatusNotifier::PLACED_OWNER => [
                'name' => 'Order Placed - Owner',
                'type' => WhatsAppTemplateType::Order,
                'active' => true,
                'body' => implode("\n", [
                    'طلب جديد رقم {{order_number}} من {{customer_name}}.',
                    'إجمالي القطع: {{total_quantity}}',
                    '',
                    'تفاصيل الطلب:',
                    '{{order_items}}',
                ]),
            ],
            OrderStatusNotifier::CONFIRMED_CUSTOMER => [
                'name' => 'Order Confirmed - Customer',
                'type' => WhatsAppTemplateType::Order,
                'active' => true,
                'body' => implode("\n", [
                    'مرحباً {{customer_name}}،',
                    'تم تأكيد طلبك رقم {{order_number}} وبدأ تجهيزه. سنوافيك بالتحديثات.',
                ]),
            ],
            OrderStatusNotifier::PARTIALLY_DELIVERED_CUSTOMER => [
                'name' => 'Partial Delivery - Customer',
                'type' => WhatsAppTemplateType::Order,
                'active' => true,
                'body' => implode("\n", [
                    'مرحباً {{customer_name}}،',
                    'تم تسليم جزء من طلبك رقم {{order_number}}.',
                    'تم التسليم: {{delivered_quantity}} قطعة، والمتبقي: {{remaining_quantity}} قطعة.',
                ]),
            ],
            OrderStatusNotifier::DELIVERED_CUSTOMER => [
                'name' => 'Order Delivered - Customer',
                'type' => WhatsAppTemplateType::Order,
                'active' => true,
                'body' => implode("\n", [
                    'مرحباً {{customer_name}}،',
                    'تم تسليم طلبك رقم {{order_number}} بالكامل. شكراً لتعاملك معنا.',
                ]),
            ],
        ];
    }
}
