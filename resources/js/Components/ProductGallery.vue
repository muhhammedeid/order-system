<script setup>
import { computed, ref, watch } from 'vue';
import AppIcon from '@/Components/Ui/AppIcon.vue';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();

const props = defineProps({
    images: {
        type: Array,
        required: true,
    },
    alt: {
        type: String,
        default: '',
    },
});

const mainIndex = ref(0);

watch(
    () => props.images,
    () => {
        mainIndex.value = 0;
    },
);

const hasImages = computed(() => props.images.length > 0);

const mainImage = computed(() => props.images[mainIndex.value] ?? null);

function step(delta) {
    if (! hasImages.value) {
        return;
    }

    const count = props.images.length;
    mainIndex.value = (mainIndex.value + delta + count) % count;
}
</script>

<template>
    <div class="flex flex-col gap-3">
        <div class="group relative aspect-square w-full overflow-hidden rounded-card border-2 border-line bg-surface-muted">
            <img
                v-if="mainImage"
                :src="mainImage"
                :alt="alt"
                class="h-full w-full object-cover"
                decoding="async"
            >
            <div
                v-else
                class="flex h-full w-full items-center justify-center text-ink-muted/50"
            >
                <AppIcon
                    name="swatch"
                    :size="56"
                />
            </div>

            <template v-if="images.length > 1">
                <button
                    type="button"
                    class="absolute start-2 top-1/2 flex h-11 w-11 -translate-y-1/2 items-center justify-center rounded-full border-2 border-line-strong bg-surface-soft/95 text-ink opacity-0 transition-opacity duration-200 group-hover:opacity-100 focus-visible:opacity-100"
                    :aria-label="t('gallery.previous')"
                    @click="step(-1)"
                >
                    <AppIcon
                        name="chevron-right"
                        :size="20"
                    />
                </button>
                <button
                    type="button"
                    class="absolute end-2 top-1/2 flex h-11 w-11 -translate-y-1/2 items-center justify-center rounded-full border-2 border-line-strong bg-surface-soft/95 text-ink opacity-0 transition-opacity duration-200 group-hover:opacity-100 focus-visible:opacity-100"
                    :aria-label="t('gallery.next')"
                    @click="step(1)"
                >
                    <AppIcon
                        name="chevron-left"
                        :size="20"
                    />
                </button>

                <span class="absolute bottom-2 end-2 rounded-full border-2 border-line-strong bg-surface-soft/95 px-2.5 py-1 text-xs font-semibold tabular-nums text-ink">
                    {{ mainIndex + 1 }} / {{ images.length }}
                </span>
            </template>
        </div>

        <div
            v-if="images.length > 1"
            class="grid grid-cols-5 gap-2"
            role="group"
            :aria-label="t('gallery.group')"
        >
            <button
                v-for="(image, index) in images"
                :key="image"
                type="button"
                class="aspect-square overflow-hidden rounded-control border-2 bg-surface-muted transition-opacity duration-150"
                :class="index === mainIndex
                    ? 'border-line-strong'
                    : 'border-line opacity-75 hover:opacity-100'"
                :aria-label="t('gallery.show', { number: index + 1 })"
                :aria-current="index === mainIndex ? 'true' : undefined"
                @click="mainIndex = index"
            >
                <img
                    :src="image"
                    :alt="t('gallery.image_alt', { alt, number: index + 1 })"
                    class="h-full w-full object-cover"
                    loading="lazy"
                    decoding="async"
                >
            </button>
        </div>
    </div>
</template>
