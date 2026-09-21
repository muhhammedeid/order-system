<script setup>
import { computed, useId } from 'vue';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();

const props = defineProps({
    modelValue: {
        type: [String, Number],
        default: '',
    },
    label: {
        type: String,
        required: true,
    },
    type: {
        type: String,
        default: 'text',
    },
    error: {
        type: String,
        default: null,
    },
    hint: {
        type: String,
        default: null,
    },
    required: {
        type: Boolean,
        default: false,
    },
    dir: {
        type: String,
        default: null,
    },
    inputmode: {
        type: String,
        default: null,
    },
    autocomplete: {
        type: String,
        default: null,
    },
    placeholder: {
        type: String,
        default: null,
    },
    name: {
        type: String,
        default: null,
    },
    min: {
        type: [String, Number],
        default: null,
    },
    max: {
        type: [String, Number],
        default: null,
    },
    step: {
        type: [String, Number],
        default: null,
    },
    maxlength: {
        type: [String, Number],
        default: null,
    },
    list: {
        type: String,
        default: null,
    },
    hideLabel: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['update:modelValue']);

const uid = useId();
const inputId = `field-${uid}`;
const describedById = `field-${uid}-description`;

const inputClasses = computed(() => [
    'block w-full min-h-12 rounded-control border-2 bg-surface-soft px-3.5 py-2.5 text-base text-ink placeholder:text-ink-muted/60 transition-colors duration-150',
    props.error
        ? 'border-danger'
        : 'border-line hover:border-ink-muted focus:border-line-strong',
]);

function onInput(event) {
    emit('update:modelValue', event.target.value);
}
</script>

<template>
    <div class="flex flex-col gap-1.5">
        <label
            :for="inputId"
            :class="[
                'text-sm font-semibold text-ink',
                hideLabel ? 'sr-only' : '',
            ]"
        >
            {{ label }}
            <span
                v-if="required"
                class="text-danger"
                aria-hidden="true"
            >*</span>
            <span
                v-if="required"
                class="sr-only"
            >{{ t('common.required') }}</span>
        </label>

        <input
            :id="inputId"
            :type="type"
            :name="name"
            :value="modelValue"
            :dir="dir"
            :inputmode="inputmode"
            :autocomplete="autocomplete"
            :placeholder="placeholder"
            :min="min"
            :max="max"
            :step="step"
            :maxlength="maxlength"
            :list="list"
            :required="required"
            :aria-invalid="error ? 'true' : undefined"
            :aria-describedby="error || hint ? describedById : undefined"
            :class="inputClasses"
            @input="onInput"
        >

        <p
            v-if="error"
            :id="describedById"
            class="flex items-start gap-1 text-sm font-semibold text-danger"
            role="alert"
        >
            {{ error }}
        </p>
        <p
            v-else-if="hint"
            :id="describedById"
            class="text-xs text-ink-muted"
        >
            {{ hint }}
        </p>
    </div>
</template>
