<script setup>
import AppIcon from '@/Components/Ui/AppIcon.vue';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();

defineProps({
    href: {
        type: String,
        required: true,
    },
});
</script>

<template>
    <a
        :href="href"
        target="_blank"
        rel="noopener noreferrer"
        class="inline-flex min-h-12 w-full items-center justify-center gap-2.5 rounded-control bg-external px-5 text-base font-bold text-on-external shadow-soft-sm transition-[transform,box-shadow,filter] duration-200 ease-out hover:-translate-y-px hover:brightness-105 hover:shadow-soft active:translate-y-0 active:shadow-soft-sm"
    >
        <AppIcon
            name="whatsapp"
            :size="22"
        />
        {{ t('product.whatsapp_price') }}
    </a>
</template>
