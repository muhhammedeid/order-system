<script setup>
defineProps({
    rounded: {
        type: String,
        default: 'rounded-control',
    },
});
</script>

<template>
    <div
        :class="['skeleton rounded-lg', rounded]"
        aria-hidden="true"
    />
</template>

<style scoped>
.skeleton {
    background-image: linear-gradient(
        90deg,
        var(--color-surface-muted) 25%,
        var(--color-surface-soft) 50%,
        var(--color-surface-muted) 75%
    );
    background-size: 200% 100%;
    animation: skeleton-shimmer 1.4s linear infinite;
}

@media (prefers-reduced-motion: reduce) {
    .skeleton {
        animation: none;
    }
}
</style>
