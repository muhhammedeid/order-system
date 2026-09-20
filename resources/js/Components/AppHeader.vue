<script setup>
import { computed, ref, watch } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import AppIcon from '@/Components/Ui/AppIcon.vue';
import BrandLockup from '@/Components/Brand/BrandLockup.vue';
import ThemeToggle from '@/Components/ThemeToggle.vue';

const page = usePage();

const cartCount = computed(() => Number(page.props.cartCount ?? 0));
const currentUrl = computed(() => page.url ?? '/');

const NAV = [
    { label: 'الرئيسية', href: '/', matches: ['/'] },
    { label: 'المتجر', href: '/catalog', matches: ['/catalog', '/product'] },
    { label: 'الطلب', href: '/cart', matches: ['/cart', '/checkout'] },
];

const menuOpen = ref(false);
const cartPulse = ref(false);

function isActive(item) {
    return item.matches.some((prefix) =>
        prefix === '/'
            ? currentUrl.value === '/'
            : currentUrl.value.startsWith(prefix),
    );
}

watch(cartCount, () => {
    cartPulse.value = true;

    window.setTimeout(() => {
        cartPulse.value = false;
    }, 450);
});

watch(currentUrl, () => {
    menuOpen.value = false;
});
</script>

<template>
    <header class="sticky top-0 z-40 border-b-4 border-navy bg-crimson text-cream">
        <div class="mx-auto flex h-18 w-full max-w-7xl items-center gap-3 px-4 py-2.5 sm:px-6 lg:px-8">
            <BrandLockup />

            <nav
                class="ms-auto hidden items-center gap-1 sm:flex"
                aria-label="التنقل الرئيسي"
            >
                <Link
                    v-for="item in NAV"
                    :key="item.href"
                    :href="item.href"
                    class="inline-flex min-h-11 items-center rounded-control px-3.5 text-base font-semibold transition-colors duration-150 hover:bg-cream/15"
                    :class="isActive(item) ? 'bg-cream/20 text-white' : 'text-cream/90'"
                    :aria-current="isActive(item) ? 'page' : undefined"
                >
                    {{ item.label }}
                </Link>
            </nav>

            <div class="ms-auto flex items-center gap-2 sm:ms-3">
                <ThemeToggle />

                <Link
                    href="/cart"
                    class="relative inline-flex h-12 items-center gap-2 rounded-control border-2 border-cream/25 px-3.5 font-semibold transition-colors duration-150 hover:bg-cream/15"
                    :aria-label="`الطلب — ${cartCount} قطعة`"
                >
                    <AppIcon
                        name="cart"
                        :size="22"
                    />
                    <span class="hidden text-base sm:inline">الطلب</span>

                    <span
                        v-if="cartCount > 0"
                        class="flex h-6 min-w-6 items-center justify-center rounded-full bg-cream px-1.5 text-xs font-bold tabular-nums text-crimson"
                        :class="cartPulse ? 'animate-[badge-pop_450ms_ease-out]' : ''"
                        aria-live="polite"
                    >
                        {{ cartCount }}
                    </span>
                </Link>

                <button
                    type="button"
                    class="inline-flex h-12 w-12 items-center justify-center rounded-control border-2 border-cream/25 transition-colors duration-150 hover:bg-cream/15 sm:hidden"
                    :aria-expanded="menuOpen"
                    aria-controls="mobile-nav"
                    aria-label="القائمة"
                    @click="menuOpen = ! menuOpen"
                >
                    <AppIcon
                        :name="menuOpen ? 'close' : 'menu'"
                        :size="22"
                    />
                </button>
            </div>
        </div>

        <div
            v-show="menuOpen"
            id="mobile-nav"
            class="border-t-2 border-cream/20 bg-crimson sm:hidden"
        >
            <nav
                class="mx-auto flex w-full max-w-7xl flex-col gap-1 px-4 py-3 sm:px-6"
                aria-label="التنقل الرئيسي للجوال"
            >
                <Link
                    v-for="item in NAV"
                    :key="`mobile-${item.href}`"
                    :href="item.href"
                    class="rounded-control px-3.5 py-3 text-base font-semibold transition-colors duration-150 hover:bg-cream/15"
                    :class="isActive(item) ? 'bg-cream/20 text-white' : 'text-cream/90'"
                    :aria-current="isActive(item) ? 'page' : undefined"
                >
                    {{ item.label }}
                </Link>
            </nav>
        </div>
    </header>
</template>
