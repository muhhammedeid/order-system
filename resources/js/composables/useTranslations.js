import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const read = (object, path) => path.split('.').reduce((value, segment) => value?.[segment], object);

export function useTranslations() {
    const page = usePage();
    const locale = computed(() => page.props.locale ?? 'ar');
    const direction = computed(() => page.props.direction ?? (locale.value === 'ar' ? 'rtl' : 'ltr'));

    const t = (key, replacements = {}) => {
        const value = read(page.props.translations ?? {}, key) ?? key;

        return String(value).replace(/:([A-Za-z_][A-Za-z0-9_]*)/g, (token, name) =>
            Object.prototype.hasOwnProperty.call(replacements, name) ? String(replacements[name]) : token,
        );
    };

    return { locale, direction, t };
}
