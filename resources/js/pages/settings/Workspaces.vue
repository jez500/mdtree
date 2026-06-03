<script setup lang="ts">
import { ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { edit } from '@/routes/workspaces';
import { store, destroy, update } from '@/actions/App/Http/Controllers/WorkspaceController';
import type { WorkspaceWithId } from '@/types/browser';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogDescription,
} from '@/components/ui/dialog';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Workspaces',
                href: edit(),
            },
        ],
    },
});

const workspaces = defineModel<WorkspaceWithId[]>('workspaces', { default: [] });

const showAddDialog = ref(false);
const showEditDialog = ref(false);
const showDeleteDialog = ref(false);

const form = ref({
    name: '',
    path: '',
    slug: '',
});

const editingWorkspace = ref<WorkspaceWithId | null>(null);
const deletingWorkspace = ref<WorkspaceWithId | null>(null);

const processing = ref(false);
const errors = ref<Record<string, string>>({});

function openAddDialog() {
    form.value = { name: '', path: '', slug: '' };
    errors.value = {};
    showAddDialog.value = true;
}

function openEditDialog(workspace: WorkspaceWithId) {
    editingWorkspace.value = workspace;
    form.value = { name: workspace.name, path: workspace.path, slug: workspace.slug };
    errors.value = {};
    showEditDialog.value = true;
}

function openDeleteDialog(workspace: WorkspaceWithId) {
    deletingWorkspace.value = workspace;
    showDeleteDialog.value = true;
}

function getCsrfToken(): string {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

function handleStore() {
    processing.value = true;
    errors.value = {};

    fetch(store.url(), {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': getCsrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
        body: JSON.stringify(form.value),
    })
        .then((response) => {
            if (!response.ok) {
                return response.json().then((data) => {
                    throw data;
                });
            }
            return response.json();
        })
        .then((data) => {
            showAddDialog.value = false;
            workspaces.value = [...workspaces.value, data.workspace];
        })
        .catch((data) => {
            if (data.errors) {
                errors.value = data.errors;
            }
        })
        .finally(() => {
            processing.value = false;
        });
}

function handleUpdate() {
    if (!editingWorkspace.value) return;

    processing.value = true;
    errors.value = {};

    fetch(update.url({ workspace: editingWorkspace.value.id }), {
        method: 'PUT',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': getCsrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
        body: JSON.stringify(form.value),
    })
        .then((response) => {
            if (!response.ok) {
                return response.json().then((data) => {
                    throw data;
                });
            }
            return response.json();
        })
        .then((data) => {
            showEditDialog.value = false;
            workspaces.value = workspaces.value.map((ws) =>
                ws.id === editingWorkspace.value!.id ? data.workspace : ws
            );
        })
        .catch((data) => {
            if (data.errors) {
                errors.value = data.errors;
            }
        })
        .finally(() => {
            processing.value = false;
        });
}

function handleDelete() {
    if (!deletingWorkspace.value) return;

    processing.value = true;

    fetch(destroy.url({ workspace: deletingWorkspace.value.id }), {
        method: 'DELETE',
        headers: {
            Accept: 'application/json',
            'X-CSRF-TOKEN': getCsrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
    })
        .then((response) => {
            if (!response.ok) {
                throw new Error('Delete failed');
            }
            return response.json();
        })
        .then(() => {
            showDeleteDialog.value = false;
            workspaces.value = workspaces.value.filter((ws) => ws.id !== deletingWorkspace.value!.id);
        })
        .finally(() => {
            processing.value = false;
        });
}
</script>

<template>
    <Head title="Workspaces" />

    <div class="flex flex-col space-y-6">
        <Heading
            title="Workspaces"
            description="Manage your workspaces"
        />

        <div class="flex justify-end">
            <Button @click="openAddDialog">Add Workspace</Button>
        </div>

        <div class="rounded-md border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Name</TableHead>
                        <TableHead>Slug</TableHead>
                        <TableHead>Path</TableHead>
                        <TableHead class="w-[100px]"></TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="workspace in workspaces" :key="workspace.id">
                        <TableCell class="font-medium">{{ workspace.name }}</TableCell>
                        <TableCell>
                            <code class="rounded bg-muted px-1.5 py-0.5 text-xs">{{ workspace.slug }}</code>
                        </TableCell>
                        <TableCell class="text-muted-foreground">{{ workspace.path }}</TableCell>
                        <TableCell>
                            <div class="flex gap-2">
                                <Button variant="outline" size="sm" @click="openEditDialog(workspace)">
                                    Edit
                                </Button>
                                <Button variant="destructive" size="sm" @click="openDeleteDialog(workspace)">
                                    Delete
                                </Button>
                            </div>
                        </TableCell>
                    </TableRow>
                    <TableRow v-if="workspaces.length === 0">
                        <TableCell colspan="4" class="h-24 text-center text-muted-foreground">
                            No workspaces yet. Click "Add Workspace" to create one.
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>
    </div>

    <Dialog :open="showAddDialog" @update:open="showAddDialog = $event">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Add Workspace</DialogTitle>
                <DialogDescription>
                    Create a new workspace to organize your files.
                </DialogDescription>
            </DialogHeader>

            <div class="space-y-4 py-4">
                <div class="grid gap-2">
                    <Label for="name">Name</Label>
                    <Input
                        id="name"
                        v-model="form.name"
                        placeholder="My Notes"
                    />
                    <p v-if="errors.name" class="text-sm text-destructive">{{ errors.name }}</p>
                </div>

                <div class="grid gap-2">
                    <Label for="path">Path</Label>
                    <Input
                        id="path"
                        v-model="form.path"
                        placeholder="/home/user/notes"
                    />
                    <p v-if="errors.path" class="text-sm text-destructive">{{ errors.path }}</p>
                </div>

                <div class="grid gap-2">
                    <Label for="slug">Slug (optional)</Label>
                    <Input
                        id="slug"
                        v-model="form.slug"
                        placeholder="my-notes"
                    />
                    <p class="text-xs text-muted-foreground">Leave empty to auto-generate from name</p>
                    <p v-if="errors.slug" class="text-sm text-destructive">{{ errors.slug }}</p>
                </div>
            </div>

            <DialogFooter>
                <Button variant="outline" @click="showAddDialog = false">Cancel</Button>
                <Button :disabled="processing" @click="handleStore">Create</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <Dialog :open="showEditDialog" @update:open="showEditDialog = $event">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Edit Workspace</DialogTitle>
                <DialogDescription>
                    Update workspace details.
                </DialogDescription>
            </DialogHeader>

            <div class="space-y-4 py-4">
                <div class="grid gap-2">
                    <Label for="edit-name">Name</Label>
                    <Input
                        id="edit-name"
                        v-model="form.name"
                        placeholder="My Notes"
                    />
                    <p v-if="errors.name" class="text-sm text-destructive">{{ errors.name }}</p>
                </div>

                <div class="grid gap-2">
                    <Label for="edit-path">Path</Label>
                    <Input
                        id="edit-path"
                        v-model="form.path"
                        placeholder="/home/user/notes"
                    />
                    <p v-if="errors.path" class="text-sm text-destructive">{{ errors.path }}</p>
                </div>
            </div>

            <DialogFooter>
                <Button variant="outline" @click="showEditDialog = false">Cancel</Button>
                <Button :disabled="processing" @click="handleUpdate">Save</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <Dialog :open="showDeleteDialog" @update:open="showDeleteDialog = $event">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Delete Workspace</DialogTitle>
                <DialogDescription>
                    Are you sure you want to delete <span class="font-medium">{{ deletingWorkspace?.name }}</span>?
                    This will not delete the files on disk.
                </DialogDescription>
            </DialogHeader>
            <DialogFooter>
                <Button variant="outline" @click="showDeleteDialog = false">Cancel</Button>
                <Button variant="destructive" :disabled="processing" @click="handleDelete">Delete</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>