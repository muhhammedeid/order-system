<script setup>
import { computed } from 'vue';
import AppIcon from '@/Components/Ui/AppIcon.vue';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();

const props = defineProps({
    currentPage: {
        type: Number,
        required: true,
    },
    lastPage: {
        type: Number,
        required: true,
    },
});

const emit = defineEmits(['change']);

const pages = computed(() => {
    const window = 1;
    const list = new Set([1, props.lastPage]);

    for (let page = props.currentPage - window; page <= props.currentPage + window; page++) {
        if (page >= 1 && page <= props.lastPage) {
            list.add(page);
        }
    }

    const sorted = [...list].sort((a, b) => a - b);
    const result = [];

    sorted.forEach((page, index) => {
        if (index > 0 && page - sorted[index - 1] > 1) {
            result.push('gap');
        }

        result.push(page);
    });

    return result;
});
</script>

<template>
    <nav
        class="flex flex-wrap items-center justify-center gap-1.5"
        :aria-label="t('pagination.label')"
    >
        <button
            type="button"
            class="inline-flex h-11 items-center gap-1 rounded-control border-2 border-line bg-surface-soft px-3.5 font-semibold text-ink transition-colors hover:border-line-strong disabled:cursor-not-allowed disabled:opacity-40"
            :disabled="currentPage <= 1"
            :aria-label="t('pagination.previous_page')"
            @click="emit('change', currentPage - 1)"
        >
            <AppIcon
                name="chevron-right"
                :size="18"
            />
            {{ t('pagination.previous') }}
        </button>

        <template
            v-for="(page, index) in pages"
            :key="`${page}-${index}`"
        >
            <span
                v-if="page === 'gap'"
                class="px-1 text-ink-muted"
                aria-hidden="true"
            >…</span>
            <button
                v-else
                type="button"
                class="inline-flex h-11 min-w-11 items-center justify-center rounded-control border-2 px-3 font-semibold tabular-nums transition-colors"
                :class="page === currentPage
                    ? 'border-line-strong bg-primary text-on-primary shadow-retro-sm'
                    : 'border-line bg-surface-soft text-ink hover:border-line-strong'"
                :aria-current="page === currentPage ? 'page' : undefined"
                :aria-label="t('pagination.page', { number: page })"
                @click="emit('change', page)"
            >
                {{ page }}
            </button>
        </template>

        <button
            type="button"
            class="inline-flex h-11 items-center gap-1 rounded-control border-2 border-line bg-surface-soft px-3.5 font-semibold text-ink transition-colors hover:border-line-strong disabled:cursor-not-allowed disabled:opacity-40"
            :disabled="currentPage >= lastPage"
            :aria-label="t('pagination.next_page')"
            @click="emit('change', currentPage + 1)"
        >
            {{ t('pagination.next') }}
            <AppIcon
                name="chevron-left"
                :size="18"
            />
        </button>
    </nav>
</template>
