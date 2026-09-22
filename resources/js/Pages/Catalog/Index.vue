<script setup>
import { computed, ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import ProductCard from '@/Components/ProductCard.vue';
import ProductCardSkeleton from '@/Components/ProductCardSkeleton.vue';
import AppIcon from '@/Components/Ui/AppIcon.vue';
import AppButton from '@/Components/Ui/AppButton.vue';
import EmptyState from '@/Components/Ui/EmptyState.vue';
import PaginationNav from '@/Components/Ui/PaginationNav.vue';
import { useInertiaLoading } from '@/composables/useInertiaLoading';
import { formatQuantity } from '@/Utils/format';
import { useTranslations } from '@/composables/useTranslations';

const props = defineProps({
    products: {
        type: Object,
        required: true,
    },
    categories: {
        type: Array,
        required: true,
    },
    filters: {
        type: Object,
        required: true,
    },
    meta: {
        type: Object,
        default: () => ({}),
    },
});

const { loading } = useInertiaLoading();
const { t } = useTranslations();

const search = ref(props.filters.search ?? '');
const category = ref(props.filters.category ? String(props.filters.category) : '');

let searchTimer = null;

watch(
    () => props.filters,
    (filters) => {
        search.value = filters.search ?? '';
        category.value = filters.category ? String(filters.category) : '';
    },
);

const hasFilters = computed(() => search.value !== '' || category.value !== '');
const total = computed(() => props.products.total ?? 0);
const from = computed(() => props.products.from ?? 0);
const to = computed(() => props.products.to ?? 0);

const activeCategoryName = computed(
    () => props.categories.find((item) => String(item.id) === category.value)?.name ?? null,
);

function applyFilters(page = 1, options = {}) {
    router.get(
        '/catalog',
        {
            search: search.value || undefined,
            category: category.value || undefined,
            page: page > 1 ? page : undefined,
        },
        {
            preserveState: true,
            preserveScroll: options.preserveScroll ?? true,
            replace: options.replace ?? false,
        },
    );
}

function onSearchInput() {
    window.clearTimeout(searchTimer);
    searchTimer = window.setTimeout(() => applyFilters(1, { replace: true }), 400);
}

function onSearchSubmit() {
    window.clearTimeout(searchTimer);
    applyFilters(1);
}

function clearSearch() {
    search.value = '';
    applyFilters(1);
}

function selectCategory(id) {
    category.value = category.value === String(id) ? '' : String(id);
    applyFilters(1);
}

function clearFilters() {
    search.value = '';
    category.value = '';
    applyFilters(1);
}

function goToPage(page) {
    if (page < 1 || page > props.products.last_page || page === props.products.current_page) {
        return;
    }

    applyFilters(page, { preserveScroll: false });

    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    window.scrollTo({ top: 0, behavior: reducedMotion ? 'auto' : 'smooth' });
}

const GRID = 'grid grid-cols-2 gap-3 sm:gap-4 md:grid-cols-3 xl:grid-cols-4';
</script>

<template>
    <StorefrontLayout>
        <Head :title="meta.title ?? t('catalog.title')" />

        <div class="flex flex-col gap-5">
            <div class="flex flex-col gap-1">
                <h1 class="font-display text-3xl font-bold text-ink sm:text-4xl">
                    {{ activeCategoryName ?? (search ? t('catalog.search_results', { search }) : t('catalog.all_products')) }}
                </h1>
                <p
                    v-if="total > 0"
                    class="text-sm text-ink-muted"
                >
                    {{ t('catalog.range', {
                        from: formatQuantity(from),
                        to: formatQuantity(to),
                        total: formatQuantity(total),
                    }) }}
                </p>
            </div>

            <div class="flex flex-col gap-4 rounded-card border border-line bg-surface p-4 shadow-soft-sm">
                <form
                    class="flex flex-col gap-2 sm:flex-row"
                    role="search"
                    @submit.prevent="onSearchSubmit"
                >
                    <div class="relative flex-1">
                        <AppIcon
                            name="search"
                            :size="20"
                            class="pointer-events-none absolute start-3.5 top-1/2 -translate-y-1/2 text-ink-muted"
                        />
                        <input
                            v-model="search"
                            type="search"
                            inputmode="search"
                            :placeholder="t('catalog.search_placeholder')"
                            :aria-label="t('catalog.search_label')"
                            class="min-h-12 w-full rounded-control border-2 border-line-strong bg-surface ps-11 pe-14 text-base text-ink placeholder:text-ink-muted transition-colors duration-200 ease-out hover:border-ink-muted"
                            @input="onSearchInput"
                        >
                        <button
                            v-if="search"
                            type="button"
                            class="absolute end-1.5 top-1/2 flex h-11 w-11 -translate-y-1/2 items-center justify-center rounded-full text-ink-muted transition-colors duration-200 ease-out hover:bg-surface-muted hover:text-ink"
                            :aria-label="t('catalog.clear_search')"
                            @click="clearSearch"
                        >
                            <AppIcon
                                name="close"
                                :size="18"
                            />
                        </button>
                    </div>

                    <AppButton
                        type="submit"
                        icon="search"
                        :loading="loading"
                        :disable-while-loading="false"
                    >
                        {{ t('catalog.search') }}
                    </AppButton>
                </form>

                <div
                    v-if="categories.length"
                    class="flex flex-wrap items-center gap-2"
                    role="group"
                    :aria-label="t('catalog.filter_categories')"
                >
                    <button
                        type="button"
                        class="inline-flex min-h-11 items-center rounded-full border px-3.5 text-sm font-semibold whitespace-nowrap transition-colors duration-200 ease-out"
                        :class="category === ''
                            ? 'border-primary bg-selected text-primary'
                            : 'border-line bg-surface text-ink hover:bg-surface-soft'"
                        :aria-pressed="category === ''"
                        @click="selectCategory('')"
                    >
                        {{ t('catalog.all_categories') }}
                    </button>

                    <button
                        v-for="item in categories"
                        :key="item.id"
                        type="button"
                        class="inline-flex min-h-11 items-center gap-1.5 rounded-full border px-3.5 text-sm font-semibold whitespace-nowrap transition-colors duration-200 ease-out"
                        :class="category === String(item.id)
                            ? 'border-primary bg-selected text-primary'
                            : 'border-line bg-surface text-ink hover:bg-surface-soft'"
                        :aria-pressed="category === String(item.id)"
                        @click="selectCategory(item.id)"
                    >
                        {{ item.name }}
                        <span class="text-xs tabular-nums opacity-70">
                            {{ formatQuantity(item.products_count ?? 0) }}
                        </span>
                    </button>

                    <button
                        v-if="hasFilters"
                        type="button"
                        class="inline-flex min-h-11 items-center gap-1.5 rounded-full border border-transparent px-3 text-sm font-semibold text-primary transition-colors duration-200 ease-out hover:bg-surface-muted"
                        @click="clearFilters"
                    >
                        <AppIcon
                            name="close"
                            :size="16"
                        />
                        {{ t('catalog.clear_filters') }}
                    </button>
                </div>
            </div>

            <h2 class="sr-only">
                {{ t('catalog.products') }}
            </h2>

            <div
                v-if="loading && ! products.data.length"
                :class="GRID"
                aria-hidden="true"
            >
                <ProductCardSkeleton
                    v-for="index in 8"
                    :key="index"
                />
            </div>

            <div
                v-else-if="products.data.length"
                :class="GRID"
                :aria-busy="loading ? 'true' : 'false'"
            >
                <ProductCard
                    v-for="product in products.data"
                    :key="product.id"
                    :product="product"
                />
            </div>

            <EmptyState
                v-else
                icon="search"
                :title="t('catalog.empty_title')"
                :description="t('catalog.empty_description')"
            >
                <AppButton
                    v-if="hasFilters"
                    variant="primary"
                    icon="close"
                    @click="clearFilters"
                >
                    {{ t('catalog.clear_filters') }}
                </AppButton>
                <AppButton
                    variant="secondary"
                    href="/catalog"
                >
                    {{ t('catalog.show_all') }}
                </AppButton>
            </EmptyState>

            <PaginationNav
                v-if="products.last_page > 1"
                :current-page="products.current_page"
                :last-page="products.last_page"
                class="pt-2"
                @change="goToPage"
            />
        </div>
    </StorefrontLayout>
</template>
