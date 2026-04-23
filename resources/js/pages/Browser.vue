<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import MarkdownEditor from '@/components/MarkdownEditor.vue';
import type { FileTreeNode, WorkspaceMap } from '@/types/browser';

const props = defineProps<{
    workspace: string;
    workspaces: WorkspaceMap;
    tree: FileTreeNode[];
    filePath: string | null;
    fileContent: string | null;
}>();

const pageTitle = computed(() => {
    if (props.filePath) {
        return props.filePath.split('/').pop() ?? props.filePath;
    }

    return props.workspaces[props.workspace]?.name ?? props.workspace;
});
</script>

<template>
    <Head :title="pageTitle" />
    <MarkdownEditor
        v-if="filePath"
        :content="fileContent"
        :workspace="workspace"
        :file-path="filePath"
        class="h-full"
    />
    <div v-else class="flex h-full items-center justify-center">
        <p class="text-sm text-gray-500 dark:text-gray-400">Select a file to get started</p>
    </div>
</template>
