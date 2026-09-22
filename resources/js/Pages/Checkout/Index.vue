<script setup>
import { computed, nextTick, ref } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import CartSummary from '@/Components/CartSummary.vue';
import AppButton from '@/Components/Ui/AppButton.vue';
import AppCard from '@/Components/Ui/AppCard.vue';
import AppIcon from '@/Components/Ui/AppIcon.vue';
import AppInput from '@/Components/Ui/AppInput.vue';
import AppTextarea from '@/Components/Ui/AppTextarea.vue';
import { useTranslations } from '@/composables/useTranslations';

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
const { t } = useTranslations();
const whatsapp = computed(() => page.props.whatsapp ?? null);

const GOVERNORATE_KEYS = [
    'cairo', 'giza', 'alexandria', 'dakahlia', 'sharqia', 'qalyubia', 'monufia', 'gharbia',
    'kafr_el_sheikh', 'damietta', 'port_said', 'ismailia', 'suez', 'beheira', 'fayoum',
    'beni_suef', 'minya', 'assiut', 'sohag', 'qena', 'luxor', 'aswan', 'red_sea', 'matrouh',
    'north_sinai', 'south_sinai', 'new_valley',
];
const governorates = computed(() => GOVERNORATE_KEYS.map((key) => t(`checkout.governorates.${key}`)));

const form = useForm({
    name: '',
    phone: '',
    company_name: '',
    whatsapp: '',
    governorate: '',
    city: '',
    address: '',
    customer_notes: '',
});

const summary = ref(null);

const errorList = computed(() =>
    Object.entries(form.errors).map(([field, message]) => ({ field, message })),
);

function submit() {
    form.post('/checkout', {
        preserveScroll: true,
        onError: () => {
            nextTick(() => summary.value?.focus());
        },
    });
}
</script>

<template>
    <StorefrontLayout>
        <Head :title="t('checkout.title')" />

        <div class="flex flex-col gap-6">
            <h1 class="font-display text-3xl font-bold text-ink sm:text-4xl">
                {{ t('checkout.title') }}
            </h1>

            <div
                v-if="! items.length"
                class="flex flex-col items-center gap-4 rounded-card border border-dashed border-line-strong/60 px-6 py-16 text-center"
            >
                <p class="text-lg text-ink-muted">{{ t('checkout.empty') }}</p>
                <AppButton
                    href="/catalog"
                    variant="primary"
                    icon="search"
                >
                    {{ t('checkout.shop_now') }}
                </AppButton>
            </div>

            <template v-else>
                <div class="grid gap-5 lg:grid-cols-[1fr_20rem] lg:items-start">
                    <form
                        class="flex flex-col gap-5"
                        novalidate
                        @submit.prevent="submit"
                    >
                        <div
                            v-if="errorList.length"
                            ref="summary"
                            tabindex="-1"
                            class="rounded-card border border-danger bg-danger-soft px-4 py-3.5"
                            role="alert"
                            aria-labelledby="error-summary-title"
                        >
                            <p
                                id="error-summary-title"
                                class="flex items-center gap-2 font-bold text-danger"
                            >
                                <AppIcon
                                    name="alert"
                                    :size="20"
                                />
                                {{ t('checkout.fix_errors') }}
                            </p>
                            <ul class="mt-2 list-inside list-disc text-sm font-semibold text-danger">
                                <li
                                    v-for="error in errorList"
                                    :key="error.field"
                                >
                                    {{ error.message }}
                                </li>
                            </ul>
                        </div>

                        <AppCard
                            as="section"
                            shadow
                        >
                            <h2 class="mb-1 font-display text-xl font-bold text-ink">
                                {{ t('checkout.contact_details') }}
                            </h2>
                            <p class="mb-4 text-sm text-ink-muted">
                                {{ t('checkout.contact_intro') }}
                            </p>

                            <div class="grid gap-4 sm:grid-cols-2">
                                <AppInput
                                    v-model="form.name"
                                    :label="t('checkout.name')"
                                    name="name"
                                    autocomplete="name"
                                    required
                                    :error="form.errors.name"
                                />

                                <AppInput
                                    v-model="form.phone"
                                    :label="t('checkout.phone')"
                                    name="phone"
                                    type="tel"
                                    inputmode="tel"
                                    dir="ltr"
                                    autocomplete="tel"
                                    placeholder="01xxxxxxxxx"
                                    :hint="t('checkout.phone_hint')"
                                    required
                                    :error="form.errors.phone"
                                />

                                <AppInput
                                    v-model="form.company_name"
                                    :label="t('checkout.company')"
                                    name="company_name"
                                    autocomplete="organization"
                                    :error="form.errors.company_name"
                                />

                                <AppInput
                                    v-model="form.whatsapp"
                                    :label="t('checkout.whatsapp')"
                                    name="whatsapp"
                                    type="tel"
                                    inputmode="tel"
                                    dir="ltr"
                                    autocomplete="tel-national"
                                    :error="form.errors.whatsapp"
                                />

                                <div class="flex flex-col gap-1.5">
                                    <AppInput
                                        v-model="form.governorate"
                                        :label="t('checkout.governorate')"
                                        name="governorate"
                                        list="governorates-list"
                                        autocomplete="address-level1"
                                        :error="form.errors.governorate"
                                    />
                                    <datalist id="governorates-list">
                                        <option
                                            v-for="governorate in governorates"
                                            :key="governorate"
                                            :value="governorate"
                                        />
                                    </datalist>
                                </div>

                                <AppInput
                                    v-model="form.city"
                                    :label="t('checkout.city')"
                                    name="city"
                                    autocomplete="address-level2"
                                    :error="form.errors.city"
                                />

                                <AppInput
                                    v-model="form.address"
                                    :label="t('checkout.address')"
                                    name="address"
                                    autocomplete="street-address"
                                    class="sm:col-span-2"
                                    :error="form.errors.address"
                                />

                                <AppTextarea
                                    v-model="form.customer_notes"
                                    :label="t('checkout.notes')"
                                    name="customer_notes"
                                    :rows="3"
                                    :hint="t('checkout.notes_hint')"
                                    class="sm:col-span-2"
                                    :error="form.errors.customer_notes"
                                />
                            </div>
                        </AppCard>

                        <AppCard
                            as="section"
                            shadow
                        >
                            <div class="flex items-start gap-3">
                                <AppIcon
                                    name="info"
                                    :size="20"
                                    class="mt-0.5 text-accent"
                                />
                                <div class="flex flex-col gap-1">
                                    <h2 class="font-display text-lg font-bold text-ink">
                                        {{ t('checkout.before_submit') }}
                                    </h2>
                                    <p class="text-sm text-ink-muted">
                                        {{ t('checkout.disclaimer') }}
                                    </p>
                                </div>
                            </div>
                        </AppCard>

                        <div class="flex flex-wrap items-center gap-3">
                            <AppButton
                                type="submit"
                                variant="primary"
                                size="lg"
                                icon="check"
                                :loading="form.processing"
                                :disabled="form.processing"
                            >
                                {{ t('checkout.submit') }}
                            </AppButton>

                            <AppButton
                                href="/cart"
                                variant="secondary"
                                size="lg"
                            >
                                {{ t('checkout.back') }}
                            </AppButton>
                        </div>

                        <p
                            v-if="whatsapp"
                            class="text-sm text-ink-muted"
                        >
                            {{ t('checkout.need_help') }}
                            <a
                                :href="`https://wa.me/${whatsapp}`"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="font-semibold text-whatsapp underline underline-offset-2"
                            >
                                {{ t('checkout.contact_whatsapp') }}
                            </a>
                        </p>
                    </form>

                    <AppCard
                        class="lg:sticky lg:top-24"
                        shadow
                    >
                        <h2 class="mb-4 font-display text-xl font-bold text-ink">
                            {{ t('checkout.summary') }}
                        </h2>

                        <CartSummary
                            :items="items"
                            :total-quantity="total_quantity"
                            :total-price="total_price"
                        />

                        <Link
                            href="/cart"
                            class="mt-4 inline-flex min-h-11 items-center gap-1.5 text-sm font-semibold text-primary transition-colors hover:text-primary-strong"
                        >
                            <AppIcon
                                name="chevron-right"
                                :size="16"
                            />
                            {{ t('checkout.edit_quantities') }}
                        </Link>
                    </AppCard>
                </div>
            </template>
        </div>
    </StorefrontLayout>
</template>
