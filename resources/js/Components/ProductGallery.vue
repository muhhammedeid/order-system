<script setup>
import { ref } from 'vue';

const props = defineProps({
    images: {
        type: Array,
        required: true,
    },
    alt: {
        type: String,
        default: '',
    },
});

const mainIndex = ref(0);

function select(index) {
    mainIndex.value = index;
}
</script>

<template>
    <div class="flex flex-col gap-2">
        <div class="aspect-square w-full overflow-hidden rounded-lg bg-gray-100">
            <img
                v-if="images.length"
                :src="`/storage/${images[mainIndex]}`"
                :alt="alt"
                class="h-full w-full object-cover"
            >
            <div v-else class="flex h-full w-full items-center justify-center text-gray-400">
                <svg class="h-16 w-16" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909M3.75 21h16.5A1.5 1.5 0 0021 19.5V4.5A1.5 1.5 0 0019.5 3H4.5A1.5 1.5 0 003 4.5v16.5z" />
                </svg>
            </div>
        </div>

        <div v-if="images.length > 1" class="grid grid-cols-5 gap-2">
            <button
                v-for="(image, index) in images"
                :key="image"
                type="button"
                class="aspect-square overflow-hidden rounded-md border-2 bg-gray-100"
                :class="index === mainIndex ? 'border-gray-900' : 'border-transparent opacity-70 hover:opacity-100'"
                @click="select(index)"
            >
                <img
                    :src="`/storage/${image}`"
                    :alt="`${alt} ${index + 1}`"
                    class="h-full w-full object-cover"
                    loading="lazy"
                >
            </button>
        </div>
    </div>
</template>
