<script setup>
import { computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';

const props = defineProps({
    items: {
        type: Array,
        default: () => [],
    },
    total_quantity: {
        type: Number,
        default: 0,
    },
    total_price: {
        type: String,
        default: null,
    },
});

const cartError = computed(() => usePage().props.errors?.quantity ?? null);

const formatter = new Intl.NumberFormat('ar-EG', {
    style: 'decimal',
    maximumFractionDigits: 2,
});

function updateQuantity(item, quantity) {
    if (quantity < 1 || quantity === item.quantity) {
        return;
    }

    router.post('/cart/update', {
        variant_id: item.variant_id,
        quantity,
    }, {
        preserveScroll: true,
    });
}

function removeItem(item) {
    router.post('/cart/remove', { variant_id: item.variant_id }, { preserveScroll: true });
}

function clearCart() {
    router.post('/cart/clear', {}, { preserveScroll: true });
}
</script>

<template>
    <StorefrontLayout>
        <div class="flex flex-col gap-6">
            <h2 class="text-2xl font-bold text-gray-900">الطلب</h2>

            <p
                v-if="cartError"
                class="text-sm font-semibold text-red-600"
            >
                {{ cartError }}
            </p>

            <div
                v-if="items.length"
                class="flex flex-col gap-3"
            >
                <div
                    v-for="item in items"
                    :key="item.variant_id"
                    class="flex flex-wrap items-center gap-3 rounded-lg border border-gray-200 bg-white p-3"
                >
                    <div class="flex-1 min-w-40">
                        <Link
                            :href="`/product/${item.product.slug}`"
                            class="text-base font-bold text-gray-900 hover:underline"
                        >
                            {{ item.product.name }}
                        </Link>
                        <p class="text-sm text-gray-500" dir="ltr">
                            {{ item.product.product_code }}
                        </p>
                        <p class="text-sm text-gray-700">
                            {{ item.color }} / {{ item.size }}
                        </p>
                    </div>

                    <div class="flex items-center gap-2">
                        <label class="text-sm text-gray-600">الكمية</label>
                        <input
                            type="number"
                            min="1"
                            :value="item.quantity"
                            class="w-20 rounded-lg border border-gray-300 px-2 py-1.5 text-gray-900 focus:border-gray-500 focus:outline-none"
                            @change="updateQuantity(item, $event.target.value)"
                        >
                    </div>

                    <div
                        v-if="item.unit_price"
                        class="min-w-32 text-end"
                    >
                        <p class="text-sm text-gray-500">
                            {{ formatter.format(item.unit_price) }} EGP
                        </p>
                        <p class="font-bold text-gray-900">
                            {{ formatter.format(item.line_total) }} EGP
                        </p>
                    </div>
                    <p
                        v-else
                        class="min-w-32 text-end text-sm font-semibold text-gray-500"
                    >
                        السعر عند الطلب
                    </p>

                    <button
                        type="button"
                        class="rounded-lg border border-red-200 px-3 py-2 text-sm font-semibold text-red-600 hover:bg-red-50"
                        @click="removeItem(item)"
                    >
                        حذف
                    </button>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg bg-white border border-gray-200 p-4">
                    <p class="text-gray-700">
                        إجمالي القطع: <span class="font-bold">{{ total_quantity }}</span>
                    </p>
                    <p
                        v-if="total_price"
                        class="text-lg font-bold text-gray-900"
                    >
                        الإجمالي: {{ formatter.format(total_price) }} EGP
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <Link
                        href="/checkout"
                        class="rounded-lg bg-gray-900 px-6 py-3 text-base font-bold text-white hover:bg-gray-800"
                    >
                        إتمام الطلب
                    </Link>
                    <Link
                        href="/catalog"
                        class="rounded-lg border border-gray-300 px-4 py-3 text-base font-semibold text-gray-700 hover:bg-gray-100"
                    >
                        مواصلة التسوق
                    </Link>
                    <button
                        type="button"
                        class="rounded-lg border border-red-200 px-4 py-3 text-base font-semibold text-red-600 hover:bg-red-50"
                        @click="clearCart"
                    >
                        إفراغ الطلب
                    </button>
                </div>
            </div>

            <div v-else class="flex flex-col items-center gap-4 py-16">
                <p class="text-lg text-gray-500">الطلب فارغ</p>
                <Link
                    href="/catalog"
                    class="rounded-lg bg-gray-900 px-6 py-3 text-base font-bold text-white hover:bg-gray-800"
                >
                    تسوق الآن
                </Link>
            </div>
        </div>
    </StorefrontLayout>
</template>
