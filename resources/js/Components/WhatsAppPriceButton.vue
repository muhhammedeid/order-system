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
        class="inline-flex min-h-12 w-full items-center justify-center gap-2.5 rounded-control border-2 border-line-strong bg-whatsapp px-5 text-base font-bold text-white shadow-retro-sm transition-[transform,box-shadow,filter] duration-150 hover:brightness-110 active:translate-y-[2px] active:shadow-none"
    >
        <AppIcon
            name="whatsapp"
            :size="22"
        />
        {{ t('product.whatsapp_price') }}
    </a>
</template>
