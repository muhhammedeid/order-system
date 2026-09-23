<script setup>
import { ref, watch } from 'vue';
import AppButton from '@/Components/Ui/AppButton.vue';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();

const props = defineProps({
    open: {
        type: Boolean,
        default: false,
    },
    title: {
        type: String,
        required: true,
    },
    description: {
        type: String,
        default: null,
    },
    confirmLabel: {
        type: String,
        default: null,
    },
    cancelLabel: {
        type: String,
        default: null,
    },
    tone: {
        type: String,
        default: 'primary',
    },
});

const emit = defineEmits(['confirm', 'cancel']);

const dialog = ref(null);

watch(
    () => props.open,
    (open) => {
        if (! dialog.value) {
            return;
        }

        if (open && ! dialog.value.open) {
            dialog.value.showModal();
        }

        if (! open && dialog.value.open) {
            dialog.value.close();
        }
    },
);
</script>

<template>
    <dialog
        ref="dialog"
        class="w-[min(28rem,calc(100vw-2rem))] rounded-card border border-line bg-surface p-5 text-ink shadow-soft-lg backdrop:bg-ink/40"
        aria-labelledby="confirm-dialog-title"
        @cancel.prevent="emit('cancel')"
        @close="emit('cancel')"
    >
        <h2
            id="confirm-dialog-title"
            class="font-display text-xl font-bold"
        >
            {{ title }}
        </h2>

        <p
            v-if="description"
            class="mt-2 text-sm text-ink-muted"
        >
            {{ description }}
        </p>

        <div class="mt-5 flex flex-wrap items-center justify-end gap-3">
            <AppButton
                variant="secondary"
                size="sm"
                @click="emit('cancel')"
            >
                {{ cancelLabel ?? t('common.cancel') }}
            </AppButton>
            <AppButton
                :variant="tone"
                size="sm"
                @click="emit('confirm')"
            >
                {{ confirmLabel ?? t('common.confirm') }}
            </AppButton>
        </div>
    </dialog>
</template>
