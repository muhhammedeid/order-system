<script setup>
import { computed } from 'vue';
import AppBadge from '@/Components/Ui/AppBadge.vue';
import { CURRENCY_LABEL, formatNumber } from '@/Utils/format';

const props = defineProps({
    value: {
        type: [Number, String],
        default: null,
    },
    visibility: {
        type: String,
        default: 'public',
    },
    size: {
        type: String,
        default: 'md',
    },
});

const SIZES = {
    sm: 'text-base',
    md: 'text-2xl',
    lg: 'text-3xl sm:text-4xl',
};

const isPublic = computed(() => props.visibility === 'public' && props.value !== null);
</script>

<template>
    <p
        v-if="isPublic"
        class="flex flex-wrap items-baseline gap-1.5 font-display font-bold text-ink"
        :class="SIZES[size] ?? SIZES.md"
    >
        <span class="tabular-nums">{{ formatNumber(value) }}</span>
        <span class="font-sans text-sm font-semibold text-ink-muted">{{ CURRENCY_LABEL }}</span>
    </p>

    <AppBadge
        v-else
        tone="powder-soft"
        size="md"
    >
        السعر عند الطلب
    </AppBadge>
</template>
