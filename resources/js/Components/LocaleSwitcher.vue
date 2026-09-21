<script setup>
import { usePage } from '@inertiajs/vue3';
import { useTranslations } from '@/composables/useTranslations';

const page = usePage();
const { locale, t } = useTranslations();
</script>

<template>
    <form
        method="POST"
        :action="`/locale/${locale === 'ar' ? 'en' : 'ar'}`"
        class="inline-flex"
    >
        <input type="hidden" name="_token" :value="page.props.csrfToken">
        <button
            type="submit"
            class="inline-flex min-h-11 items-center justify-center rounded-full border border-line px-3 text-sm font-bold text-ink transition hover:border-brand hover:text-brand focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand"
            :aria-label="t('language.label')"
            :title="locale === 'ar' ? t('language.english') : t('language.arabic')"
        >
            {{ locale === 'ar' ? 'EN' : 'ع' }}
        </button>
    </form>
</template>
