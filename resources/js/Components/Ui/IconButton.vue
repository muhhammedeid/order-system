<script setup>
import { computed } from 'vue';
import AppIcon from '@/Components/Ui/AppIcon.vue';

const props = defineProps({
    icon: {
        type: String,
        required: true,
    },
    label: {
        type: String,
        required: true,
    },
    variant: {
        type: String,
        default: 'secondary',
    },
    size: {
        type: String,
        default: 'md',
    },
    disabled: {
        type: Boolean,
        default: false,
    },
});

const VARIANTS = {
    primary:
        'bg-primary text-on-primary hover:bg-primary-strong',
    secondary:
        'border-2 border-line-strong bg-surface text-ink hover:bg-surface-soft',
    ghost:
        'bg-transparent text-ink hover:bg-surface-muted',
    danger:
        'bg-transparent text-danger hover:bg-danger-soft',
};

const SIZES = {
    sm: 'h-11 w-11',
    md: 'h-12 w-12',
};

const classes = computed(() => [
    'inline-flex items-center justify-center rounded-control transition-[transform,background-color,color,border-color] duration-200 ease-out disabled:cursor-not-allowed disabled:opacity-50',
    VARIANTS[props.variant] ?? VARIANTS.secondary,
    SIZES[props.size] ?? SIZES.md,
]);
</script>

<template>
    <button
        type="button"
        :class="classes"
        :disabled="disabled"
        :title="label"
        :aria-label="label"
    >
        <AppIcon
            :name="icon"
            :size="size === 'sm' ? 18 : 20"
        />
    </button>
</template>
