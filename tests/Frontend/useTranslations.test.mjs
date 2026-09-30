import assert from 'node:assert/strict';
import test from 'node:test';
import { createSSRApp, h } from 'vue';
import { renderToString } from '@vue/server-renderer';
import { createInertiaApp } from '@inertiajs/vue3';
import { useTranslations } from '../../resources/js/composables/useTranslations.js';

const scenarios = [
    ['Arabic catalog range keeps total intact', 'عرض :from–:to من :total منتج', { from: 1, to: 2, total: 2 }, 'عرض 1–2 من 2 منتج'],
    ['English catalog range keeps total intact', 'Showing :from–:to of :total products', { from: 1, to: 2, total: 2 }, 'Showing 1–2 of 2 products'],
    ['replacement key order does not change the range', ':to / :total', { total: 12, to: 2 }, '2 / 12'],
    ['replacement text is literal and is not expanded again', ':label / :to', { label: '$& :to', to: 2 }, '$& :to / 2'],
    ['zero and repeated placeholders replace while unknown names remain', ':count :count :missing :counted', { count: 0 }, '0 0 :missing :counted'],
];

for (const [name, translation, replacements, expected] of scenarios) {
    test(name, async () => {
        let translatedText;
        const pageComponent = {
            setup() {
                const { t } = useTranslations();
                translatedText = t('catalog.range', replacements);
                return () => h('p', translatedText);
            },
        };

        await createInertiaApp({
            page: { component: 'TranslationRegression', props: { translations: { catalog: { range: translation } } }, url: '/', version: null },
            resolve: () => pageComponent,
            render: renderToString,
            setup: ({ App, props, plugin }) => createSSRApp({ render: () => h(App, props) }).use(plugin),
        });

        assert.equal(translatedText, expected);
    });
}
