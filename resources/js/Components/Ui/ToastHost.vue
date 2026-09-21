<script setup>
import { computed, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AppIcon from '@/Components/Ui/AppIcon.vue';
import { useToast } from '@/composables/useToast';
import { useTranslations } from '@/composables/useTranslations';

const page = usePage();
const { items, dismiss } = useToast();
const { t } = useTranslations();

const flash = computed(() => page.props.flash ?? {});

watch(
    flash,
    (value) => {
        if (value?.success) {
            useToast().push(value.success, 'success');
        }

        if (value?.error) {
            useToast().push(value.error, 'danger');
        }

        if (value?.info) {
            useToast().push(value.info, 'info');
        }
    },
    { immediate: true, deep: true },
);

const TONES = {
    success: {
        wrapper: 'border-success/40 bg-success-soft text-success',
        icon: 'check-circle',
    },
    danger: {
        wrapper: 'border-danger/40 bg-danger-soft text-danger',
        icon: 'alert',
    },
    info: {
        wrapper: 'border-line-strong bg-surface-soft text-ink',
        icon: 'info',
    },
};
</script>

<template>
    <div
        class="pointer-events-none fixed inset-x-0 bottom-4 z-50 flex flex-col items-center gap-2 px-4"
        role="region"
        :aria-label="t('accessibility.notifications')"
    >
        <TransitionGroup
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="translate-y-3 opacity-0"
            leave-active-class="transition duration-150 ease-in"
            leave-to-class="translate-y-2 opacity-0"
        >
            <div
                v-for="item in items"
                :key="item.id"
                class="pointer-events-auto flex w-full max-w-md items-start gap-3 rounded-card border-2 px-4 py-3 shadow-retro"
                :class="(TONES[item.tone] ?? TONES.info).wrapper"
                role="status"
                aria-live="polite"
            >
                <AppIcon
                    :name="(TONES[item.tone] ?? TONES.info).icon"
                    :size="20"
                    class="mt-0.5"
                />

                <p class="flex-1 text-sm font-semibold">
                    {{ item.message }}
                </p>

                <button
                    type="button"
                    class="rounded p-1 opacity-70 transition-opacity hover:opacity-100"
                    :aria-label="t('accessibility.close_notification')"
                    @click="dismiss(item.id)"
                >
                    <AppIcon
                        name="close"
                        :size="16"
                    />
                </button>
            </div>
        </TransitionGroup>
    </div>
</template>
