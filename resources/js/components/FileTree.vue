<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronRight } from 'lucide-vue-next';
import { computed, reactive, watch } from 'vue';
import { show } from '@/actions/App/Http/Controllers/BrowserController';
import type { FileTreeNode } from '@/types/browser';

const props = defineProps<{
    nodes: FileTreeNode[];
    workspace: string;
    activePath: string | null;
}>();

const openFolders = reactive(new Set<string>());

function getAncestorPaths(nodes: FileTreeNode[], target: string, current: string[] = []): string[] | null {
    for (const node of nodes) {
        if (node.type === 'file' && node.path === target) {
            return current;
        }

        if (node.type === 'folder') {
            const found = getAncestorPaths(node.children ?? [], target, [...current, node.path]);

            if (found !== null) {
                return found;
            }
        }
    }

    return null;
}

function expandToPath(target: string | null) {
    if (!target) {
return;
}

    const ancestors = getAncestorPaths(props.nodes, target);
    ancestors?.forEach((path) => openFolders.add(path));
}

expandToPath(props.activePath);

watch(() => props.activePath, expandToPath);

function toggle(path: string) {
    if (openFolders.has(path)) {
        openFolders.delete(path);
    } else {
        openFolders.add(path);
    }
}

type FlatNode = FileTreeNode & { depth: number };

function flattenTree(nodes: FileTreeNode[], depth = 0): FlatNode[] {
    const result: FlatNode[] = [];

    for (const node of nodes) {
        result.push({ ...node, depth });

        if (node.type === 'folder' && openFolders.has(node.path)) {
            result.push(...flattenTree(node.children ?? [], depth + 1));
        }
    }

    return result;
}

const flatNodes = computed(() => flattenTree(props.nodes));

function fileUrl(path: string): string {
    return `${show.url({ workspace: props.workspace })}?path=${encodeURIComponent(path)}`;
}
</script>

<template>
    <div class="select-none py-1 text-[14px]">
        <template v-for="node in flatNodes" :key="node.path">
            <!-- Folder row -->
            <button
                v-if="node.type === 'folder'"
                type="button"
                class="relative flex w-full items-center rounded py-1 pr-2 text-left text-sidebar-foreground/80 hover:bg-sidebar-accent hover:text-sidebar-foreground opacity-75"
                :style="{ paddingLeft: `${8 + node.depth * 16}px` }"
                @click="toggle(node.path)"
            >
                <span
                    v-for="i in node.depth"
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

            <!-- File row -->
            <Link
                v-else
                :href="fileUrl(node.path)"
                class="relative flex w-full items-center rounded py-1 pr-2 text-sidebar-foreground/70 no-underline hover:bg-sidebar-accent hover:text-sidebar-foreground"
                :class="activePath === node.path ? 'bg-sidebar-accent font-medium text-sidebar-foreground' : ''"
                :style="{ paddingLeft: `${8 + node.depth * 16 + 18}px` }"
                preserve-state
                preserve-scroll
            >
                <span
                    v-for="i in node.depth"
                    :key="i"
                    class="absolute inset-y-0 w-px bg-sidebar-border"
                    :style="{ left: `${8 + (i - 1) * 16 + 7}px` }"
                />
                <span class="truncate">{{ node.name }}</span>
            </Link>
        </template>
    </div>
</template>
