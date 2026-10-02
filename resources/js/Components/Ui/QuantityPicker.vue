<script setup>
import { ref, watch } from 'vue';
import { formatQuantity } from '@/Utils/format';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();

const props = defineProps({
    modelValue: {
        type: Number,
        default: 5,
    },
    presets: {
        type: Array,
        default: () => [5, 10],
    },
    min: {
        type: Number,
        default: 1,
    },
    max: {
        type: Number,
        default: 4294967295,
    },
    step: {
        type: Number,
        default: 1,
    },
    label: {
        type: String,
        default: null,
    },
    error: {
        type: String,
        default: null,
    },
    busy: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['update:modelValue']);

const customActive = ref(! props.presets.includes(props.modelValue));
const draft = ref(customActive.value ? props.modelValue : null);

watch(
    () => props.modelValue,
    (value) => {
        if (props.presets.includes(value)) {
            if (! customActive.value) {
                draft.value = null;
            }

            return;
        }

        customActive.value = true;
        draft.value = value;
    },
);

const activeClasses = 'border-primary bg-selected text-primary';
const idleClasses = 'border-line bg-surface text-ink hover:bg-surface-soft';

function selectPreset(value) {
    customActive.value = false;
    draft.value = null;
    emit('update:modelValue', value);
}

function selectCustom() {
    customActive.value = true;
    draft.value = props.presets.includes(props.modelValue) ? props.min : props.modelValue;
    emit('update:modelValue', draft.value);
}

function onCustomInput(event) {
    const digits = String(event.target.value).replace(/\D/g, '');

    if (digits === '') {
        draft.value = null;

        return;
    }

    const value = Number.parseInt(digits, 10);
    draft.value = value;
    emit('update:modelValue', value);
}

function normalizeCustom() {
    const value = draft.value === null ? props.min : Number(draft.value);
    const normalized = Number.isFinite(value)
        ? Math.min(props.max, Math.max(props.min, value))
        : props.min;

    draft.value = normalized;
    emit('update:modelValue', normalized);
}
</script>

<template>
    <fieldset class="min-w-0">
        <legend class="mb-2 text-sm font-semibold text-ink">
            {{ label ?? t('common.quantity') }}
        </legend>

        <div class="flex flex-wrap items-center gap-2">
            <button
                v-for="preset in presets"
                :key="preset"
                type="button"
                class="inline-flex min-h-11 min-w-16 items-center justify-center rounded-control border-2 px-4 text-sm font-bold tabular-nums transition-colors duration-200 ease-out disabled:cursor-not-allowed disabled:opacity-50"
                :class="! customActive && modelValue === preset ? activeClasses : idleClasses"
                :aria-pressed="! customActive && modelValue === preset"
                :disabled="busy"
                @click="selectPreset(preset)"
            >
                {{ formatQuantity(preset) }}
            </button>

            <button
                type="button"
                class="inline-flex min-h-11 items-center justify-center rounded-control border-2 px-4 text-sm font-semibold transition-colors duration-200 ease-out disabled:cursor-not-allowed disabled:opacity-50"
                :class="customActive ? activeClasses : idleClasses"
                :aria-pressed="customActive"
                :disabled="busy"
                @click="selectCustom"
            >
                {{ t('common.other_quantity') }}
            </button>
        </div>

        <div
            v-if="customActive"
            class="mt-2"
        >
            <input
                :value="draft ?? ''"
                type="number"
                inputmode="numeric"
                :min="min"
                :max="max"
                :step="step"
                :aria-label="t('common.custom_quantity', { label: label ?? t('common.quantity') })"
                :aria-invalid="error ? 'true' : undefined"
                :disabled="busy"
                class="h-12 w-36 rounded-control border-2 border-line-strong bg-surface-soft text-center text-base font-bold tabular-nums text-ink"
                @input="onCustomInput"
                @blur="normalizeCustom"
            >
        </div>

        <p
            v-if="error"
            class="mt-1.5 text-sm font-semibold text-danger"
            role="alert"
        >
            {{ error }}
        </p>
    </fieldset>
</template>
