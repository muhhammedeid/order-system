const LOCALE = typeof document !== 'undefined' && document.documentElement.lang === 'en'
    ? 'en-EG'
    : 'ar-EG-u-nu-latn';

const decimalFormatter = new Intl.NumberFormat(LOCALE, {
    maximumFractionDigits: 2,
});

const integerFormatter = new Intl.NumberFormat(LOCALE, {
    maximumFractionDigits: 0,
});

export function formatNumber(value) {
    const numeric = Number(value);

    if (! Number.isFinite(numeric)) {
        return '';
    }

    return decimalFormatter.format(numeric);
}

export function formatQuantity(value) {
    const numeric = Number(value);

    if (! Number.isFinite(numeric)) {
        return '';
    }

    return integerFormatter.format(numeric);
}
