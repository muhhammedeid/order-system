<script setup>
defineProps({
    product: {
        type: Object,
        required: true,
    },
});

const formatter = new Intl.NumberFormat('ar-EG', {
    style: 'decimal',
    maximumFractionDigits: 2,
});
</script>

<template>
    <div class="group flex flex-col overflow-hidden rounded-lg border border-gray-200 bg-white transition hover:shadow-md">
        <div class="aspect-square w-full bg-gray-100">
            <img
                v-if="product.images && product.images.length"
                :src="`/storage/${product.images[0].image_path}`"
                :alt="product.name"
                class="h-full w-full object-cover"
                loading="lazy"
            >
        </div>

        <div class="flex flex-1 flex-col gap-1 p-3">
            <h3 class="text-base font-bold text-gray-900 text-start">
                {{ product.name }}
            </h3>
            <p class="text-sm text-gray-500 text-start" dir="ltr">
                {{ product.product_code }}
            </p>

            <div class="mt-auto pt-2">
                <p
                    v-if="product.price_visibility === 'public'"
                    class="text-lg font-bold text-gray-900 text-start"
                >
                    {{ formatter.format(product.price) }} EGP
                </p>
                <p v-else class="text-base font-semibold text-gray-700 text-start">
                    السعر عند الطلب
                </p>
            </div>
        </div>
    </div>
</template>
