<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronRight } from 'lucide-vue-next';
import { computed, inject, type ComputedRef } from 'vue';
import { show } from '@/actions/App/Http/Controllers/BrowserController';
import {
    ContextMenu,
    ContextMenuContent,
    ContextMenuItem,
    ContextMenuTrigger,
} from '@/components/ui/context-menu';
import type { FileTreeNode } from '@/types/browser';

const props = defineProps<{
    node: FileTreeNode;
    depth: number;
    workspace: string;
}>();

const openFolders = inject<Set<string>>('filetree-open-folders')!;
const toggleFolder = inject<(path: string) => void>('filetree-toggle-folder')!;
const activePath = inject<ComputedRef<string | null>>('filetree-active-path')!;
const submitMove = inject<(fromPath: string, toPath: string) => void>('filetree-submit-move')!;
const openNodeDialog = inject<
    (mode: 'createFile' | 'createDir' | 'deleteFile', path: string) => void
>('filetree-open-dialog')!;

const fileBaseUrl = computed(() => show.url({ workspace: props.workspace }));

function fileUrl(path: string): string {
    return `${fileBaseUrl.value}?path=${encodeURIComponent(path)}`;
}

function dragPayload(): string {
    return JSON.stringify({
        name: props.node.name,
        path: props.node.path,
        type: props.node.type,
    });
}

function onDragStart(event: DragEvent) {
    event.dataTransfer?.setData('application/json', dragPayload());
    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';
    }
}

function onDrop(event: DragEvent, newParentPath: string) {
    event.preventDefault();
    event.stopPropagation();

    const payload = event.dataTransfer?.getData('application/json');

    if (!payload) {
        return;
    }

    const moved = JSON.parse(payload) as FileTreeNode;
    const nextPath = newParentPath ? `${newParentPath}/${moved.name}` : moved.name;

    if (nextPath === moved.path) {
        return;
    }

    submitMove(moved.path, nextPath);
}

function allowDrop(event: DragEvent) {
    event.preventDefault();
    event.stopPropagation();
}
</script>

<template>
    <div>
        <ContextMenu v-if="node.type === 'folder'">
            <ContextMenuTrigger as-child>
                <button
                    type="button"
                    class="relative flex w-full items-center rounded py-1 pr-2 text-left text-sidebar-foreground/80 opacity-75 hover:bg-sidebar-accent hover:text-sidebar-foreground"
                    :style="{ paddingLeft: `${8 + depth * 16}px` }"
                    @click="toggleFolder(node.path)"
                    draggable="true"
                    @dragstart="onDragStart"
                    @dragover="allowDrop"
                    @drop="onDrop($event, node.path)"
                >
                    <span
                        v-for="i in depth"
                        :key="i"
                        class="absolute inset-y-0 w-px bg-sidebar-border"
                        :style="{ left: `${8 + (i - 1) * 16 + 7}px` }"
                    />
                    <ChevronRight
                        class="mr-1 size-3.5 shrink-0 text-sidebar-foreground/40 transition-transform duration-150"
                        :class="openFolders.has(node.path) ? 'rotate-90' : ''"
                    />
                    <span class="truncate">{{ node.name }}</span>
                </button>
            </ContextMenuTrigger>

            <ContextMenuContent>
                <ContextMenuItem @select="openNodeDialog('createFile', node.path)">New File</ContextMenuItem>
                <ContextMenuItem @select="openNodeDialog('createDir', node.path)">New Directory</ContextMenuItem>
            </ContextMenuContent>
        </ContextMenu>

        <ContextMenu v-else>
            <ContextMenuTrigger as-child>
                <Link
                    :href="fileUrl(node.path)"
                    class="relative flex w-full items-center rounded py-1 pr-2 text-sidebar-foreground/70 no-underline hover:bg-sidebar-accent hover:text-sidebar-foreground"
                    :class="activePath === node.path ? 'bg-sidebar-accent font-medium text-sidebar-foreground' : ''"
                    :style="{ paddingLeft: `${8 + depth * 16 + 18}px` }"
                    :only="['filePath', 'fileContent']"
                    preserve-state
                    preserve-scroll
                    draggable="true"
                    @dragstart="onDragStart"
                >
                    <span
                        v-for="i in depth"
                        :key="i"
                        class="absolute inset-y-0 w-px bg-sidebar-border"
                        :style="{ left: `${8 + (i - 1) * 16 + 7}px` }"
                    />
                    <span class="truncate">{{ node.name }}</span>
                </Link>
            </ContextMenuTrigger>

            <ContextMenuContent>
                <ContextMenuItem class="text-destructive focus:text-destructive" @select="openNodeDialog('deleteFile', node.path)">Delete</ContextMenuItem>
            </ContextMenuContent>
        </ContextMenu>

        <div v-if="node.type === 'folder' && openFolders.has(node.path) && node.children?.length" class="space-y-0.5">
            <FileTreeNode
                v-for="child in node.children"
                :key="child.path"
                :node="child"
                :depth="depth + 1"
                :workspace="workspace"
            />
        </div>
    </div>
</template>
