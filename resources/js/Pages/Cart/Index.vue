<script setup>
import { computed, ref } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import CartItemRow from '@/Components/CartItemRow.vue';
import CartSummary from '@/Components/CartSummary.vue';
import AppButton from '@/Components/Ui/AppButton.vue';
import AppCard from '@/Components/Ui/AppCard.vue';
import ConfirmDialog from '@/Components/Ui/ConfirmDialog.vue';
import EmptyState from '@/Components/Ui/EmptyState.vue';
import { useInertiaLoading } from '@/composables/useInertiaLoading';

const props = defineProps({
    items: {
        type: Array,
        default: () => [],
    },
    total_quantity: {
        type: Number,
        default: 0,
    },
    total_price: {
        type: String,
        default: null,
    },
});

const page = usePage();
const { loading } = useInertiaLoading();

const errors = computed(() => page.props.errors ?? {});
const rowScopedError = computed(() =>
    errors.value.quantity_variant ? errors.value.quantity ?? null : null,
);
const topError = computed(() => {
    if (rowScopedError.value) {
        return null;
    }

    return errors.value.quantity ?? errors.value.cart ?? errors.value.variant_id ?? null;
});

function rowError(item) {
    const flagged = errors.value.quantity_variant;

    if (! flagged || String(flagged) !== String(item.variant_id)) {
        return null;
    }

    return errors.value.quantity ?? null;
}

function updateQuantity(item, quantity) {
    if (quantity < 1 || quantity === item.quantity) {
        return;
    }

    router.post('/cart/update', {
        variant_id: item.variant_id,
        quantity,
    }, {
        preserveScroll: true,
    });
}

const dialog = ref({ open: false, type: null, item: null });

const dialogTitle = computed(() => (dialog.value.type === 'clear'
    ? 'إفراغ الطلب بالكامل؟'
    : 'حذف هذا المنتج من الطلب؟'));

const dialogDescription = computed(() => (dialog.value.type === 'clear'
    ? 'سيتم حذف جميع الأصناف من الطلب ولا يمكن التراجع عن هذه الخطوة.'
    : dialog.value.item?.product?.name ?? null));

function askRemove(item) {
    dialog.value = { open: true, type: 'remove', item };
}

function askClear() {
    dialog.value = { open: true, type: 'clear', item: null };
}

function confirmDialog() {
    const { type, item } = dialog.value;
    dialog.value.open = false;

    if (type === 'remove' && item) {
        router.post('/cart/remove', { variant_id: item.variant_id }, { preserveScroll: true });

        return;
    }

    router.post('/cart/clear', {}, { preserveScroll: true });
}
</script>

<template>
    <StorefrontLayout>
        <Head title="الطلب" />

        <div class="flex flex-col gap-6">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <h1 class="font-display text-3xl font-bold text-ink sm:text-4xl">
                    الطلب
                </h1>
                <p
                    v-if="items.length"
                    class="text-sm text-ink-muted"
                >
                    {{ items.length }} صنف · {{ total_quantity }} قطعة
                </p>
            </div>

            <p
                v-if="topError"
                class="rounded-card border-2 border-danger/40 bg-danger-soft px-4 py-3 text-sm font-semibold text-danger"
                role="alert"
            >
                {{ topError }}
            </p>

            <div
                v-if="items.length"
                class="grid gap-5 lg:grid-cols-[1fr_20rem] lg:items-start"
            >
                <div class="flex flex-col gap-3">
                    <CartItemRow
                        v-for="item in items"
                        :key="item.variant_id"
                        :item="item"
                        :error="rowError(item)"
                        :busy="loading"
                        @update-quantity="updateQuantity"
                        @remove="askRemove"
                    />
                </div>

                <AppCard
                    class="lg:sticky lg:top-24"
                    shadow
                >
                    <h2 class="mb-4 font-display text-xl font-bold text-ink">
                        ملخص الطلب
                    </h2>

                    <CartSummary
                        :items="items"
                        :total-quantity="total_quantity"
                        :total-price="total_price"
                    />

                    <div class="mt-5 flex flex-col gap-2.5">
                        <AppButton
                            href="/checkout"
                            variant="primary"
                            size="lg"
                            icon="check"
                            block
                        >
                            إتمام الطلب
                        </AppButton>
                        <AppButton
                            href="/catalog"
                            variant="secondary"
                            block
                        >
                            مواصلة التسوق
                        </AppButton>
                        <AppButton
                            variant="danger"
                            block
                            icon="trash"
                            @click="askClear"
                        >
                            إفراغ الطلب
                        </AppButton>
                    </div>
                </AppCard>
            </div>

            <EmptyState
                v-else
                icon="cart"
                title="الطلب فارغ"
                description="تصفح المتجر وأضف الأصناف التي تحتاجها، ثم أكمل بياناتك لإرسال الطلب."
            >
                <AppButton
                    href="/catalog"
                    variant="primary"
                    size="lg"
                    icon="search"
                >
                    تسوق الآن
                </AppButton>
            </EmptyState>
        </div>

        <ConfirmDialog
            :open="dialog.open"
            :title="dialogTitle"
            :description="dialogDescription"
            :confirm-label="dialog.type === 'clear' ? 'إفراغ الطلب' : 'حذف'"
            tone="danger"
            @confirm="confirmDialog"
            @cancel="dialog.open = false"
        />
    </StorefrontLayout>
</template>
