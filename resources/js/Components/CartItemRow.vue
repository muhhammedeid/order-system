<script setup>
import { computed } from 'vue';
import AppIcon from '@/Components/Ui/AppIcon.vue';
import IconButton from '@/Components/Ui/IconButton.vue';
import QuantityStepper from '@/Components/Ui/QuantityStepper.vue';
import { formatNumber } from '@/Utils/format';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();

const props = defineProps({
    item: {
        type: Object,
        required: true,
    },
    error: {
        type: String,
        default: null,
    },
    busy: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['update-quantity', 'remove']);

const unitPriceLabel = computed(() =>
    props.item.unit_price ? `${formatNumber(props.item.unit_price)} ${t('common.currency')}` : null,
);
</script>

<template>
    <div class="rounded-card border border-line bg-surface p-3 shadow-soft-sm sm:p-4">
        <div class="flex gap-3">
            <a
                :href="`/product/${item.product.slug}`"
                class="h-20 w-20 shrink-0 overflow-hidden rounded-control border border-line bg-surface-muted sm:h-24 sm:w-24"
                tabindex="-1"
                aria-hidden="true"
            >
                <img
                    v-if="item.product.image"
                    :src="item.product.image"
                    :alt="item.product.name"
                    class="h-full w-full object-cover"
                    loading="lazy"
                    decoding="async"
                >
                <span
                    v-else
                    class="flex h-full w-full items-center justify-center text-ink-muted"
                >
                    <AppIcon
                        name="swatch"
                        :size="28"
                    />
                </span>
            </a>

            <div class="flex min-w-0 flex-1 flex-col gap-1">
                <div class="flex items-start justify-between gap-2">
                    <a
                        :href="`/product/${item.product.slug}`"
                        class="font-display text-base font-bold leading-snug text-ink transition-colors hover:text-primary sm:text-lg"
                    >
                        {{ item.product.name }}
                    </a>

                    <IconButton
                        icon="trash"
                        :label="t('cart.remove_item')"
                        variant="danger"
                        size="sm"
                        :disabled="busy"
                        @click="emit('remove', item)"
                    />
                </div>

                <p
                    class="font-mono text-xs text-ink-muted"
                    dir="ltr"
                >
                    {{ item.product.product_code }}
                </p>

                <p class="text-sm text-ink-muted">
                    <template v-if="item.color">
                        <span class="font-semibold text-ink">{{ item.color }}</span>
                    </template>
                    <template v-if="item.size">
                        <span
                            v-if="item.color"
                            aria-hidden="true"
                        > / </span>
                        <span class="font-semibold tabular-nums text-ink">{{ item.size }}</span>
                    </template>
                    <span
                        v-if="unitPriceLabel"
                        class="ms-2"
                    >
                        {{ t('cart.per_piece', { price: unitPriceLabel }) }}
                    </span>
                </p>

                <div class="mt-2 flex flex-wrap items-end justify-between gap-3">
                    <QuantityStepper
                        :model-value="item.quantity"
                        :label="t('common.quantity_per_color')"
                        :min="item.quantity_step"
                        :step="item.quantity_step"
                        :busy="busy"
                        :error="error"
                        @update:model-value="emit('update-quantity', item, $event)"
                    />

                    <p class="text-sm text-ink-muted">
                        {{ t('product.total_pieces') }}
                        <strong class="tabular-nums text-ink">{{ item.pieces_quantity }}</strong>
                        ({{ t('cart.pieces_formula', { quantity: item.quantity, count: item.color_count }) }})
                    </p>

                    <div
                        v-if="item.line_total"
                        class="text-end"
                    >
                        <p class="text-xs text-ink-muted">{{ t('common.total') }}</p>
                        <p class="font-display text-lg font-bold tabular-nums text-ink">
                            {{ formatNumber(item.line_total) }}
                            <span class="font-sans text-xs font-semibold text-ink-muted">{{ t('common.currency') }}</span>
                        </p>
                    </div>
                    <p
                        v-else
                        class="rounded-full border border-attention/25 bg-attention-soft px-2.5 py-1 text-xs font-semibold text-attention"
                    >
                        {{ t('common.request_price') }}
                    </p>
                </div>
            </div>
        </div>

        <p
            v-if="error"
            class="mt-2 rounded-control border border-danger/40 bg-danger-soft px-3 py-2 text-sm font-semibold text-danger"
            role="alert"
        >
            {{ error }}
        </p>
    </div>
</template>
