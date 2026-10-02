import { ref } from 'vue';

const STORAGE_KEY = 'mai-theme';

const isDark = ref(
    typeof document !== 'undefined' && document.documentElement.classList.contains('dark'),
);

const THEME_COLOR = {
    light: '#FFFFFF',
    dark: '#16222C',
};

function syncBrowserChrome(dark) {
    if (typeof document === 'undefined') {
        return;
    }

    const meta = document.querySelector('meta[name="theme-color"]');

    if (meta) {
        meta.setAttribute('content', dark ? THEME_COLOR.dark : THEME_COLOR.light);
    }
}

function apply(dark) {
    isDark.value = dark;

    if (typeof document !== 'undefined') {
        document.documentElement.classList.toggle('dark', dark);
    }

    syncBrowserChrome(dark);

    try {
        localStorage.setItem(STORAGE_KEY, dark ? 'dark' : 'light');
    } catch (error) {
        /* storage unavailable — theme applies for this session only */
    }
}

export function useTheme() {
    return {
        isDark,
        toggle() {
            apply(! isDark.value);
        },
    };
}
