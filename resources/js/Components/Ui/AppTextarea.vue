<script setup>
import { computed, useId } from 'vue';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();

const props = defineProps({
    modelValue: {
        type: String,
        default: '',
    },
    label: {
        type: String,
        required: true,
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
    rows: {
        type: [String, Number],
        default: 3,
    },
    maxlength: {
        type: [String, Number],
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
});

const emit = defineEmits(['update:modelValue']);

const uid = useId();
const fieldId = `field-${uid}`;
const describedById = `field-${uid}-description`;

const textareaClasses = computed(() => [
    'block w-full rounded-control border-2 bg-surface-soft px-3.5 py-2.5 text-base text-ink placeholder:text-ink-muted transition-colors duration-200 ease-out',
    props.error
        ? 'border-danger'
        : 'border-line-strong hover:border-ink-muted',
]);
</script>

<template>
    <div class="flex flex-col gap-1.5">
        <label
            :for="fieldId"
            class="text-sm font-semibold text-ink"
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

        <textarea
            :id="fieldId"
            :name="name"
            :value="modelValue"
            :rows="rows"
            :maxlength="maxlength"
            :placeholder="placeholder"
            :required="required"
            :aria-invalid="error ? 'true' : undefined"
            :aria-describedby="error || hint ? describedById : undefined"
            :class="textareaClasses"
            @input="emit('update:modelValue', $event.target.value)"
        />

        <p
            v-if="error"
            :id="describedById"
            class="text-sm font-semibold text-danger"
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
