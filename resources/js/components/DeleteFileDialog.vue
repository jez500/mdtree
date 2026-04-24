<script setup lang="ts">
import { ref } from 'vue';
import { deleteFile } from '@/actions/App/Http/Controllers/FileController';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';

const props = defineProps<{
    open: boolean;
    filePath: string;
    workspace: string;
}>();

const emits = defineEmits<{
    'update:open': [open: boolean];
    deleted: [];
}>();

const processing = ref(false);

function submit() {
    processing.value = true;
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

    fetch(deleteFile.url(props.workspace), {
        method: 'DELETE',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
        body: JSON.stringify({ path: props.filePath }),
    })
        .then((response) => {
            if (!response.ok) {
                throw new Error('Delete failed');
            }

            emits('deleted');
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
                <DialogTitle>Delete File</DialogTitle>
            </DialogHeader>

            <p class="text-sm text-muted-foreground">Delete <span class="font-medium text-foreground">{{ filePath }}</span>? This cannot be undone.</p>

            <DialogFooter>
                <Button type="button" variant="outline" @click="emits('update:open', false)">Cancel</Button>
                <Button type="button" variant="destructive" :disabled="processing" @click="submit">Delete</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
