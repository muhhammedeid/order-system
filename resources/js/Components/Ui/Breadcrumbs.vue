<script setup>
import { Link } from '@inertiajs/vue3';
import AppIcon from '@/Components/Ui/AppIcon.vue';

defineProps({
    items: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <nav aria-label="مسار التنقل">
        <ol class="flex flex-wrap items-center gap-1.5 text-sm text-ink-muted">
            <li
                v-for="(item, index) in items"
                :key="`${item.label}-${index}`"
                class="flex items-center gap-1.5"
            >
                <AppIcon
                    v-if="index > 0"
                    name="chevron-left"
                    :size="14"
                    class="text-line-strong/40"
                />

                <Link
                    v-if="item.href && index < items.length - 1"
                    :href="item.href"
                    class="rounded font-semibold transition-colors hover:text-primary"
                >
                    {{ item.label }}
                </Link>
                <span
                    v-else
                    class="font-semibold text-ink"
                    aria-current="page"
                >
                    {{ item.label }}
                </span>
            </li>
        </ol>
    </nav>
</template>
