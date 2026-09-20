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
        <Head :title="meta.title ?? 'المتجر'" />

        <div class="flex flex-col gap-5">
            <div class="flex flex-col gap-1">
                <h1 class="font-display text-3xl font-bold text-ink sm:text-4xl">
                    {{ activeCategoryName ?? (search ? `نتائج البحث: ${search}` : 'كل المنتجات') }}
                </h1>
                <p
                    v-if="total > 0"
                    class="text-sm text-ink-muted"
                >
                    عرض {{ formatQuantity(from) }}–{{ formatQuantity(to) }} من {{ formatQuantity(total) }} منتج
                </p>
            </div>

            <div class="flex flex-col gap-4 rounded-card border-2 border-line bg-surface-soft p-4 shadow-retro-sm">
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
                            placeholder="ابحث بالاسم أو كود المنتج"
                            aria-label="البحث في المنتجات"
                            class="min-h-12 w-full rounded-control border-2 border-line bg-surface ps-11 pe-11 text-base text-ink placeholder:text-ink-muted/60 hover:border-ink-muted focus:border-line-strong"
                            @input="onSearchInput"
                        >
                        <button
                            v-if="search"
                            type="button"
                            class="absolute end-2 top-1/2 flex h-9 w-9 -translate-y-1/2 items-center justify-center rounded-full text-ink-muted transition-colors hover:bg-surface-muted hover:text-ink"
                            aria-label="مسح البحث"
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
                        بحث
                    </AppButton>
                </form>

                <div
                    v-if="categories.length"
                    class="flex flex-wrap items-center gap-2"
                    role="group"
                    aria-label="تصفية حسب الفئة"
                >
                    <button
                        type="button"
                        class="inline-flex min-h-11 items-center rounded-full border-2 px-3.5 text-sm font-semibold whitespace-nowrap transition-colors"
                        :class="category === ''
                            ? 'border-line-strong bg-navy text-cream'
                            : 'border-line bg-surface text-ink hover:border-line-strong'"
                        :aria-pressed="category === ''"
                        @click="selectCategory('')"
                    >
                        كل الفئات
                    </button>

                    <button
                        v-for="item in categories"
                        :key="item.id"
                        type="button"
                        class="inline-flex min-h-11 items-center gap-1.5 rounded-full border-2 px-3.5 text-sm font-semibold whitespace-nowrap transition-colors"
                        :class="category === String(item.id)
                            ? 'border-line-strong bg-navy text-cream'
                            : 'border-line bg-surface text-ink hover:border-line-strong'"
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
                        class="inline-flex min-h-11 items-center gap-1.5 rounded-full border-2 border-transparent px-3 text-sm font-semibold text-primary transition-colors hover:bg-surface-muted"
                        @click="clearFilters"
                    >
                        <AppIcon
                            name="close"
                            :size="16"
                        />
                        مسح الفلاتر
                    </button>
                </div>
            </div>

            <h2 class="sr-only">
                المنتجات
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
                title="لا توجد منتجات مطابقة"
                description="جرّب كلمات بحث أقصر أو اختر فئة مختلفة."
            >
                <AppButton
                    v-if="hasFilters"
                    variant="primary"
                    icon="close"
                    @click="clearFilters"
                >
                    مسح الفلاتر
                </AppButton>
                <AppButton
                    variant="secondary"
                    href="/catalog"
                >
                    عرض كل المنتجات
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
