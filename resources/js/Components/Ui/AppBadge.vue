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
};

const SIZES = {
    sm: 'px-2 py-0.5 text-[0.7rem]',
    md: 'px-2.5 py-1 text-xs',
};

const classes = computed(() => [
    'inline-flex max-w-full items-center gap-1 whitespace-nowrap rounded-full border font-semibold',
    TONES[props.tone] ?? TONES.neutral,
    SIZES[props.size] ?? SIZES.md,
]);
</script>

<template>
    <span :class="classes">
        <slot />
    </span>
</template>
