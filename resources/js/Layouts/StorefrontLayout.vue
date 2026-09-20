<script setup>
import AppHeader from '@/Components/AppHeader.vue';
import AppFooter from '@/Components/AppFooter.vue';
import ToastHost from '@/Components/Ui/ToastHost.vue';

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
    <div class="flex min-h-screen flex-col bg-surface text-ink">
        <a
            href="#main"
            class="sr-only focus:not-sr-only focus:absolute focus:start-4 focus:top-4 focus:z-50 focus:rounded-control focus:border-2 focus:border-line-strong focus:bg-surface-soft focus:px-4 focus:py-3 focus:font-semibold"
        >
            تخطَّ إلى المحتوى
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
