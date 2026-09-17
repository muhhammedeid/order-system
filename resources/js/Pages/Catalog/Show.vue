<script setup>
import { computed, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import ProductGallery from '@/Components/ProductGallery.vue';
import VariantSelector from '@/Components/VariantSelector.vue';

const props = defineProps({
    product: {
        type: Object,
        required: true,
    },
    variants: {
        type: Array,
        default: () => [],
    },
});

const selector = ref(null);

const selectedVariant = computed(() => {
    if (!selector.value) {
        return null;
    }

    return props.variants
        .find((group) => group.color === selector.value.selectedColor)
        ?.sizes.find((size) => size.size === selector.value.selectedSize) ?? null;
});

const formatter = new Intl.NumberFormat('ar-EG', {
    style: 'decimal',
    maximumFractionDigits: 2,
});
</script>

<template>
    <StorefrontLayout>
        <Head :title="product.name" />

        <div class="grid grid-cols-1 gap-8 md:grid-cols-2">
            <ProductGallery
                :images="product.images"
                :alt="product.name"
            />

            <div class="flex flex-col gap-5">
                <div>
                    <p
                        v-if="product.category"
                        class="text-sm font-semibold text-gray-500"
                    >
                        {{ product.category.name }}
                    </p>
                    <h1 class="mt-1 text-2xl font-bold text-gray-900 sm:text-3xl">
                        {{ product.name }}
                    </h1>
                    <p class="mt-1 text-sm text-gray-500" dir="ltr">
                        {{ product.product_code }}
                    </p>
                </div>

                <p
                    v-if="product.price_visibility === 'public'"
                    class="text-2xl font-bold text-gray-900"
                >
                    {{ formatter.format(product.price) }} EGP
                </p>

                <VariantSelector
                    v-if="variants.length"
                    ref="selector"
                    :variants="variants"
                />

                <p
                    v-if="selector && selector.selectedSize && selectedVariant"
                    class="text-sm text-gray-700"
                >
                    <span class="font-semibold">الكمية المتاحة:</span>
                    {{ selectedVariant.available_quantity }}
                </p>

                <p
                    v-if="product.description"
                    class="text-gray-700 text-start"
                >
                    {{ product.description }}
                </p>
            </div>
        </div>
    </StorefrontLayout>
</template>
