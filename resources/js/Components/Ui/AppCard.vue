<script setup>
import { computed } from 'vue';

const props = defineProps({
    as: {
        type: String,
        default: 'div',
    },
    shadow: {
        type: Boolean,
        default: false,
    },
    padded: {
        type: Boolean,
        default: true,
    },
    tone: {
        type: String,
        default: 'soft',
    },
});

const TONES = {
    soft: 'bg-surface-soft',
    surface: 'bg-surface',
    muted: 'bg-surface-muted',
};

const classes = computed(() => [
    'rounded-card border-2 border-line',
    TONES[props.tone] ?? TONES.soft,
    props.shadow ? 'shadow-retro' : '',
    props.padded ? 'p-4 sm:p-5' : '',
]);
</script>

<template>
    <component
        :is="as"
        :class="classes"
    >
        <slot />
    </component>
</template>
