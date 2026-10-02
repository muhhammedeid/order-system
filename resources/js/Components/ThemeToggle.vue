<script setup>
import AppIcon from '@/Components/Ui/AppIcon.vue';
import { useTheme } from '@/composables/useTheme';
import { useTranslations } from '@/composables/useTranslations';

const { isDark, toggle } = useTheme();
const { t } = useTranslations();
</script>

<template>
    <button
        type="button"
        class="inline-flex h-12 w-12 items-center justify-center rounded-control border border-line text-ink transition-colors duration-200 ease-out hover:bg-surface-soft"
        :aria-label="isDark ? t('theme.enable_light') : t('theme.enable_dark')"
        :title="isDark ? t('theme.light') : t('theme.dark')"
        :aria-pressed="isDark"
        @click="toggle"
    >
        <AppIcon
            :name="isDark ? 'sun' : 'moon'"
            :size="21"
        />
    </button>
</template>
