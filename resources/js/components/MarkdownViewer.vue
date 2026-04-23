<script setup lang="ts">
import DOMPurify from 'dompurify';
import { marked } from 'marked';
import { computed } from 'vue';

const props = defineProps<{
    content: string | null;
}>();

const html = computed(() => {
    if (!props.content) {
        return null;
    }

    return DOMPurify.sanitize(marked.parse(props.content) as string);
});
</script>

<template>
    <div v-if="html" class="prose-content" v-html="html" />
    <div v-else class="flex h-full items-center justify-center">
        <p class="text-sm text-muted-foreground">Select a file to get started</p>
    </div>
</template>
