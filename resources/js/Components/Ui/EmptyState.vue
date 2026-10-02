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
    <div class="flex flex-col items-center gap-3 rounded-card border border-dashed border-line-strong/60 px-6 py-14 text-center">
        <span class="flex h-16 w-16 items-center justify-center rounded-full bg-stopped-soft text-stopped">
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
