<script setup>
import { useTranslations } from '@/composables/useTranslations';
const { t } = useTranslations();
defineProps({
    showTagline: { type: Boolean, default: true },
    markSize: { type: Number, default: 38 },
    href: { type: String, default: '/' },
});
</script>

<template>
    <a :href="href" class="inline-flex items-center gap-2.5 rounded-control" :aria-label="t('brand.home_label')">
        <img
            :src="'/Logo.png?v=20261001-original3'"
            alt="Mai shoes"
            width="1536"
            height="1024"
            class="shrink-0 rounded bg-white object-contain"
            :style="{ height: `${markSize + 18}px`, width: `${(markSize + 18) * 1.5}px` }"
        >
        <span v-if="showTagline" class="hidden border-s border-line ps-2.5 font-display text-sm text-ink-muted lg:block">
            {{ t('brand.tagline') }}
        </span>
    </a>
</template>
