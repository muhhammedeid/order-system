<script setup>
import AppIcon from '@/Components/Ui/AppIcon.vue';
import { useTheme } from '@/composables/useTheme';

const { isDark, toggle } = useTheme();
</script>

<template>
    <button
        type="button"
        class="inline-flex h-12 w-12 items-center justify-center rounded-control border-2 border-cream/25 transition-colors duration-150 hover:bg-cream/15"
        :aria-label="isDark ? 'تفعيل الوضع الفاتح' : 'تفعيل الوضع الليلي'"
        :title="isDark ? 'الوضع الفاتح' : 'الوضع الليلي'"
        :aria-pressed="isDark"
        @click="toggle"
    >
        <AppIcon
            :name="isDark ? 'sun' : 'moon'"
            :size="21"
        />
    </button>
</template>
