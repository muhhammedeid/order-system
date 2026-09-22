<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppIcon from '@/Components/Ui/AppIcon.vue';

const props = defineProps({
    variant: {
        type: String,
        default: 'primary',
    },
    size: {
        type: String,
        default: 'md',
    },
    href: {
        type: String,
        default: null,
    },
    method: {
        type: String,
        default: 'get',
    },
    type: {
        type: String,
        default: 'button',
    },
    loading: {
        type: Boolean,
        default: false,
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    block: {
        type: Boolean,
        default: false,
    },
    disableWhileLoading: {
        type: Boolean,
        default: true,
    },
    icon: {
        type: String,
        default: null,
    },
    iconPosition: {
        type: String,
        default: 'start',
    },
});

const VARIANTS = {
    primary: 'bg-primary text-on-primary hover:bg-primary-strong',
    secondary: 'border-2 border-line-strong bg-surface text-primary hover:bg-primary-soft',
    accent: 'border-2 border-primary/30 bg-primary-soft text-primary hover:bg-selected',
    whatsapp: 'bg-external text-on-external hover:brightness-105',
    ghost:
        'bg-transparent text-ink shadow-none hover:bg-surface-muted hover:shadow-none',
    danger: 'bg-danger text-on-danger hover:bg-danger-strong',

    /* Deprecated aliases kept until callers migrate to the semantic roles. */
    powder: 'border-2 border-primary/30 bg-primary-soft text-primary hover:bg-selected',
};

const SIZES = {
    sm: 'min-h-11 px-3.5 text-sm gap-1.5',
    md: 'min-h-12 px-5 text-base gap-2',
    lg: 'min-h-14 px-7 text-lg gap-2.5',
};

const classes = computed(() => [
    'inline-flex select-none items-center justify-center rounded-control font-semibold shadow-soft-sm transition-[transform,background-color,box-shadow,color,border-color] duration-200 ease-out hover:-translate-y-px hover:shadow-soft active:translate-y-0 active:shadow-soft-sm disabled:cursor-not-allowed disabled:opacity-50 disabled:shadow-none disabled:hover:translate-y-0',
    VARIANTS[props.variant] ?? VARIANTS.primary,
    SIZES[props.size] ?? SIZES.md,
    props.block ? 'w-full' : '',
]);

const isDisabled = computed(() => props.disabled || (props.loading && props.disableWhileLoading));
</script>

<template>
    <component
        :is="href ? Link : 'button'"
        :href="href"
        :method="href ? method : undefined"
        :type="href ? undefined : type"
        :disabled="! href && isDisabled ? true : undefined"
        :aria-disabled="isDisabled ? 'true' : undefined"
        :aria-busy="loading ? 'true' : undefined"
        :class="classes"
    >
        <AppIcon
            v-if="loading"
            name="spinner"
            :size="size === 'lg' ? 22 : 18"
            class="animate-spin"
        />
        <AppIcon
            v-else-if="icon && iconPosition === 'start'"
            :name="icon"
            :size="size === 'lg' ? 22 : 18"
        />

        <slot />

        <AppIcon
            v-if="! loading && icon && iconPosition === 'end'"
            :name="icon"
            :size="size === 'lg' ? 22 : 18"
        />
    </component>
</template>
