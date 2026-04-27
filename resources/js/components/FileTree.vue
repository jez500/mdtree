<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { FilePlus, FolderPlus, Search } from 'lucide-vue-next';
import { computed, provide, reactive, ref, watch } from 'vue';
import { show } from '@/actions/App/Http/Controllers/BrowserController';
import { moveNode } from '@/actions/App/Http/Controllers/FileController';
import CreateNodeDialog from '@/components/CreateNodeDialog.vue';
import DeleteFileDialog from '@/components/DeleteFileDialog.vue';
import FileTreeNode from '@/components/FileTreeNode.vue';
import SearchDialog from '@/components/SearchDialog.vue';
import { Button } from '@/components/ui/button';
import type { FileTreeNode as FileTreeNodeType } from '@/types/browser';

const props = defineProps<{
    nodes: FileTreeNodeType[];
    workspace: string;
    activePath: string | null;
}>();

const openFolders = reactive(new Set<string>());
const dialog = reactive({
    open: false,
    mode: null as 'createFile' | 'createDir' | 'deleteFile' | null,
    targetPath: '',
});
const searchOpen = ref(false);

const treeNodes = ref<FileTreeNodeType[]>([]);
const activePath = computed(() => props.activePath);

function cloneNodes(nodes: FileTreeNodeType[]): FileTreeNodeType[] {
    return nodes.map((node) => ({
        ...node,
        children: node.children ? cloneNodes(node.children) : undefined,
    }));
}

watch(
    () => props.nodes,
    (nodes) => {
        treeNodes.value = cloneNodes(nodes);
        expandToPath(props.activePath);
    },
    { immediate: true },
);

function getAncestorPaths(
    nodes: FileTreeNodeType[],
    target: string,
    current: string[] = [],
): string[] | null {
    for (const node of nodes) {
        if (node.type === 'file' && node.path === target) {
            return current;
        }

        if (node.type === 'folder') {
            const found = getAncestorPaths(node.children ?? [], target, [
                ...current,
                node.path,
            ]);

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

watch(() => props.activePath, expandToPath);

function toggle(path: string) {
    if (openFolders.has(path)) {
        openFolders.delete(path);
    } else {
        openFolders.add(path);
    }
}

function openDialog(mode: typeof dialog.mode, path: string) {
    dialog.mode = mode;
    dialog.targetPath = path;
    dialog.open = true;
}

function closeDialog() {
    dialog.open = false;
    dialog.mode = null;
    dialog.targetPath = '';
}

function fileUrl(path: string): string {
    return `${show.url({ workspace: props.workspace })}?path=${encodeURIComponent(path)}`;
}

function refreshTree() {
    router.reload({ only: ['tree'] });
}

function resolveActivePath(fromPath: string, toPath: string): string | null {
    const current = activePath.value;

    if (!current) {
        return null;
    }

    if (current === fromPath) {
        return toPath;
    }

    const prefix = `${fromPath}/`;

    if (current.startsWith(prefix)) {
        return `${toPath}/${current.slice(prefix.length)}`;
    }

    return null;
}

function submitMove(fromPath: string, toPath: string) {
    if (fromPath === toPath) {
        return;
    }

    const nextActivePath = resolveActivePath(fromPath, toPath);
    const csrfToken =
        document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content') ?? '';

    fetch(moveNode.url(props.workspace), {
        method: 'PATCH',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
        body: JSON.stringify({ from: fromPath, to: toPath }),
    })
        .then((response) => {
            if (!response.ok) {
                throw new Error('Move failed');
            }

            if (
                nextActivePath !== null &&
                nextActivePath !== activePath.value
            ) {
                router.visit(fileUrl(nextActivePath), {
                    preserveScroll: true,
                    preserveState: true,
                    replace: true,
                    only: ['tree', 'filePath', 'fileContent'],
                });

                return;
            }

            refreshTree();
        })
        .catch(() => {
            refreshTree();
        });
}

function handleRootDrop(event: DragEvent) {
    if (event.target !== event.currentTarget) {
        return;
    }

    const payload = event.dataTransfer?.getData('application/json');

    if (!payload) {
        return;
    }

    const moved = JSON.parse(payload) as FileTreeNodeType;

    if (moved.path === moved.name) {
        return;
    }

    submitMove(moved.path, moved.name);
}

function handleCreated(path: string) {
    const mode = dialog.mode;

    closeDialog();

    if (mode === 'createFile') {
        router.visit(fileUrl(path), {
            preserveScroll: true,
            preserveState: true,
            replace: true,
            only: ['tree', 'filePath', 'fileContent'],
        });

        return;
    }

    refreshTree();
}

function handleDeleted() {
    const deletedPath = dialog.targetPath;
    const shouldGoToRoot = props.activePath === deletedPath;

    closeDialog();

    if (shouldGoToRoot) {
        router.visit(show.url({ workspace: props.workspace }), {
            preserveScroll: true,
            preserveState: true,
            replace: true,
            only: ['tree', 'filePath', 'fileContent'],
        });

        return;
    }

    refreshTree();
}

provide('filetree-open-folders', openFolders);
provide('filetree-toggle-folder', toggle);
provide('filetree-active-path', activePath);
provide('filetree-submit-move', submitMove);
provide('filetree-open-dialog', openDialog);
provide('filetree-on-root-drop', handleRootDrop);
</script>

<template>
    <div class="py-1 text-[14px] select-none">
        <div class="flex items-center justify-between px-2 py-1">
            <span
                class="text-xs font-medium tracking-wide text-sidebar-foreground/50 uppercase"
                title="Drop here to move to workspace root"
                @dragover.prevent
                @drop.prevent.stop="handleRootDrop"
            >
                ROOT
            </span>
            <div class="flex gap-1">
                <Button
                    variant="ghost"
                    size="icon"
                    class="h-6 w-6"
                    title="Search Documents"
                    @click="searchOpen = true"
                >
                    <Search class="size-3.5" />
                </Button>
                <Button
                    variant="ghost"
                    size="icon"
                    class="h-6 w-6"
                    title="New Directory"
                    @click="openDialog('createDir', '')"
                >
                    <FolderPlus class="size-3.5" />
                </Button>
                <Button
                    variant="ghost"
                    size="icon"
                    class="h-6 w-6"
                    title="New File"
                    @click="openDialog('createFile', '')"
                >
                    <FilePlus class="size-3.5" />
                </Button>
            </div>
        </div>

        <div
            class="space-y-0.5"
            @dragover.prevent
            @drop.prevent="handleRootDrop"
        >
            <FileTreeNode
                v-for="element in treeNodes"
                :key="element.path"
                :node="element"
                :depth="0"
                :workspace="workspace"
            />
        </div>

        <CreateNodeDialog
            :open="
                dialog.mode === 'createFile' || dialog.mode === 'createDir'
                    ? dialog.open
                    : false
            "
            :type="dialog.mode === 'createDir' ? 'directory' : 'file'"
            :parent-path="dialog.targetPath"
            :workspace="workspace"
            @update:open="dialog.open = $event"
            @created="handleCreated"
        />

        <DeleteFileDialog
            :open="dialog.mode === 'deleteFile' ? dialog.open : false"
            :file-path="dialog.targetPath"
            :workspace="workspace"
            @update:open="dialog.open = $event"
            @deleted="handleDeleted"
        />

        <SearchDialog v-model:open="searchOpen" :workspace="workspace" />
    </div>
</template>
