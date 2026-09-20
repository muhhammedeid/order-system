<script setup>
import { computed } from 'vue';
import AppIcon from '@/Components/Ui/AppIcon.vue';
import { CURRENCY_LABEL, formatNumber, formatQuantity } from '@/Utils/format';

const props = defineProps({
    items: {
        type: Array,
        default: () => [],
    },
    totalQuantity: {
        type: Number,
        default: 0,
    },
    totalPrice: {
        type: [String, Number],
        default: null,
    },
});

const hasRequestPrice = computed(() => props.items.some((item) => ! item.unit_price));
</script>

<template>
    <div class="flex flex-col gap-4">
        <ul class="flex flex-col gap-3">
            <li
                v-for="item in items"
                :key="item.line_id"
                class="flex flex-wrap items-start justify-between gap-2 text-sm"
            >
                <span class="min-w-0 flex-1">
                    <span class="font-semibold text-ink">{{ item.product.name }}</span>
                    <span class="block text-xs text-ink-muted">
                        <template v-if="item.color">{{ item.color }}</template>
                        <template v-if="item.size"><template v-if="item.color"> / </template><span class="tabular-nums">{{ item.size }}</span></template>
                        <span class="ms-1">× <span class="tabular-nums">{{ formatQuantity(item.quantity) }}</span></span>
                    </span>
                </span>

                <span
                    v-if="item.line_total"
                    class="font-semibold tabular-nums text-ink"
                >
                    {{ formatNumber(item.line_total) }}
                </span>
                <span
                    v-else
                    class="text-xs font-semibold text-accent"
                >
                    عند الطلب
                </span>
            </li>
        </ul>

        <dl class="flex flex-col gap-2 border-t-2 border-line pt-3">
            <div class="flex items-center justify-between text-sm">
                <dt class="text-ink-muted">إجمالي الكميات الشاملة</dt>
                <dd class="font-bold tabular-nums text-ink">
                    {{ formatQuantity(totalQuantity) }}
                </dd>
            </div>

            <div
                v-if="totalPrice"
                class="flex items-center justify-between"
            >
                <dt class="font-semibold text-ink">الإجمالي</dt>
                <dd class="font-display text-xl font-bold tabular-nums text-ink">
                    {{ formatNumber(totalPrice) }}
                    <span class="font-sans text-sm font-semibold text-ink-muted">{{ CURRENCY_LABEL }}</span>
                </dd>
            </div>
        </dl>

        <p
            v-if="hasRequestPrice"
            class="flex items-start gap-2 rounded-control border-2 border-line bg-surface px-3 py-2.5 text-xs text-ink-muted"
        >
            <AppIcon
                name="info"
                :size="16"
                class="mt-0.5"
            />
            بعض الأصناف بأسعار عند الطلب — سيتم تحديد سعرها والتواصل معك لتأكيد الطلب.
        </p>
    </div>
</template>
