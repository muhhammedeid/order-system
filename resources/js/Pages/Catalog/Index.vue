<script setup>
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import ProductCard from '@/Components/ProductCard.vue';

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
});

const search = ref(props.filters.search ?? '');
const category = ref(props.filters.category ?? '');

watch(
    () => props.filters,
    (filters) => {
        search.value = filters.search ?? '';
        category.value = filters.category ?? '';
    },
);

function applyFilters(page = 1) {
    router.get(
        '/catalog',
        {
            search: search.value,
            category: category.value,
            page: page > 1 ? page : undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
        },
    );
}

function onSearch() {
    applyFilters(1);
}

function onCategoryChange() {
    applyFilters(1);
}

function goToPage(page) {
    if (page < 1 || page > props.products.last_page) {
        return;
    }

    applyFilters(page);
}
</script>

<template>
    <StorefrontLayout>
        <div class="flex flex-col gap-4">
            <div class="flex flex-col gap-2 sm:flex-row">
                <form class="flex flex-1" @submit.prevent="onSearch">
                    <input
                        v-model="search"
                        type="search"
                        placeholder="ابحث بالاسم أو الكود"
                        class="w-full rounded-s-lg border border-gray-300 px-3 py-2 text-gray-900 placeholder:text-gray-400 focus:border-gray-500 focus:outline-none"
                    >
                    <button
                        type="submit"
                        class="rounded-e-lg bg-gray-900 px-4 py-2 text-white hover:bg-gray-800"
                    >
                        بحث
                    </button>
                </form>

                <select
                    v-model="category"
                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 focus:border-gray-500 focus:outline-none sm:w-56"
                    @change="onCategoryChange"
                >
                    <option value="">كل الفئات</option>
                    <option
                        v-for="category in categories"
                        :key="category.id"
                        :value="category.id"
                    >
                        {{ category.name }}
                    </option>
                </select>
            </div>

            <div
                v-if="products.data.length"
                class="grid grid-cols-2 gap-3 md:grid-cols-3 lg:grid-cols-4"
            >
                <ProductCard
                    v-for="product in products.data"
                    :key="product.id"
                    :product="product"
                />
            </div>
            <p v-else class="py-16 text-center text-lg text-gray-500">
                لا توجد منتجات مطابقة
            </p>

            <div
                v-if="products.last_page > 1"
                class="flex items-center justify-center gap-4 pt-4"
            >
                <button
                    type="button"
                    class="rounded-lg border border-gray-300 px-4 py-2 text-gray-700 hover:bg-gray-100 disabled:opacity-40"
                    :disabled="products.current_page <= 1"
                    @click="goToPage(products.current_page - 1)"
                >
                    السابق
                </button>
                <span class="text-sm text-gray-600">
                    صفحة {{ products.current_page }} من {{ products.last_page }}
                </span>
                <button
                    type="button"
                    class="rounded-lg border border-gray-300 px-4 py-2 text-gray-700 hover:bg-gray-100 disabled:opacity-40"
                    :disabled="products.current_page >= products.last_page"
                    @click="goToPage(products.current_page + 1)"
                >
                    التالي
                </button>
            </div>
        </div>
    </StorefrontLayout>
</template>
