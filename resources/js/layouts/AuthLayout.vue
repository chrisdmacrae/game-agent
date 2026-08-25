<script setup lang="ts">
import AuthLayout from '@/layouts/auth/AuthSimpleLayout.vue';

const {
    eyebrow = '',
    title = '',
    description = '',
} = defineProps<{
    eyebrow?: string;
    title?: string;
    description?: string;
}>();
</script>

<template>
    <AuthLayout :eyebrow="eyebrow" :title="title" :description="description">
        <slot />
    </AuthLayout>
</template>
