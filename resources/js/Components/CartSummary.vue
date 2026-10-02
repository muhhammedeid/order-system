<script setup>
import { computed } from 'vue';
import AppIcon from '@/Components/Ui/AppIcon.vue';
import { formatNumber, formatQuantity } from '@/Utils/format';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();

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
                        <span class="ms-1">
                            {{ t('cart.line_summary', {
                                quantity: formatQuantity(item.quantity),
                                colors: formatQuantity(item.color_count),
                                pieces: formatQuantity(item.pieces_quantity),
                            }) }}
                        </span>
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
                    class="text-xs font-semibold text-attention"
                >
                    {{ t('common.on_request') }}
                </span>
            </li>
        </ul>

        <dl class="flex flex-col gap-2 border-t border-line pt-3">
            <div class="flex items-center justify-between text-sm">
                <dt class="text-ink-muted">{{ t('common.total_pieces') }}</dt>
                <dd class="font-bold tabular-nums text-ink">
                    {{ formatQuantity(totalQuantity) }}
                </dd>
            </div>

            <div
                v-if="totalPrice"
                class="flex items-center justify-between"
            >
                <dt class="font-semibold text-ink">{{ t('common.total') }}</dt>
                <dd class="font-display text-xl font-bold tabular-nums text-ink">
                    {{ formatNumber(totalPrice) }}
                    <span class="font-sans text-sm font-semibold text-ink-muted">{{ t('common.currency') }}</span>
                </dd>
            </div>
        </dl>

        <p
            v-if="hasRequestPrice"
            class="flex items-start gap-2 rounded-control border border-line bg-surface px-3 py-2.5 text-xs text-ink-muted"
        >
            <AppIcon
                name="info"
                :size="16"
                class="mt-0.5"
            />
            {{ t('cart.request_price_note') }}
        </p>
    </div>
</template>
