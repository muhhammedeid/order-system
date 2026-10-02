<script setup>
defineProps({
    size: {
        type: Number,
        default: 36,
    },
});
</script>

<template>
    <svg
        :width="size"
        :height="size"
        viewBox="0 0 64 64"
        fill="none"
        aria-hidden="true"
        class="shrink-0"
    >
        <rect width="64" height="64" rx="14" class="fill-primary" />
        <g
            stroke="currentColor"
            stroke-width="7"
            stroke-linecap="round"
            class="text-on-primary"
        >
            <line x1="32" y1="14" x2="32" y2="50" />
            <line x1="16.4" y1="23" x2="47.6" y2="41" />
            <line x1="47.6" y1="23" x2="16.4" y2="41" />
        </g>
    </svg>
</template>
