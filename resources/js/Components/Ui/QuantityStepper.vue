<script setup>
import { computed, ref, watch } from 'vue';
import AppIcon from '@/Components/Ui/AppIcon.vue';

const props = defineProps({
    modelValue: {
        type: Number,
        default: 1,
    },
    min: {
        type: Number,
        default: 1,
    },
    max: {
        type: Number,
        default: null,
    },
    step: {
        type: Number,
        default: 1,
    },
    label: {
        type: String,
        default: 'الكمية',
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

const draft = ref(props.modelValue);

watch(
    () => props.modelValue,
    (value) => {
        draft.value = value;
    },
);

const canDecrease = computed(() => draft.value > props.min);
const canIncrease = computed(() => props.max === null || draft.value < props.max);

function clamp(value) {
    const numeric = Math.floor(Number(value));

    if (! Number.isFinite(numeric)) {
        return props.min;
    }

    const lowerBounded = Math.max(props.min, numeric);

    return props.max === null ? lowerBounded : Math.min(props.max, lowerBounded);
}

function commit(value) {
    const next = clamp(value);
    draft.value = next;
    emit('update:modelValue', next);
}

function step(delta) {
    commit(draft.value + (delta * props.step));
}

function onBlur() {
    commit(draft.value);
}
</script>

<template>
    <div class="flex flex-col gap-1.5">
        <span class="text-sm font-semibold text-ink">{{ label }}</span>

        <div
            class="inline-flex items-stretch overflow-hidden rounded-control border-2"
            :class="error ? 'border-danger' : 'border-line-strong'"
        >
            <button
                type="button"
                class="flex h-12 w-12 items-center justify-center bg-surface-soft text-ink transition-colors hover:bg-surface-muted disabled:cursor-not-allowed disabled:opacity-40"
                :disabled="! canDecrease || busy"
                :aria-label="`تقليل ${label}`"
                @click="step(-1)"
            >
                <AppIcon
                    name="minus"
                    :size="18"
                />
            </button>

            <input
                v-model.number="draft"
                type="number"
                inputmode="numeric"
                :min="min"
                :max="max ?? undefined"
                :step="step"
                :aria-label="label"
                :aria-invalid="error ? 'true' : undefined"
                :aria-busy="busy ? 'true' : undefined"
                class="h-12 w-16 border-x-2 border-line-strong bg-surface-soft text-center text-base font-bold tabular-nums text-ink"
                @blur="onBlur"
                @change="commit(draft)"
            >

            <button
                type="button"
                class="flex h-12 w-12 items-center justify-center bg-surface-soft text-ink transition-colors hover:bg-surface-muted disabled:cursor-not-allowed disabled:opacity-40"
                :disabled="! canIncrease || busy"
                :aria-label="`زيادة ${label}`"
                @click="step(1)"
            >
                <AppIcon
                    name="plus"
                    :size="18"
                />
            </button>
        </div>

        <p
            v-if="error"
            class="text-sm font-semibold text-danger"
            role="alert"
        >
            {{ error }}
        </p>
    </div>
</template>
