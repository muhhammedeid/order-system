<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import AppIcon from '@/Components/Ui/AppIcon.vue';
import BrandLockup from '@/Components/Brand/BrandLockup.vue';
import { useTranslations } from '@/composables/useTranslations';

const page = usePage();
const { t } = useTranslations();

/*
 * The shared `whatsapp` prop is the configured number as a string, but a
 * request-price product page replaces it with `{ number, message, href }`.
 * The footer renders on every page, so normalise both shapes: the payload
 * object must never be interpolated, and the contact link must stay a valid
 * wa.me URL.
 */
const whatsappLink = computed(() => {
    const value = page.props.whatsapp;

    if (typeof value === 'string' && value !== '') {
        return { href: `https://wa.me/${value}`, number: value };
    }

    if (typeof value?.number === 'string' && value.number !== '') {
        return { href: `https://wa.me/${value.number}`, number: value.number };
    }

    return null;
});

const year = new Date().getFullYear();

const LINKS = computed(() => [
    { label: t('nav.catalog'), href: '/catalog' },
    { label: t('nav.cart'), href: '/cart' },
]);
</script>

<template>
    <footer class="mt-16 border-t border-line bg-surface-muted text-ink">
        <div class="mx-auto grid w-full max-w-7xl gap-8 px-4 py-12 sm:px-6 lg:grid-cols-[1.5fr_1fr_1.25fr] lg:px-8">
            <div class="flex flex-col gap-3">
                <BrandLockup :show-tagline="false" :mark-size="44" />

                <p class="max-w-xs text-sm text-ink-muted">
                    {{ t('footer.summary') }}
                </p>
            </div>

            <nav
                class="flex flex-col gap-2"
                :aria-label="t('footer.quick_links')"
            >
                <h2 class="font-display text-base font-bold text-ink">
                    {{ t('footer.quick_links') }}
                </h2>
                <Link
                    v-for="link in LINKS"
                    :key="link.href"
                    :href="link.href"
                    class="w-fit rounded text-sm font-semibold text-ink-muted transition-colors duration-200 ease-out hover:text-primary"
                >
                    {{ link.label }}
                </Link>
            </nav>

            <div class="flex flex-col gap-2">
                <h2 class="font-display text-base font-bold text-ink">
                    {{ t('footer.contact') }}
                </h2>

                <a
                    v-if="whatsappLink"
                    :href="whatsappLink.href"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex min-h-11 w-fit items-center gap-2 rounded-control border border-line-strong bg-surface px-3.5 text-sm font-semibold text-ink transition-colors duration-200 ease-out hover:bg-surface-soft"
                >
                    <AppIcon
                        name="whatsapp"
                        :size="18"
                        class="text-external"
                    />
                    {{ t('footer.whatsapp') }}
                    <span
                        class="tabular-nums"
                        dir="ltr"
                    >{{ whatsappLink.number }}</span>
                </a>

                <p class="text-xs text-ink-muted">
                    {{ t('footer.price_note') }}
                </p>
            </div>
        </div>

        <div class="border-t border-line">
            <div class="mx-auto flex w-full max-w-7xl flex-wrap items-center justify-between gap-2 px-4 py-4 text-xs text-ink-muted sm:px-6 lg:px-8">
                <p>{{ t('footer.rights', { year }) }}</p>
                <p>{{ t('footer.owner') }}</p>
                <p>{{ t('footer.disclaimer') }}</p>
            </div>
        </div>
    </footer>
</template>
