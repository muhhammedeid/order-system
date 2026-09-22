<script setup>
import BrandMark from '@/Components/Brand/BrandMark.vue';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();

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
        :aria-label="t('brand.home_label')"
    >
        <BrandMark
            :size="markSize"
            class="transition-transform duration-200 group-hover:rotate-45"
        />

        <span class="flex flex-col items-start leading-none" aria-hidden="true">
            <span
                class="font-display text-xl leading-none tracking-tight text-ink sm:text-2xl"
                dir="ltr"
            >
                MAI<span class="text-primary">*</span>
            </span>
            <span
                class="mt-0.5 font-display text-[0.55rem] leading-none tracking-[0.34em] text-ink-muted sm:text-[0.6rem]"
                dir="ltr"
            >
                SHOES
            </span>
        </span>

        <span
            v-if="showTagline"
            class="hidden border-s border-line ps-2.5 font-display text-sm text-ink-muted lg:block"
        >
            {{ t('brand.tagline') }}
        </span>
    </a>
</template>
