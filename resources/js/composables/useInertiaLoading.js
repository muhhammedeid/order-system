import { onMounted, onUnmounted, ref } from 'vue';
import { router } from '@inertiajs/vue3';

export function useInertiaLoading() {
    const loading = ref(false);
    let finish = null;
    let start = null;

    onMounted(() => {
        start = router.on('start', () => {
            loading.value = true;
        });

        finish = router.on('finish', () => {
            loading.value = false;
        });
    });

    onUnmounted(() => {
        start?.();
        finish?.();
    });

    return { loading };
}
