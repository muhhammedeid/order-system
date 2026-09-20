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
const whatsapp = computed(() => page.props.whatsapp ?? null);

const GOVERNORATES = [
    'القاهرة', 'الجيزة', 'الإسكندرية', 'الدقهلية', 'الشرقية', 'القليوبية',
    'المنوفية', 'الغربية', 'كفر الشيخ', 'دمياط', 'بورسعيد', 'الإسماعيلية',
    'السويس', 'البحيرة', 'الفيوم', 'بني سويف', 'المنيا', 'أسيوط', 'سوهاج',
    'قنا', 'الأقصر', 'أسوان', 'البحر الأحمر', 'مطروح', 'شمال سيناء',
    'جنوب سيناء', 'الوادي الجديد',
];

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
        <Head title="إتمام الطلب" />

        <div class="flex flex-col gap-6">
            <h1 class="font-display text-3xl font-bold text-ink sm:text-4xl">
                إتمام الطلب
            </h1>

            <div
                v-if="! items.length"
                class="flex flex-col items-center gap-4 rounded-card border-2 border-dashed border-line px-6 py-16 text-center"
            >
                <p class="text-lg text-ink-muted">لا يمكن إتمام طلب فارغ</p>
                <AppButton
                    href="/catalog"
                    variant="primary"
                    icon="search"
                >
                    تسوق الآن
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
                            class="rounded-card border-2 border-danger bg-danger-soft px-4 py-3.5"
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
                                يرجى تصحيح الحقول التالية
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
                                بيانات التواصل
                            </h2>
                            <p class="mb-4 text-sm text-ink-muted">
                                سنستخدم هذه البيانات لتأكيد الطلب والتواصل معك. لا حاجة لإنشاء حساب.
                            </p>

                            <div class="grid gap-4 sm:grid-cols-2">
                                <AppInput
                                    v-model="form.name"
                                    label="الاسم"
                                    name="name"
                                    autocomplete="name"
                                    required
                                    :error="form.errors.name"
                                />

                                <AppInput
                                    v-model="form.phone"
                                    label="رقم الموبايل"
                                    name="phone"
                                    type="tel"
                                    inputmode="tel"
                                    dir="ltr"
                                    autocomplete="tel"
                                    placeholder="01xxxxxxxxx"
                                    hint="سيتم استخدامه لمطابقة بياناتك الحالية إن وجدت."
                                    required
                                    :error="form.errors.phone"
                                />

                                <AppInput
                                    v-model="form.company_name"
                                    label="اسم الشركة / المحل"
                                    name="company_name"
                                    autocomplete="organization"
                                    :error="form.errors.company_name"
                                />

                                <AppInput
                                    v-model="form.whatsapp"
                                    label="رقم واتساب (إن اختلف)"
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
                                        label="المحافظة"
                                        name="governorate"
                                        list="governorates-list"
                                        autocomplete="address-level1"
                                        :error="form.errors.governorate"
                                    />
                                    <datalist id="governorates-list">
                                        <option
                                            v-for="governorate in GOVERNORATES"
                                            :key="governorate"
                                            :value="governorate"
                                        />
                                    </datalist>
                                </div>

                                <AppInput
                                    v-model="form.city"
                                    label="المدينة / المنطقة"
                                    name="city"
                                    autocomplete="address-level2"
                                    :error="form.errors.city"
                                />

                                <AppInput
                                    v-model="form.address"
                                    label="العنوان"
                                    name="address"
                                    autocomplete="street-address"
                                    class="sm:col-span-2"
                                    :error="form.errors.address"
                                />

                                <AppTextarea
                                    v-model="form.customer_notes"
                                    label="ملاحظات على الطلب"
                                    name="customer_notes"
                                    :rows="3"
                                    hint="مثال: تفضيل ميعاد التسليم أو أي تفاصيل إضافية."
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
                                        قبل الإرسال
                                    </h2>
                                    <p class="text-sm text-ink-muted">
                                        إرسال الطلب لا يعني إتمام البيع أو الدفع. سيقوم فريق المبيعات بمراجعة الطلب
                                        وتأكيد الكميات والأسعار ثم التواصل معك.
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
                                إرسال الطلب
                            </AppButton>

                            <AppButton
                                href="/cart"
                                variant="secondary"
                                size="lg"
                            >
                                رجوع للطلب
                            </AppButton>
                        </div>

                        <p
                            v-if="whatsapp"
                            class="text-sm text-ink-muted"
                        >
                            تحتاج مساعدة؟
                            <a
                                :href="`https://wa.me/${whatsapp}`"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="font-semibold text-whatsapp underline underline-offset-2"
                            >
                                تواصل معنا على واتساب
                            </a>
                        </p>
                    </form>

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

                        <Link
                            href="/cart"
                            class="mt-4 inline-flex min-h-11 items-center gap-1.5 text-sm font-semibold text-primary transition-colors hover:text-primary-strong"
                        >
                            <AppIcon
                                name="chevron-right"
                                :size="16"
                            />
                            تعديل الكميات
                        </Link>
                    </AppCard>
                </div>
            </template>
        </div>
    </StorefrontLayout>
</template>
