<script setup>
import AppIcon from '@/Components/Ui/AppIcon.vue';

defineProps({
    icon: {
        type: String,
        default: 'inbox',
    },
    title: {
        type: String,
        required: true,
    },
    description: {
        type: String,
        default: null,
    },
});
</script>

<template>
    <div class="flex flex-col items-center gap-3 rounded-card border-2 border-dashed border-line px-6 py-14 text-center">
        <span class="flex h-16 w-16 items-center justify-center rounded-full border-2 border-line bg-surface-muted text-ink-muted">
            <AppIcon
                :name="icon"
                :size="30"
            />
        </span>

        <h2 class="font-display text-xl font-bold text-ink">
            {{ title }}
        </h2>

        <p
            v-if="description"
            class="max-w-md text-sm text-ink-muted"
        >
            {{ description }}
        </p>

        <div
            v-if="$slots.default"
            class="mt-2 flex flex-wrap items-center justify-center gap-3"
        >
            <slot />
        </div>
    </div>
</template>
