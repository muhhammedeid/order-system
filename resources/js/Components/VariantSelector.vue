<script setup>
import { computed, watch } from 'vue';
import AppIcon from '@/Components/Ui/AppIcon.vue';
import { useTranslations } from '@/composables/useTranslations';

const { locale, t } = useTranslations();

const props = defineProps({
    variants: { type: Array, required: true },
    selectedColors: { type: Array, default: () => [] },
    selectedSize: { type: String, default: '' },
    sizeEnabled: { type: Boolean, default: false },
    colorEnabled: { type: Boolean, default: true },
    colorError: { type: String, default: null },
    sizeError: { type: String, default: null },
});

const emit = defineEmits(['update:selectedColors', 'update:selectedSize']);

const selectedGroups = computed(() =>
    props.variants.filter((group) => props.selectedColors.includes(group.color)),
);

const commonSizes = computed(() => {
    if (! selectedGroups.value.length) {
        return [];
    }

    const [first, ...rest] = selectedGroups.value.map((group) =>
        group.sizes.map((variant) => variant.size).filter(Boolean),
    );

    return first.filter((size) => rest.every((sizes) => sizes.includes(size)));
});

watch(
    () => props.selectedColors,
    () => {
        if (! commonSizes.value.includes(props.selectedSize)) {
            emit('update:selectedSize', '');
        }
    },
    { deep: true },
);

function toggleColor(color) {
    const colors = props.selectedColors.includes(color)
        ? props.selectedColors.filter((selected) => selected !== color)
        : [...props.selectedColors, color];

    emit('update:selectedColors', colors);
}

function selectSize(size) {
    emit('update:selectedSize', size);
}
</script>

<template>
    <div class="flex flex-col gap-5">
        <fieldset
            v-if="colorEnabled"
            class="min-w-0"
        >
            <legend class="mb-2 text-sm font-semibold text-ink">
                {{ t('variants.choose_colors') }}
            </legend>

            <div class="flex flex-wrap gap-2">
                <label
                    v-for="group in variants"
                    :key="group.color ?? 'default'"
                    class="cursor-pointer"
                >
                    <input
                        type="checkbox"
                        name="variant-colors"
                        class="peer sr-only"
                        :value="group.color"
                        :checked="selectedColors.includes(group.color)"
                        @change="toggleColor(group.color)"
                    >
                    <span
                        class="inline-flex min-h-11 items-center gap-2 rounded-control border px-4 text-sm font-semibold transition-colors duration-200 ease-out peer-focus-visible:ring-2 peer-focus-visible:ring-ring peer-focus-visible:ring-offset-2 peer-focus-visible:ring-offset-surface"
                        :class="selectedColors.includes(group.color)
                            ? 'border-primary bg-selected text-primary'
                            : 'border-line bg-surface text-ink hover:bg-surface-soft'"
                    >
                        {{ group.color || t('variants.unspecified') }}
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

        <section
            v-else
            class="min-w-0"
        >
            <h3 class="mb-2 text-sm font-semibold text-ink">
                {{ t('variants.available_colors_sizes') }}
            </h3>
            <div class="grid gap-2 sm:grid-cols-2">
                <div
                    v-for="group in variants"
                    :key="group.color ?? 'default'"
                    class="rounded-control border border-line bg-surface px-3.5 py-3"
                >
                    <p class="font-semibold text-ink">{{ group.color || t('variants.unspecified') }}</p>
                    <p
                        v-if="group.sizes.some((variant) => variant.size)"
                        class="mt-1 text-xs text-ink-muted"
                    >
                        {{ t('variants.sizes') }}
                        {{ group.sizes.map((variant) => variant.size).filter(Boolean).join(locale === 'ar' ? '، ' : ', ') }}
                    </p>
                </div>
            </div>
            <p class="mt-2 text-xs text-ink-muted">
                {{ t('variants.all_included') }}
            </p>
        </section>

        <section
            v-if="colorEnabled && ! sizeEnabled && selectedGroups.length"
            class="min-w-0"
        >
            <h3 class="mb-2 text-sm font-semibold text-ink">{{ t('variants.available_sizes') }}</h3>
            <div class="grid gap-2 sm:grid-cols-2">
                <div
                    v-for="group in selectedGroups"
                    :key="group.color"
                    class="rounded-control border border-line bg-surface px-3.5 py-3"
                >
                    <p class="font-semibold text-ink">{{ group.color }}</p>
                    <p class="mt-1 text-xs text-ink-muted">
                        {{ group.sizes.map((variant) => variant.size).filter(Boolean).join(locale === 'ar' ? '، ' : ', ') || t('variants.no_size') }}
                    </p>
                </div>
            </div>
            <p class="mt-2 text-xs text-ink-muted">
                {{ t('variants.display_only') }}
            </p>
        </section>

        <fieldset
            v-if="sizeEnabled && selectedColors.length"
            class="min-w-0"
        >
            <legend class="mb-2 text-sm font-semibold text-ink">
                {{ t('variants.size') }}
            </legend>

            <div
                v-if="commonSizes.length"
                class="flex flex-wrap gap-2"
            >
                <label
                    v-for="size in commonSizes"
                    :key="size"
                    class="cursor-pointer"
                >
                    <input
                        type="radio"
                        name="variant-size"
                        class="peer sr-only"
                        :value="size"
                        :checked="selectedSize === size"
                        @change="selectSize(size)"
                    >
                    <span
                        class="inline-flex min-h-11 min-w-14 items-center justify-center rounded-control border px-3.5 text-sm font-bold tabular-nums transition-colors duration-200 ease-out peer-focus-visible:ring-2 peer-focus-visible:ring-ring peer-focus-visible:ring-offset-2 peer-focus-visible:ring-offset-surface"
                        :class="selectedSize === size
                            ? 'border-primary bg-selected text-primary'
                            : 'border-line bg-surface text-ink hover:bg-surface-soft'"
                    >
                        {{ size }}
                    </span>
                </label>
            </div>
            <p
                v-else
                class="rounded-control border border-dashed border-line-strong/60 px-3.5 py-3 text-sm text-ink-muted"
            >
                {{ t('variants.no_common_size') }}
            </p>

            <p
                v-if="sizeError"
                class="mt-1.5 text-sm font-semibold text-danger"
                role="alert"
            >
                {{ sizeError }}
            </p>
        </fieldset>

        <p
            v-else-if="sizeEnabled"
            class="flex items-center gap-2 rounded-control border border-dashed border-line-strong/60 px-3.5 py-3 text-sm text-ink-muted"
        >
            <AppIcon name="swatch" :size="18" />
            {{ t('variants.choose_color_for_sizes') }}
        </p>
    </div>
</template>
