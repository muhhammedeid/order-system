<script setup>
import { computed } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import AppButton from '@/Components/Ui/AppButton.vue';
import AppCard from '@/Components/Ui/AppCard.vue';
import AppIcon from '@/Components/Ui/AppIcon.vue';
import { useToast } from '@/composables/useToast';

const props = defineProps({
    order_number: {
        type: String,
        required: true,
    },
});

const page = usePage();
const { push } = useToast();

const whatsapp = computed(() => page.props.whatsapp ?? null);

const whatsappHref = computed(() => {
    if (! whatsapp.value) {
        return null;
    }

    const message = `مرحبًا، بخصوص الطلب رقم ${props.order_number} من MAI SHOES`;

    return `https://wa.me/${whatsapp.value}?text=${encodeURIComponent(message)}`;
});

const STEPS = [
    { icon: 'check-circle', title: 'تم استلام الطلب', text: 'طلبك مسجل الآن في نظامنا.' },
    { icon: 'phone', title: 'مراجعة وتأكيد', text: 'يراجع فريق المبيعات الكميات والأسعار ويتواصل معك.' },
    { icon: 'truck', title: 'تجهيز الطلب', text: 'يتم الاتفاق على التفاصيل وميعاد التسليم.' },
];

async function copyOrderNumber() {
    try {
        await navigator.clipboard.writeText(props.order_number);
        push('تم نسخ رقم الطلب', 'success');
    } catch (error) {
        push('تعذّر النسخ، يمكنك تحديد الرقم يدويًا', 'danger');
    }
}
</script>

<template>
    <StorefrontLayout>
        <Head title="تم استلام الطلب" />

        <div class="mx-auto flex max-w-3xl flex-col items-center gap-6 py-8 text-center sm:py-12">
            <span class="flex h-20 w-20 items-center justify-center rounded-full border-2 border-line-strong bg-success-soft text-success shadow-retro">
                <AppIcon
                    name="check"
                    :size="38"
                />
            </span>

            <div class="flex flex-col gap-2">
                <h1 class="font-display text-3xl font-bold text-ink sm:text-4xl">
                    تم استلام طلبك بنجاح
                </h1>
                <p class="max-w-lg text-ink-muted">
                    سجلنا طلبك وسيتواصل معك فريق المبيعات لتأكيد الكميات والأسعار.
                    إرسال الطلب لا يعني إتمام البيع أو الدفع.
                </p>
            </div>

            <AppCard
                shadow
                class="w-full"
            >
                <p class="text-sm font-semibold text-ink-muted">
                    رقم الطلب
                </p>
                <div class="mt-2 flex flex-wrap items-center justify-center gap-3">
                    <span class="font-mono text-2xl font-bold tabular-nums text-ink sm:text-3xl">
                        {{ order_number }}
                    </span>
                    <button
                        type="button"
                        class="inline-flex min-h-11 items-center gap-2 rounded-control border-2 border-line-strong bg-surface px-3.5 text-sm font-semibold text-ink transition-colors hover:bg-surface-muted"
                        @click="copyOrderNumber"
                    >
                        <AppIcon
                            name="copy"
                            :size="18"
                        />
                        نسخ الرقم
                    </button>
                </div>

                <p class="mt-3 text-xs text-ink-muted">
                    احتفظ بالرقم للرجوع إليه عند التواصل معنا.
                </p>
            </AppCard>

            <ol class="grid w-full gap-3 text-start sm:grid-cols-3">
                <li
                    v-for="step in STEPS"
                    :key="step.title"
                    class="flex flex-col gap-2 rounded-card border-2 border-line bg-surface-soft p-4"
                >
                    <AppIcon
                        :name="step.icon"
                        :size="22"
                        class="text-primary"
                    />
                    <span class="font-display text-base font-bold text-ink">
                        {{ step.title }}
                    </span>
                    <span class="text-sm text-ink-muted">{{ step.text }}</span>
                </li>
            </ol>

            <div class="flex flex-wrap items-center justify-center gap-3">
                <AppButton
                    href="/catalog"
                    variant="primary"
                    size="lg"
                    icon="search"
                >
                    مواصلة التسوق
                </AppButton>

                <a
                    v-if="whatsappHref"
                    :href="whatsappHref"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex min-h-14 items-center gap-2.5 rounded-control border-2 border-line-strong bg-whatsapp px-6 text-lg font-bold text-white shadow-retro-sm transition-[transform,box-shadow,filter] duration-150 hover:brightness-110 active:translate-y-[2px] active:shadow-none"
                >
                    <AppIcon
                        name="whatsapp"
                        :size="22"
                    />
                    إرسال رقم الطلب على واتساب
                </a>
            </div>
        </div>
    </StorefrontLayout>
</template>
