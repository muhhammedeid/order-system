<script setup>
import { computed, watch } from 'vue';
import AppIcon from '@/Components/Ui/AppIcon.vue';

const props = defineProps({
    variants: {
        type: Array,
        required: true,
    },
    selectedColor: {
        type: String,
        default: '',
    },
    selectedSize: {
        type: String,
        default: '',
    },
    sizeEnabled: {
        type: Boolean,
        default: false,
    },
    colorError: {
        type: String,
        default: null,
    },
    sizeError: {
        type: String,
        default: null,
    },
});

const emit = defineEmits(['update:selectedColor', 'update:selectedSize']);

const selectedGroup = computed(
    () => props.variants.find((group) => group.color === props.selectedColor) ?? null,
);

const sizes = computed(() => selectedGroup.value?.sizes ?? []);

watch(
    () => props.selectedColor,
    () => {
        emit('update:selectedSize', '');
    },
);

function selectColor(color) {
    emit('update:selectedColor', color);
}

function selectSize(size) {
    emit('update:selectedSize', size);
}
</script>

<template>
    <div class="flex flex-col gap-5">
        <fieldset class="min-w-0">
            <legend class="mb-2 text-sm font-semibold text-ink">
                اللون
            </legend>

            <div class="flex flex-wrap gap-2">
                <label
                    v-for="group in variants"
                    :key="group.color"
                    class="cursor-pointer"
                >
                    <input
                        type="radio"
                        name="variant-color"
                        class="peer sr-only"
                        :value="group.color"
                        :checked="selectedColor === group.color"
                        @change="selectColor(group.color)"
                    >
                    <span
                        class="inline-flex min-h-11 items-center gap-2 rounded-control border-2 px-4 text-sm font-semibold transition-colors peer-focus-visible:ring-2 peer-focus-visible:ring-ring peer-focus-visible:ring-offset-2 peer-focus-visible:ring-offset-surface"
                        :class="selectedColor === group.color
                            ? 'border-line-strong bg-navy text-cream'
                            : 'border-line bg-surface-soft text-ink hover:border-line-strong'"
                    >
                        {{ group.color }}
                    </span>
                </label>
            </div>

            <p
                v-if="colorError"
                class="mt-1.5 text-sm font-semibold text-danger"
                role="alert"
            >
                {{ colorError }}
            </p>
        </fieldset>

        <fieldset
            v-if="sizeEnabled && selectedGroup"
            class="min-w-0"
        >
            <legend class="mb-2 text-sm font-semibold text-ink">
                المقاس
            </legend>

            <div class="flex flex-wrap gap-2">
                <label
                    v-for="size in sizes"
                    :key="size.size"
                    class="cursor-pointer"
                >
                    <input
                        type="radio"
                        name="variant-size"
                        class="peer sr-only"
                        :value="size.size"
                        :checked="selectedSize === size.size"
                        @change="selectSize(size.size)"
                    >
                    <span
                        class="inline-flex min-h-11 min-w-14 items-center justify-center rounded-control border-2 px-3.5 text-sm font-bold tabular-nums transition-colors peer-focus-visible:ring-2 peer-focus-visible:ring-ring peer-focus-visible:ring-offset-2 peer-focus-visible:ring-offset-surface"
                        :class="selectedSize === size.size
                            ? 'border-line-strong bg-navy text-cream'
                            : 'border-line bg-surface-soft text-ink hover:border-line-strong'"
                    >
                        {{ size.size }}
                    </span>
                </label>
            </div>

            <p
                v-if="sizeError"
                class="mt-1.5 text-sm font-semibold text-danger"
                role="alert"
            >
                {{ sizeError }}
            </p>
        </fieldset>

        <p
            v-else-if="sizeEnabled && ! selectedGroup"
            class="flex items-center gap-2 rounded-control border-2 border-dashed border-line px-3.5 py-3 text-sm text-ink-muted"
        >
            <AppIcon
                name="swatch"
                :size="18"
            />
            اختر لونًا لعرض المقاسات المتاحة
        </p>
    </div>
</template>
