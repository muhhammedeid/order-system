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
    primary: 'bg-primary-soft text-primary border-primary/25',
    success: 'bg-success-soft text-success border-success/25',
    attention: 'bg-attention-soft text-attention border-attention/25',
    progress: 'bg-progress-soft text-progress border-progress/25',
    stopped: 'bg-stopped-soft text-stopped border-stopped/25',
    danger: 'bg-danger-soft text-danger border-danger/25',
    external: 'bg-external-soft text-external border-external/25',
    data: 'bg-data-soft text-data border-data/25',

    /* Deprecated aliases kept until callers migrate to the semantic roles. */
    'primary-soft': 'bg-primary-soft text-primary border-primary/25',
    powder: 'bg-primary text-on-primary border-primary',
    'powder-soft': 'bg-attention-soft text-attention border-attention/25',
    warning: 'bg-attention-soft text-attention border-attention/25',
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
