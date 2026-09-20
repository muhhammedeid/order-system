<script setup>
import { computed, ref, watch } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import ProductGallery from '@/Components/ProductGallery.vue';
import ProductCard from '@/Components/ProductCard.vue';
import VariantSelector from '@/Components/VariantSelector.vue';
import WhatsAppPriceButton from '@/Components/WhatsAppPriceButton.vue';
import AppBadge from '@/Components/Ui/AppBadge.vue';
import AppButton from '@/Components/Ui/AppButton.vue';
import AppIcon from '@/Components/Ui/AppIcon.vue';
import Breadcrumbs from '@/Components/Ui/Breadcrumbs.vue';
import PriceTag from '@/Components/Ui/PriceTag.vue';
import QuantityPicker from '@/Components/Ui/QuantityPicker.vue';

const props = defineProps({
    product: {
        type: Object,
        required: true,
    },
    variants: {
        type: Array,
        default: () => [],
    },
    whatsapp: {
        type: Object,
        default: null,
    },
    related: {
        type: Array,
        default: () => [],
    },
    meta: {
        type: Object,
        default: () => ({}),
    },
});

const selectedColor = ref(props.variants.length ? props.variants[0].color : '');
const selectedSize = ref('');
const sizeError = ref(null);
const quantityError = ref(null);

const form = useForm({
    variant_id: null,
    quantity: 5,
});

const sizeEnabled = computed(() => Boolean(props.product.size_enabled));

const flatVariants = computed(() =>
    props.variants.flatMap((group) =>
        group.sizes.map((size) => ({ ...size, color: group.color })),
    ),
);

const selectedVariant = computed(() => {
    if (! selectedColor.value) {
        return null;
    }

    if (! sizeEnabled.value) {
        return flatVariants.value.find(
            (variant) => variant.color === selectedColor.value,
        ) ?? null;
    }

    return flatVariants.value.find(
        (variant) => variant.color === selectedColor.value && variant.size === selectedSize.value,
    ) ?? null;
});

const breadcrumbs = computed(() => [
    { label: 'الرئيسية', href: '/' },
    { label: 'المتجر', href: '/catalog' },
    ...(props.product.category
        ? [{ label: props.product.category.name, href: `/catalog?category=${props.product.category.id}` }]
        : []),
    { label: props.product.name },
]);

watch(selectedSize, () => {
    sizeError.value = null;
    quantityError.value = null;
    form.clearErrors();
});

function addToOrder() {
    if (! selectedVariant.value) {
        sizeError.value = sizeEnabled.value
            ? 'اختر المقاس المطلوب أولًا'
            : 'اختر اللون المطلوب أولًا';

        return;
    }

    if (! Number.isInteger(form.quantity) || form.quantity < 1) {
        quantityError.value = 'أدخل كمية صحيحة أكبر من صفر';

        return;
    }

    sizeError.value = null;
    quantityError.value = null;
    form.variant_id = selectedVariant.value.id;

    form.post('/cart/add', {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            form.variant_id = null;
        },
    });
}

const pickerError = computed(
    () => quantityError.value ?? form.errors.quantity ?? form.errors.variant_id ?? null,
);
</script>

<template>
    <StorefrontLayout>
        <Head :title="meta.title ?? product.name" />

        <div class="flex flex-col gap-6">
            <Breadcrumbs :items="breadcrumbs" />

            <div class="grid grid-cols-1 gap-8 lg:grid-cols-2 lg:gap-10">
                <ProductGallery
                    :images="product.images"
                    :alt="product.name"
                />

                <div class="flex flex-col gap-5 lg:sticky lg:top-24 lg:self-start">
                    <div class="flex flex-wrap items-center gap-2">
                        <AppBadge
                            v-if="product.category"
                            tone="powder-soft"
                        >
                            {{ product.category.name }}
                        </AppBadge>
                        <span
                            class="font-mono text-sm text-ink-muted"
                            dir="ltr"
                        >
                            {{ product.product_code }}
                        </span>
                    </div>

                    <h1 class="font-display text-3xl font-bold leading-tight text-ink sm:text-4xl">
                        {{ product.name }}
                    </h1>

                    <div class="flex flex-wrap items-center gap-3">
                        <PriceTag
                            :value="product.price"
                            :visibility="product.price_visibility"
                            size="lg"
                        />
                    </div>

                    <WhatsAppPriceButton
                        v-if="whatsapp"
                        :href="whatsapp.href"
                    />

                    <div
                        v-if="variants.length"
                        class="flex flex-col gap-5 rounded-card border-2 border-line bg-surface-soft p-4 shadow-retro-sm sm:p-5"
                    >
                        <VariantSelector
                            v-model:selected-color="selectedColor"
                            v-model:selected-size="selectedSize"
                            :variants="variants"
                            :size-enabled="sizeEnabled"
                            :color-error="! sizeEnabled ? sizeError : null"
                            :size-error="sizeEnabled ? sizeError : null"
                        />

                        <div
                            v-if="selectedVariant"
                            class="flex flex-col gap-3 rounded-control border-2 border-line bg-surface p-3.5"
                        >
                            <p class="text-sm text-ink">
                                <span class="font-semibold">{{ selectedColor }}</span>
                                <template v-if="sizeEnabled && selectedSize">
                                    <span class="text-ink-muted"> / </span>
                                    <span class="font-semibold tabular-nums">{{ selectedSize }}</span>
                                </template>
                            </p>

                            <QuantityPicker
                                v-model="form.quantity"
                                :busy="form.processing"
                                :error="pickerError"
                            />
                        </div>

                        <p
                            v-else-if="sizeError"
                            class="rounded-control border-2 border-danger/40 bg-danger-soft px-3.5 py-2.5 text-sm font-semibold text-danger"
                            role="alert"
                        >
                            {{ sizeError }}
                        </p>

                        <AppButton
                            variant="primary"
                            size="lg"
                            icon="cart"
                            block
                            :loading="form.processing"
                            @click="addToOrder"
                        >
                            أضف إلى الطلب
                        </AppButton>

                        <p class="flex items-start gap-2 text-xs text-ink-muted">
                            <AppIcon
                                name="info"
                                :size="16"
                                class="mt-0.5"
                            />
                            إرسال الطلب لا يعني إتمام البيع أو الدفع — سيتم تأكيد الطلب والتواصل معك من فريق المبيعات.
                        </p>
                    </div>

                    <p
                        v-else
                        class="rounded-card border-2 border-dashed border-line px-4 py-6 text-center text-sm text-ink-muted"
                    >
                        لا توجد ألوان مسجلة لهذا المنتج حاليًا — تواصل معنا عبر واتساب لمعرفة المتاح.
                    </p>

                    <div
                        v-if="product.description"
                        class="rounded-card border-2 border-line bg-surface-soft p-4"
                    >
                        <h2 class="mb-1.5 font-display text-lg font-bold text-ink">
                            تفاصيل المنتج
                        </h2>
                        <p class="whitespace-pre-line text-sm leading-relaxed text-ink-muted">
                            {{ product.description }}
                        </p>
                    </div>
                </div>
            </div>

            <section
                v-if="related.length"
                class="flex flex-col gap-4 pt-4"
            >
                <h2 class="font-display text-2xl font-bold text-ink">
                    منتجات مشابهة
                </h2>

                <div class="grid grid-cols-2 gap-3 sm:gap-4 md:grid-cols-3 xl:grid-cols-4">
                    <ProductCard
                        v-for="item in related"
                        :key="item.id"
                        :product="item"
                    />
                </div>
            </section>
        </div>
    </StorefrontLayout>
</template>
