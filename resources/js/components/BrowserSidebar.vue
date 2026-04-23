<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { useLocalStorage } from '@vueuse/core';
import { computed } from 'vue';
import FileTree from '@/components/FileTree.vue';
import { Sidebar, SidebarContent, SidebarHeader } from '@/components/ui/sidebar';
import WorkspaceSwitcher from '@/components/WorkspaceSwitcher.vue';
import type { FileTreeNode, WorkspaceMap } from '@/types/browser';

const page = usePage<{
    workspace: string;
    workspaces: WorkspaceMap;
    tree: FileTreeNode[];
    filePath: string | null;
}>();

const workspace = computed(() => page.props.workspace);
const workspaces = computed(() => page.props.workspaces ?? {});
const tree = computed(() => page.props.tree ?? []);
const filePath = computed(() => page.props.filePath ?? null);

const sidebarWidth = useLocalStorage('mdtree-sidebar-width', 280);

function startResize(e: MouseEvent) {
    const onMove = (ev: MouseEvent) => {
        sidebarWidth.value = Math.max(180, Math.min(600, ev.clientX));
    };
    const onUp = () => {
        document.removeEventListener('mousemove', onMove);
        document.removeEventListener('mouseup', onUp);
    };
    document.addEventListener('mousemove', onMove);
    document.addEventListener('mouseup', onUp);
    e.preventDefault();
}
</script>

<template>
    <Sidebar collapsible="none" class="relative h-svh overflow-hidden" :style="{ '--sidebar-width': `${sidebarWidth}px` }">
        <SidebarHeader>
            <WorkspaceSwitcher :workspace :workspaces />
        </SidebarHeader>
        <SidebarContent class="px-1">
            <FileTree :nodes="tree" :workspace :active-path="filePath" />
        </SidebarContent>
        <div
            class="absolute inset-y-0 right-0 z-10 w-1 cursor-col-resize transition-colors hover:bg-sidebar-border"
            @mousedown="startResize"
        />
    </Sidebar>
</template>
