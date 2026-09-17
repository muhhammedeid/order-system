<script setup>
defineProps({
    title: {
        type: String,
        default: '',
    },
});
</script>

<template>
    <div class="min-h-screen flex flex-col bg-gray-50 text-gray-900">
        <header class="bg-white border-b border-gray-200">
            <div class="mx-auto max-w-6xl px-4 py-4 sm:px-6">
                <h1 class="text-lg font-bold text-gray-900">
                    نظام طلبات الجملة
                </h1>
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
