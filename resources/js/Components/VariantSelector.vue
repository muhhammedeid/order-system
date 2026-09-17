<script setup>
import { computed, ref, watch } from 'vue';

const props = defineProps({
    variants: {
        type: Array,
        required: true,
    },
});

const selectedColor = ref(props.variants.length ? props.variants[0].color : '');
const selectedSize = ref('');

watch(selectedColor, () => {
    selectedSize.value = '';
});

const selectedSizes = computed(() =>
    props.variants.find((variant) => variant.color === selectedColor.value)?.sizes ?? [],
);

defineExpose({ selectedColor, selectedSize });
</script>

<template>
    <div class="flex flex-col gap-4">
        <div>
            <p class="mb-2 text-sm font-semibold text-gray-700">اللون</p>
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="group in variants"
                    :key="group.color"
                    type="button"
                    class="rounded-lg border px-4 py-2 text-sm font-semibold"
                    :class="selectedColor === group.color
                        ? 'border-gray-900 bg-gray-900 text-white'
                        : 'border-gray-300 bg-white text-gray-700 hover:border-gray-500'"
                    @click="selectedColor = group.color"
                >
                    {{ group.color }}
                </button>
            </div>
        </div>

        <div>
            <p class="mb-2 text-sm font-semibold text-gray-700">المقاس</p>
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="size in selectedSizes"
                    :key="size.size"
                    type="button"
                    class="rounded-lg border px-4 py-2 text-sm font-semibold"
                    :class="selectedSize === size.size
                        ? 'border-gray-900 bg-gray-900 text-white'
                        : size.available_quantity > 0
                            ? 'border-gray-300 bg-white text-gray-700 hover:border-gray-500'
                            : 'border-gray-200 bg-gray-100 text-gray-400'"
                    :disabled="size.available_quantity <= 0"
                    :title="size.available_quantity <= 0 ? 'غير متوفر' : ''"
                    @click="selectedSize = size.size"
                >
                    {{ size.size }}
                </button>
            </div>
        </div>
    </div>
</template>
