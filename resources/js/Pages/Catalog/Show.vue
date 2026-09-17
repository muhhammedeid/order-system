<script setup>
import { computed, ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import ProductGallery from '@/Components/ProductGallery.vue';
import VariantSelector from '@/Components/VariantSelector.vue';
import WhatsAppPriceButton from '@/Components/WhatsAppPriceButton.vue';

const props = defineProps({
    product: {
        type: Object,
        required: true,
    },
    variants: {
        type: Array,
        default: () => [],
    },
    whatsapp: {
        type: Object,
        default: null,
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

const form = useForm({
    variant_id: null,
    quantity: 1,
});

const maxQuantity = computed(() => selectedVariant.value?.available_quantity ?? 0);

function addToOrder() {
    if (!selectedVariant.value || form.quantity < 1) {
        return;
    }

    form.variant_id = selectedVariant.value.id;
    form.post('/cart/add', {
        preserveScroll: true,
    });
}

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

                <form
                    v-if="variants.length"
                    class="flex items-center gap-3"
                    @submit.prevent="addToOrder"
                >
                    <input
                        v-model.number="form.quantity"
                        type="number"
                        min="1"
                        :max="maxQuantity"
                        class="w-20 rounded-lg border border-gray-300 px-3 py-2 text-gray-900 focus:border-gray-500 focus:outline-none"
                    >
                    <button
                        type="submit"
                        class="rounded-lg bg-gray-900 px-6 py-3 text-base font-bold text-white hover:bg-gray-800 disabled:opacity-40"
                        :disabled="!selectedVariant || form.quantity < 1 || form.quantity > maxQuantity"
                    >
                        أضف إلى الطلب
                    </button>
                </form>

                <p
                    v-if="form.errors.quantity || form.errors.variant_id"
                    class="text-sm font-semibold text-red-600"
                >
                    {{ form.errors.quantity || form.errors.variant_id }}
                </p>

                <p
                    v-if="product.description"
                    class="text-gray-700 text-start"
                >
                    {{ product.description }}
                </p>

                <WhatsAppPriceButton
                    v-if="whatsapp"
                    :href="whatsapp.href"
                />
            </div>
        </div>
    </StorefrontLayout>
</template>
