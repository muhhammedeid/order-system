<script setup>
defineProps({
    href: {
        type: String,
        required: true,
    },
});
</script>

<template>
    <a
        :href="href"
        target="_blank"
        rel="noopener noreferrer"
        class="inline-flex items-center justify-center gap-2 rounded-lg bg-green-600 px-5 py-3 text-base font-bold text-white hover:bg-green-700"
    >
        معرفة السعر عبر WhatsApp
    </a>
</template>
