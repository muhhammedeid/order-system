<script setup>
import SkeletonBlock from '@/Components/Ui/SkeletonBlock.vue';
</script>

<template>
    <div class="flex flex-col overflow-hidden rounded-card border-2 border-line bg-surface-soft">
        <SkeletonBlock
            rounded="rounded-none"
            class="aspect-square w-full"
        />
        <div class="flex flex-col gap-2 p-3">
            <SkeletonBlock class="h-3 w-14" />
            <SkeletonBlock class="h-4 w-full" />
            <SkeletonBlock class="h-3 w-20" />
            <SkeletonBlock class="mt-1 h-5 w-24" />
        </div>
    </div>
</template>
