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

const errors = computed(() => usePage().props.errors ?? {});

const formatter = new Intl.NumberFormat('ar-EG', {
    style: 'decimal',
    maximumFractionDigits: 2,
});

function submit(event) {
    const formData = new FormData(event.target);

    router.post('/checkout', formData, {
        preserveScroll: true,
    });
}
</script>

<template>
    <StorefrontLayout>
        <div class="flex flex-col gap-6">
            <h2 class="text-2xl font-bold text-gray-900">إتمام الطلب</h2>

            <div
                v-if="!items.length"
                class="flex flex-col items-center gap-4 py-16"
            >
                <p class="text-lg text-gray-500">الطلب فارغ</p>
                <Link
                    href="/catalog"
                    class="rounded-lg bg-gray-900 px-6 py-3 text-base font-bold text-white hover:bg-gray-800"
                >
                    تسوق الآن
                </Link>
            </div>

            <template v-else>
                <div class="rounded-lg border border-gray-200 bg-white p-4">
                    <h3 class="mb-3 text-lg font-bold text-gray-900">ملخص الطلب</h3>
                    <div class="flex flex-col gap-2">
                        <div
                            v-for="item in items"
                            :key="item.variant_id"
                            class="flex flex-wrap items-center justify-between gap-2 text-sm"
                        >
                            <span class="font-semibold text-gray-900">
                                {{ item.product.name }}
                            </span>
                            <span class="text-gray-600" dir="ltr">
                                {{ item.product.product_code }}
                            </span>
                            <span class="text-gray-600">{{ item.color }} / {{ item.size }}</span>
                            <span class="text-gray-600">× {{ item.quantity }}</span>
                            <span
                                v-if="item.line_total"
                                class="font-semibold text-gray-900"
                            >
                                {{ formatter.format(item.line_total) }} EGP
                            </span>
                            <span v-else class="text-gray-500">السعر عند الطلب</span>
                        </div>
                    </div>
                    <div class="mt-3 flex flex-wrap items-center justify-between gap-2 border-t border-gray-100 pt-3">
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
                </div>

                <form
                    class="flex flex-col gap-4 rounded-lg border border-gray-200 bg-white p-4"
                    @submit.prevent="submit"
                >
                    <h3 class="text-lg font-bold text-gray-900">بيانات التواصل</h3>

                    <p
                        v-if="Object.keys(errors).length"
                        class="text-sm font-semibold text-red-600"
                    >
                        يرجى تصحيح الحقول المطلوبة
                    </p>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <label class="flex flex-col gap-1">
                            <span class="text-sm font-semibold text-gray-700">الاسم <span class="text-red-600">*</span></span>
                            <input
                                type="text"
                                name="name"
                                required
                                class="rounded-lg border border-gray-300 px-3 py-2 text-gray-900 focus:border-gray-500 focus:outline-none"
                            >
                            <span
                                v-if="errors.name"
                                class="text-xs text-red-600"
                            >{{ errors.name }}</span>
                        </label>

                        <label class="flex flex-col gap-1">
                            <span class="text-sm font-semibold text-gray-700">رقم الموبايل <span class="text-red-600">*</span></span>
                            <input
                                type="text"
                                name="phone"
                                required
                                dir="ltr"
                                class="rounded-lg border border-gray-300 px-3 py-2 text-gray-900 focus:border-gray-500 focus:outline-none"
                            >
                            <span
                                v-if="errors.phone"
                                class="text-xs text-red-600"
                            >{{ errors.phone }}</span>
                        </label>

                        <label class="flex flex-col gap-1">
                            <span class="text-sm text-gray-700">اسم الشركة / المحل</span>
                            <input
                                type="text"
                                name="company_name"
                                class="rounded-lg border border-gray-300 px-3 py-2 text-gray-900 focus:border-gray-500 focus:outline-none"
                            >
                        </label>

                        <label class="flex flex-col gap-1">
                            <span class="text-sm text-gray-700">واتساب</span>
                            <input
                                type="text"
                                name="whatsapp"
                                dir="ltr"
                                class="rounded-lg border border-gray-300 px-3 py-2 text-gray-900 focus:border-gray-500 focus:outline-none"
                            >
                        </label>

                        <label class="flex flex-col gap-1">
                            <span class="text-sm text-gray-700">المحافظة</span>
                            <input
                                type="text"
                                name="governorate"
                                class="rounded-lg border border-gray-300 px-3 py-2 text-gray-900 focus:border-gray-500 focus:outline-none"
                            >
                        </label>

                        <label class="flex flex-col gap-1">
                            <span class="text-sm text-gray-700">المدينة</span>
                            <input
                                type="text"
                                name="city"
                                class="rounded-lg border border-gray-300 px-3 py-2 text-gray-900 focus:border-gray-500 focus:outline-none"
                            >
                        </label>

                        <label class="flex flex-col gap-1 sm:col-span-2">
                            <span class="text-sm text-gray-700">العنوان</span>
                            <input
                                type="text"
                                name="address"
                                class="rounded-lg border border-gray-300 px-3 py-2 text-gray-900 focus:border-gray-500 focus:outline-none"
                            >
                        </label>

                        <label class="flex flex-col gap-1 sm:col-span-2">
                            <span class="text-sm text-gray-700">ملاحظات على الطلب</span>
                            <textarea
                                name="customer_notes"
                                rows="3"
                                class="rounded-lg border border-gray-300 px-3 py-2 text-gray-900 focus:border-gray-500 focus:outline-none"
                            />
                        </label>
                    </div>

                    <button
                        type="submit"
                        class="self-start rounded-lg bg-gray-900 px-8 py-3 text-base font-bold text-white hover:bg-gray-800"
                    >
                        إرسال الطلب
                    </button>
                </form>
            </template>
        </div>
    </StorefrontLayout>
</template>
