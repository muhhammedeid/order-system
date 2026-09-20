<script setup>
import BrandMark from '@/Components/Brand/BrandMark.vue';

defineProps({
    showTagline: {
        type: Boolean,
        default: true,
    },
    markSize: {
        type: Number,
        default: 38,
    },
    href: {
        type: String,
        default: '/',
    },
});
</script>

<template>
    <a
        :href="href"
        class="group inline-flex items-center gap-2.5 rounded-control"
        aria-label="MAI SHOES — الصفحة الرئيسية"
    >
        <BrandMark
            :size="markSize"
            class="transition-transform duration-200 group-hover:rotate-45"
        />

        <span class="flex flex-col items-start leading-none" aria-hidden="true">
            <span
                class="font-brand text-xl leading-none tracking-tight text-cream sm:text-2xl"
                dir="ltr"
            >
                MAI<span class="text-powder">*</span>
            </span>
            <span
                class="mt-0.5 font-brand text-[0.55rem] leading-none tracking-[0.34em] text-cream/80 sm:text-[0.6rem]"
                dir="ltr"
            >
                SHOES
            </span>
        </span>

        <span
            v-if="showTagline"
            class="hidden border-s border-cream/25 ps-2.5 font-display text-sm text-cream/90 lg:block"
        >
            طلبات الجملة
        </span>
    </a>
</template>
