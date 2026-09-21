<script setup>
import { computed } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import AppButton from '@/Components/Ui/AppButton.vue';
import AppCard from '@/Components/Ui/AppCard.vue';
import AppIcon from '@/Components/Ui/AppIcon.vue';
import { useToast } from '@/composables/useToast';
import { useTranslations } from '@/composables/useTranslations';

const props = defineProps({
    order_number: {
        type: String,
        required: true,
    },
});

const page = usePage();
const { push } = useToast();
const { t } = useTranslations();

const whatsapp = computed(() => page.props.whatsapp ?? null);

const whatsappHref = computed(() => {
    if (! whatsapp.value) {
        return null;
    }

    const message = t('success.whatsapp_message', { number: props.order_number });

    return `https://wa.me/${whatsapp.value}?text=${encodeURIComponent(message)}`;
});

const STEPS = computed(() => [
    { icon: 'check-circle', title: t('success.steps.received.title'), text: t('success.steps.received.text') },
    { icon: 'phone', title: t('success.steps.review.title'), text: t('success.steps.review.text') },
    { icon: 'truck', title: t('success.steps.prepare.title'), text: t('success.steps.prepare.text') },
]);

async function copyOrderNumber() {
    try {
        await navigator.clipboard.writeText(props.order_number);
        push(t('success.copied'), 'success');
    } catch (error) {
        push(t('success.copy_failed'), 'danger');
    }
}
</script>

<template>
    <StorefrontLayout>
        <Head :title="t('success.title')" />

        <div class="mx-auto flex max-w-3xl flex-col items-center gap-6 py-8 text-center sm:py-12">
            <span class="flex h-20 w-20 items-center justify-center rounded-full border-2 border-line-strong bg-success-soft text-success shadow-retro">
                <AppIcon
                    name="check"
                    :size="38"
                />
            </span>

            <div class="flex flex-col gap-2">
                <h1 class="font-display text-3xl font-bold text-ink sm:text-4xl">
                    {{ t('success.heading') }}
                </h1>
                <p class="max-w-lg text-ink-muted">
                    {{ t('success.intro') }}
                </p>
            </div>

            <AppCard
                shadow
                class="w-full"
            >
                <p class="text-sm font-semibold text-ink-muted">
                    {{ t('success.order_number') }}
                </p>
                <div class="mt-2 flex flex-wrap items-center justify-center gap-3">
                    <span class="font-mono text-2xl font-bold tabular-nums text-ink sm:text-3xl">
                        {{ order_number }}
                    </span>
                    <button
                        type="button"
                        class="inline-flex min-h-11 items-center gap-2 rounded-control border-2 border-line-strong bg-surface px-3.5 text-sm font-semibold text-ink transition-colors hover:bg-surface-muted"
                        @click="copyOrderNumber"
                    >
                        <AppIcon
                            name="copy"
                            :size="18"
                        />
                        {{ t('success.copy') }}
                    </button>
                </div>

                <p class="mt-3 text-xs text-ink-muted">
                    {{ t('success.keep_number') }}
                </p>
            </AppCard>

            <ol class="grid w-full gap-3 text-start sm:grid-cols-3">
                <li
                    v-for="step in STEPS"
                    :key="step.title"
                    class="flex flex-col gap-2 rounded-card border-2 border-line bg-surface-soft p-4"
                >
                    <AppIcon
                        :name="step.icon"
                        :size="22"
                        class="text-primary"
                    />
                    <span class="font-display text-base font-bold text-ink">
                        {{ step.title }}
                    </span>
                    <span class="text-sm text-ink-muted">{{ step.text }}</span>
                </li>
            </ol>

            <div class="flex flex-wrap items-center justify-center gap-3">
                <AppButton
                    href="/catalog"
                    variant="primary"
                    size="lg"
                    icon="search"
                >
                    {{ t('success.continue_shopping') }}
                </AppButton>

                <a
                    v-if="whatsappHref"
                    :href="whatsappHref"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex min-h-14 items-center gap-2.5 rounded-control border-2 border-line-strong bg-whatsapp px-6 text-lg font-bold text-white shadow-retro-sm transition-[transform,box-shadow,filter] duration-150 hover:brightness-110 active:translate-y-[2px] active:shadow-none"
                >
                    <AppIcon
                        name="whatsapp"
                        :size="22"
                    />
                    {{ t('success.send_whatsapp') }}
                </a>
            </div>
        </div>
    </StorefrontLayout>
</template>
