<script setup>
import { computed } from 'vue';

const props = defineProps({
    tone: {
        type: String,
        default: 'neutral',
    },
    size: {
        type: String,
        default: 'md',
    },
    tilt: {
        type: Boolean,
        default: false,
    },
});

const TONES = {
    neutral: 'bg-surface-muted text-ink border-line',
    primary: 'bg-primary text-on-primary border-line-strong',
    'primary-soft': 'bg-primary-soft text-primary-strong border-primary/30',
    powder: 'bg-powder text-powder-ink border-line-strong',
    'powder-soft': 'bg-accent-soft text-accent border-powder/40',
    success: 'bg-success-soft text-success border-success/30',
    warning: 'bg-warning-soft text-warning border-warning/30',
    danger: 'bg-danger-soft text-danger border-danger/30',
};

const SIZES = {
    sm: 'px-2 py-0.5 text-[0.7rem]',
    md: 'px-2.5 py-1 text-xs',
};

const classes = computed(() => [
    'inline-flex max-w-full items-center gap-1 whitespace-nowrap rounded-full border font-semibold',
    TONES[props.tone] ?? TONES.neutral,
    SIZES[props.size] ?? SIZES.md,
    props.tilt ? '-rotate-2' : '',
]);
</script>

<template>
    <span :class="classes">
        <slot />
    </span>
</template>
