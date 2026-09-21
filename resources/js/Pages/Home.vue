<script setup>
import { computed } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import ProductCard from '@/Components/ProductCard.vue';
import AppButton from '@/Components/Ui/AppButton.vue';
import AppIcon from '@/Components/Ui/AppIcon.vue';
import { formatQuantity } from '@/Utils/format';

const props = defineProps({
    products: {
        type: Array,
        default: () => [],
    },
    categories: {
        type: Array,
        default: () => [],
    },
    stats: {
        type: Object,
        default: () => ({}),
    },
    meta: {
        type: Object,
        default: () => ({}),
    },
});

const page = usePage();
const whatsapp = computed(() => page.props.whatsapp ?? null);

const whatsappHref = computed(() =>
    whatsapp.value ? `https://wa.me/${whatsapp.value}?text=${encodeURIComponent('مرحبًا، أريد الاستفسار عن أسعار الجملة من MAI SHOES')}` : null,
);

const STEPS = [
    { icon: 'search', title: 'تصفح المتجر', text: 'اختر الفئة أو ابحث بكود المنتج.' },
    { icon: 'swatch', title: 'حدد الكمية', text: 'شاهد الالوان المتاحة للمنتج قبل الطلب.' },
    { icon: 'cart', title: 'أضف إلى الطلب', text: 'عدّل الكميات وراجع ملخص الطلب.' },
    { icon: 'check-circle', title: 'أرسل الطلب', text: 'يؤكد فريق المبيعات الطلب ويتواصل معك.' },
];

const FEATURES = [
    { icon: 'shield', title: 'جملة فقط', text: 'الأسعار والكميات مخصصة لتجار التجزئة.' },
    { icon: 'truck', title: 'بدون دفع إلكتروني', text: 'الطلب يُسجل ويتم الاتفاق على التفاصيل مع فريق المبيعات.' },
    { icon: 'sparkles', title: 'أسعار خاصة', text: 'بعض المنتجات بأسعار عند الطلب تُعرف مباشرة عبر واتساب.' },
];
</script>

<template>
    <StorefrontLayout full>
        <Head :title="meta.title ?? 'الرئيسية'" />

        <section class="relative overflow-hidden border-b-4 border-line-strong bg-crimson text-cream">
            <AppIcon
                name="sparkles"
                :size="360"
                class="pointer-events-none absolute -start-24 -top-24 text-cream/10"
            />

            <div class="relative mx-auto grid w-full max-w-7xl gap-8 px-4 py-12 sm:px-6 sm:py-16 lg:grid-cols-[1.1fr_0.9fr] lg:items-center lg:px-8">
                <div class="flex flex-col items-start gap-5">
                    <span class="inline-flex -rotate-2 items-center gap-2 rounded-full border-2 border-cream/40 bg-burgundy px-3.5 py-1.5 text-sm font-bold shadow-retro-sm">
                        <AppIcon
                            name="sparkles"
                            :size="16"
                        />
                        أسعار جملة — انتاج مصنعنا
                    </span>

                    <h1 class="font-retro text-5xl leading-[1.15] text-cream sm:text-6xl lg:text-7xl">
                         تصفح المنتجات وأطلب<br>
                        <span class="text-powder">في دقائق</span>
                    </h1>

                    <p class="max-w-xl text-lg leading-relaxed text-cream/90">
                        تصفح تشكيلة MAI SHOES، اعرف الألوان والمقاسات والكميات المتاحة لكل منتج،
                        وأرسل طلبك في خطوات بسيطة — وفريقنا يؤكد الطلب ويتواصل معك.
                    </p>

                    <div class="flex flex-wrap items-center gap-3">
                        <AppButton
                            href="/catalog"
                            variant="powder"
                            size="lg"
                            icon="search"
                        >
                            تصفح المتجر
                        </AppButton>
                        <a
                            v-if="whatsappHref"
                            :href="whatsappHref"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex min-h-14 items-center gap-2.5 rounded-control border-2 border-cream/40 px-6 text-lg font-bold text-cream transition-colors duration-150 hover:bg-cream/10"
                        >
                            <AppIcon
                                name="whatsapp"
                                :size="22"
                            />
                            تواصل واتساب
                        </a>
                    </div>

                    <dl class="flex flex-wrap gap-3 pt-1">
                        <div class="rounded-control border-2 border-cream/30 px-3.5 py-2">
                            <dt class="text-xs text-cream/75">منتجات متاحة</dt>
                            <dd class="font-display text-xl font-bold tabular-nums">
                                {{ formatQuantity(stats.products ?? 0) }}
                            </dd>
                        </div>
                        <div
                            v-if="stats.categories"
                            class="rounded-control border-2 border-cream/30 px-3.5 py-2"
                        >
                            <dt class="text-xs text-cream/75">فئات</dt>
                            <dd class="font-display text-xl font-bold tabular-nums">
                                {{ formatQuantity(stats.categories) }}
                            </dd>
                        </div>
                    </dl>
                </div>

                <div
                    v-if="products.length"
                    class="grid grid-cols-2 gap-3"
                >
                    <ProductCard
                        v-for="product in products.slice(0, 2)"
                        :key="product.id"
                        :product="product"
                    />
                </div>
            </div>
        </section>

        <section class="mx-auto w-full max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
            <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
                <h2 class="font-display text-3xl font-bold text-ink sm:text-4xl">
                    تسوق حسب الفئة
                </h2>
                <AppButton
                    href="/catalog"
                    variant="secondary"
                    size="sm"
                    icon="chevron-left"
                >
                    كل المنتجات
                </AppButton>
            </div>

            <div
                v-if="categories.length"
                class="grid grid-cols-2 gap-3 sm:gap-4 md:grid-cols-3 lg:grid-cols-4"
            >
                <a
                    v-for="category in categories"
                    :key="category.id"
                    :href="`/catalog?category=${category.id}`"
                    class="group flex items-center justify-between gap-3 rounded-card border-2 border-line bg-surface-soft p-4 transition-[transform,border-color,box-shadow] duration-200 hover:-translate-y-0.5 hover:border-line-strong hover:shadow-retro-sm"
                >
                    <span class="flex flex-col gap-0.5">
                        <span class="font-display text-lg font-bold text-ink">
                            {{ category.name }}
                        </span>
                        <span class="text-xs text-ink-muted tabular-nums">
                            {{ formatQuantity(category.products_count ?? 0) }} منتج
                        </span>
                    </span>
                    <AppIcon
                        name="chevron-left"
                        :size="20"
                        class="text-ink-muted transition-transform duration-200 group-hover:-translate-x-0.5"
                    />
                </a>
            </div>
            <p
                v-else
                class="rounded-card border-2 border-dashed border-line px-4 py-10 text-center text-sm text-ink-muted"
            >
                لا توجد فئات متاحة حاليًا.
            </p>
        </section>

        <section
            v-if="products.length"
            class="border-y-4 border-line bg-surface-muted/60"
        >
            <div class="mx-auto w-full max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
                <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
                    <h2 class="font-display text-3xl font-bold text-ink sm:text-4xl">
                        أحدث المنتجات
                    </h2>
                    <AppButton
                        href="/catalog"
                        variant="primary"
                        size="sm"
                        icon="chevron-left"
                    >
                        عرض الكل
                    </AppButton>
                </div>

                <div class="grid grid-cols-2 gap-3 sm:gap-4 md:grid-cols-3 xl:grid-cols-4">
                    <ProductCard
                        v-for="product in products"
                        :key="product.id"
                        :product="product"
                    />
                </div>
            </div>
        </section>

        <section class="mx-auto w-full max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
            <h2 class="mb-6 font-display text-3xl font-bold text-ink sm:text-4xl">
                كيف يعمل الطلب؟
            </h2>

            <ol class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <li
                    v-for="(step, index) in STEPS"
                    :key="step.title"
                    class="flex flex-col gap-3 rounded-card border-2 border-line bg-surface-soft p-4"
                >
                    <span class="flex h-11 w-11 items-center justify-center rounded-control border-2 border-line-strong bg-powder font-display text-lg font-bold text-powder-ink">
                        {{ index + 1 }}
                    </span>
                    <span class="flex items-center gap-2 font-display text-lg font-bold text-ink">
                        <AppIcon
                            :name="step.icon"
                            :size="20"
                            class="text-ink-muted"
                        />
                        {{ step.title }}
                    </span>
                    <span class="text-sm text-ink-muted">{{ step.text }}</span>
                </li>
            </ol>
        </section>

        <section class="mx-auto w-full max-w-7xl px-4 pb-14 sm:px-6 lg:px-8">
            <div class="grid gap-3 sm:grid-cols-3">
                <div
                    v-for="feature in FEATURES"
                    :key="feature.title"
                    class="flex flex-col gap-2 rounded-card border-2 border-line-strong bg-surface-soft p-4 shadow-retro-sm"
                >
                    <AppIcon
                        :name="feature.icon"
                        :size="24"
                        class="text-primary"
                    />
                    <h3 class="font-display text-lg font-bold text-ink">
                        {{ feature.title }}
                    </h3>
                    <p class="text-sm text-ink-muted">{{ feature.text }}</p>
                </div>
            </div>
        </section>
    </StorefrontLayout>
</template>
