import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const read = (object, path) => path.split('.').reduce((value, segment) => value?.[segment], object);

export function useTranslations() {
    const page = usePage();
    const locale = computed(() => page.props.locale ?? 'ar');
    const direction = computed(() => page.props.direction ?? (locale.value === 'ar' ? 'rtl' : 'ltr'));

    const t = (key, replacements = {}) => {
        let value = read(page.props.translations ?? {}, key) ?? key;

        Object.entries(replacements).forEach(([name, replacement]) => {
            value = String(value).replaceAll(`:${name}`, replacement);
        });

        return value;
    };

    return { locale, direction, t };
}
