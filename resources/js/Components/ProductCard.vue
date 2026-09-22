<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppIcon from '@/Components/Ui/AppIcon.vue';
import PriceTag from '@/Components/Ui/PriceTag.vue';
import { formatQuantity } from '@/Utils/format';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();

const props = defineProps({
    product: {
        type: Object,
        required: true,
    },
});

const colorsLabel = computed(() => {
    const colors = Number(props.product.colors_count ?? 0);

    if (colors <= 0) {
        return null;
    }

    return colors === 1
        ? t('catalog.one_color')
        : t('catalog.colors', { count: formatQuantity(colors) });
});
</script>

<template>
    <Link
        :href="`/product/${product.slug}`"
        class="group flex flex-col overflow-hidden rounded-card border border-line bg-surface shadow-soft-sm transition-[transform,border-color,box-shadow] duration-200 ease-out hover:-translate-y-0.5 hover:shadow-soft"
    >
        <div class="relative aspect-square w-full overflow-hidden bg-surface-muted">
            <img
                v-if="product.image"
                :src="product.image"
                :alt="product.name"
                class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
                loading="lazy"
                decoding="async"
            >
            <div
                v-else
                class="flex h-full w-full items-center justify-center text-ink-muted"
            >
                <AppIcon
                    name="swatch"
                    :size="42"
                />
            </div>
        </div>

        <div class="flex flex-1 flex-col gap-1 p-3">
            <p
                v-if="product.category"
                class="text-xs font-semibold text-ink-muted"
            >
                {{ product.category.name }}
            </p>

            <h3 class="font-display text-base font-bold leading-snug text-ink line-clamp-2">
                {{ product.name }}
            </h3>

            <p
                class="font-mono text-xs text-ink-muted"
                dir="ltr"
            >
                {{ product.product_code }}
            </p>

            <div class="mt-auto flex flex-wrap items-end justify-between gap-2 pt-2">
                <PriceTag
                    :value="product.price"
                    :visibility="product.price_visibility"
                    size="sm"
                />

                <span
                    v-if="colorsLabel"
                    class="text-xs font-semibold text-ink-muted"
                >
                    {{ colorsLabel }}
                </span>
            </div>
        </div>
    </Link>
</template>
