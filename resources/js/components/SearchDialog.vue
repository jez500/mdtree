<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { FileText, Search } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { search, show } from '@/actions/App/Http/Controllers/BrowserController';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import type { SearchResult } from '@/types/browser';

const props = defineProps<{
    open: boolean;
    workspace: string;
}>();

const emits = defineEmits<{
    'update:open': [open: boolean];
}>();

const query = ref('');
const results = ref<SearchResult[]>([]);
const loading = ref(false);
let timeout: ReturnType<typeof setTimeout> | null = null;
let controller: AbortController | null = null;

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            query.value = '';
            results.value = [];
        }
    },
);

watch(query, (value) => {
    if (timeout) {
        clearTimeout(timeout);
    }

    timeout = setTimeout(() => runSearch(value), 150);
});

function runSearch(value: string) {
    const term = value.trim();

    controller?.abort();

    if (!term) {
        results.value = [];
        loading.value = false;

        return;
    }

    const searchController = new AbortController();
    controller = searchController;
    loading.value = true;

    fetch(search.url(props.workspace, { query: { q: term } }), {
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
        signal: searchController.signal,
    })
        .then((response) => {
            if (!response.ok) {
                throw new Error('Search failed');
            }

            return response.json() as Promise<{ results: SearchResult[] }>;
        })
        .then((data) => {
            results.value = data.results;
        })
        .catch((error: unknown) => {
            if (
                !(error instanceof DOMException) ||
                error.name !== 'AbortError'
            ) {
                results.value = [];
            }
        })
        .finally(() => {
            if (controller === searchController) {
                loading.value = false;
            }
        });
}

function openResult(path: string) {
    emits('update:open', false);
    router.visit(
        show.url({ workspace: props.workspace }, { query: { path } }),
        { preserveScroll: true },
    );
}
</script>

<template>
    <Dialog :open="open" @update:open="emits('update:open', $event)">
        <DialogContent
            class="mt-[18vh] gap-0 self-start overflow-hidden p-0 sm:max-w-2xl"
            :show-close-button="false"
        >
            <DialogHeader class="sr-only">
                <DialogTitle>Search Documents</DialogTitle>
            </DialogHeader>

            <div class="flex items-center gap-3 border-b px-4 py-3">
                <Search class="size-4 shrink-0 text-muted-foreground" />
                <Input
                    v-model="query"
                    autofocus
                    class="h-9 border-0 bg-transparent px-0 shadow-none focus-visible:ring-0"
                    placeholder="Search documents..."
                />
            </div>

            <div class="max-h-[24rem] overflow-y-auto p-2">
                <div
                    v-if="loading"
                    class="px-3 py-6 text-center text-sm text-muted-foreground"
                >
                    Searching...
                </div>
                <div
                    v-else-if="query.trim() && results.length === 0"
                    class="px-3 py-6 text-center text-sm text-muted-foreground"
                >
                    No documents found
                </div>
                <div
                    v-else-if="!query.trim()"
                    class="px-3 py-6 text-center text-sm text-muted-foreground"
                >
                    Type to search titles and document text
                </div>

                <button
                    v-for="result in results"
                    :key="result.path"
                    type="button"
                    class="flex w-full items-start gap-3 rounded-md px-3 py-2 text-left hover:bg-accent focus:bg-accent focus:outline-none"
                    @click="openResult(result.path)"
                >
                    <FileText
                        class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                    />
                    <span class="min-w-0 flex-1">
                        <span
                            class="block truncate text-sm font-medium text-foreground"
                            >{{ result.title }}</span
                        >
                        <span
                            class="block truncate text-xs text-muted-foreground"
                            >{{ result.path }}</span
                        >
                        <span
                            v-if="result.excerpt"
                            class="mt-1 block truncate text-xs text-muted-foreground"
                            >{{ result.excerpt }}</span
                        >
                    </span>
                </button>
            </div>
        </DialogContent>
    </Dialog>
</template>
