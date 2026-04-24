<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { createDirectory, createFile } from '@/actions/App/Http/Controllers/FileController';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const props = defineProps<{
    open: boolean;
    type: 'file' | 'directory';
    parentPath: string;
    workspace: string;
}>();

const emits = defineEmits<{
    'update:open': [open: boolean];
    created: [path: string];
}>();

const name = ref('');
const processing = ref(false);

const title = computed(() => (props.type === 'file' ? 'New File' : 'New Directory'));

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            name.value = '';
        }
    },
);

function fullPath() {
    return props.parentPath ? `${props.parentPath}/${name.value.trim()}` : name.value.trim();
}

function submit() {
    const path = fullPath();

    if (!path) {
        return;
    }

    processing.value = true;
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

    const route = props.type === 'file' ? createFile(props.workspace) : createDirectory(props.workspace);

    fetch(route.url, {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
        body: JSON.stringify({ path }),
    })
        .then((response) => {
            if (!response.ok) {
                throw new Error('Create failed');
            }

            emits('created', path);
            emits('update:open', false);
        })
        .finally(() => {
            processing.value = false;
        });
}
</script>

<template>
    <Dialog :open="open" @update:open="emits('update:open', $event)">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{ title }}</DialogTitle>
            </DialogHeader>

            <form class="grid gap-4" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label for="node-name">Name</Label>
                    <Input id="node-name" v-model="name" autofocus placeholder="Untitled" />
                </div>

                <DialogFooter>
                    <Button type="button" variant="outline" @click="emits('update:open', false)">Cancel</Button>
                    <Button type="submit" :disabled="processing || !name.trim()">Create</Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
