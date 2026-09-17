<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

defineProps({
    title: {
        type: String,
        default: '',
    },
});

const cartCount = computed(() => usePage().props.cartCount ?? 0);
</script>

<template>
    <div class="min-h-screen flex flex-col bg-gray-50 text-gray-900">
        <header class="bg-white border-b border-gray-200">
            <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-4 sm:px-6">
                <Link href="/" class="text-lg font-bold text-gray-900">
                    نظام طلبات الجملة
                </Link>
                <nav class="flex items-center gap-4">
                    <Link
                        href="/catalog"
                        class="text-base font-semibold text-gray-700 hover:text-gray-900"
                    >
                        المتجر
                    </Link>
                    <Link
                        href="/cart"
                        class="text-base font-semibold text-gray-700 hover:text-gray-900"
                    >
                        الطلب
                        <span
                            v-if="cartCount > 0"
                            class="ms-1 rounded-full bg-gray-900 px-2 py-0.5 text-xs text-white"
                        >
                            {{ cartCount }}
                        </span>
                    </Link>
                </nav>
            </div>
        </header>

        <main class="flex-1">
            <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6">
                <slot />
            </div>
        </main>

        <footer class="bg-white border-t border-gray-200">
            <div class="mx-auto max-w-6xl px-4 py-4 sm:px-6">
                <p class="text-sm text-gray-500 text-start">
                    نظام طلبات الجملة &copy; {{ new Date().getFullYear() }}
                </p>
            </div>
        </footer>
    </div>
</template>
