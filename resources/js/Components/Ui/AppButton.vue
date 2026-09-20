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
    primary:
        'bg-primary text-on-primary border-2 border-line-strong shadow-retro-sm hover:bg-primary-strong hover:shadow-retro active:translate-y-[2px] active:shadow-none',
    secondary:
        'bg-surface-soft text-ink border-2 border-line-strong shadow-retro-sm hover:bg-surface-muted active:translate-y-[2px] active:shadow-none',
    powder:
        'bg-powder text-powder-ink border-2 border-line-strong shadow-retro-sm hover:brightness-105 active:translate-y-[2px] active:shadow-none',
    whatsapp:
        'bg-whatsapp text-white border-2 border-line-strong shadow-retro-sm hover:brightness-110 active:translate-y-[2px] active:shadow-none',
    ghost:
        'bg-transparent text-ink border-2 border-transparent hover:bg-surface-muted',
    danger:
        'bg-transparent text-danger border-2 border-danger hover:bg-danger-soft',
};

const SIZES = {
    sm: 'min-h-11 px-3.5 text-sm gap-1.5',
    md: 'min-h-12 px-5 text-base gap-2',
    lg: 'min-h-14 px-7 text-lg gap-2.5',
};

const classes = computed(() => [
    'inline-flex select-none items-center justify-center rounded-control font-semibold transition-[transform,background-color,box-shadow,filter] duration-150 disabled:cursor-not-allowed disabled:opacity-50 disabled:shadow-none',
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
