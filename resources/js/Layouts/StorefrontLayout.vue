<script setup>
import AppHeader from '@/Components/AppHeader.vue';
import AppFooter from '@/Components/AppFooter.vue';
import ToastHost from '@/Components/Ui/ToastHost.vue';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();

defineProps({
    title: {
        type: String,
        default: '',
    },
    full: {
        type: Boolean,
        default: false,
    },
});
</script>

<template>
    <div class="flex min-h-[100dvh] flex-col bg-page text-ink">
        <a
            href="#main"
            class="sr-only focus:not-sr-only focus:absolute focus:start-4 focus:top-4 focus:z-[500] focus:rounded-control focus:border border-line-strong focus:bg-surface focus:px-4 focus:py-3 focus:font-semibold focus:shadow-soft"
        >
            {{ t('nav.skip') }}
        </a>

        <AppHeader />

        <main
            id="main"
            class="flex-1"
        >
            <template v-if="full">
                <slot />
            </template>
            <div
                v-else
                class="mx-auto w-full max-w-7xl px-4 py-6 sm:px-6 sm:py-8 lg:px-8"
            >
                <slot />
            </div>
        </main>

        <AppFooter />
        <ToastHost />
    </div>
</template>
