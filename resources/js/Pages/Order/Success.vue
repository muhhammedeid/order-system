<script setup>
import { Head } from '@inertiajs/vue3';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';

defineProps({
    order_number: {
        type: String,
        required: true,
    },
});
</script>

<template>
    <StorefrontLayout>
        <Head title="تم استلام الطلب" />

        <div class="flex flex-col items-center gap-4 py-16 text-center">
            <div class="flex h-16 w-16 items-center justify-center rounded-full bg-green-100">
                <svg class="h-8 w-8 text-green-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                </svg>
            </div>

            <h2 class="text-2xl font-bold text-gray-900 sm:text-3xl">
                تم استلام طلبك بنجاح
            </h2>

            <p class="text-lg text-gray-700">
                رقم الطلب: <span class="font-bold text-gray-900" dir="ltr">{{ order_number }}</span>
            </p>

            <p class="max-w-md text-gray-600">
                سيتواصل معك فريقنا لتأكيد الطلب وتحديد تفاصيل التسليم.
                في حالة وجود أي استفسار يرجى التواصل معنا عبر WhatsApp.
            </p>

            <a
                href="/catalog"
                class="rounded-lg bg-gray-900 px-6 py-3 text-base font-bold text-white hover:bg-gray-800"
            >
                مواصلة التسوق
            </a>
        </div>
    </StorefrontLayout>
</template>
