<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import type { Editor as TiptapEditor } from '@tiptap/core';
import { Table } from '@tiptap/extension-table';
import { TableCell } from '@tiptap/extension-table-cell';
import { TableHeader } from '@tiptap/extension-table-header';
import { TableRow } from '@tiptap/extension-table-row';
import { useDebounceFn } from '@vueuse/core';
import { Check, FileText, Link2, Loader2, X } from 'lucide-vue-next';
import { Editor as NovelEditor } from 'novel-vue';
import { computed, nextTick, ref, watch } from 'vue';
import { resolveLink } from '@/actions/App/Http/Controllers/BrowserController';
import { save as saveFileRoute } from '@/routes/files';
import type { FileTreeNode } from '@/types/browser';
import TableBubbleMenu from './TableBubbleMenu.vue';

const props = defineProps<{
    content: string | null;
    workspace: string;
    filePath: string;
    tree: FileTreeNode[];
}>();

type SaveStatus = 'saving' | 'saved' | 'error';
type Mode = 'editor' | 'raw';

const saveStatus = ref<SaveStatus>('saved');
const mode = ref<Mode>('editor');
const rawContent = ref(props.content ?? '');
const linkDialogOpen = ref(false);
const linkUrl = ref('');
// exposeProxy auto-unwraps refs, so .editor is the Editor instance directly (not a ShallowRef)
const editorRef = ref<{ editor: TiptapEditor | undefined } | null>(null);
const activeEditor = computed(
    () => editorRef.value?.editor as TiptapEditor | undefined,
);
const transactionTick = ref(0);

let suppressSave = true;
const editorInitialized = ref(false);

const storageKey = computed(
    () => `mdtree__${props.workspace}__${props.filePath}`,
);

const tableExtensions = [
    Table.configure({ resizable: false }),
    TableRow,
    TableHeader,
    TableCell,
];
const markdownDocuments = computed(() => flattenMarkdownFiles(props.tree));

watch(
    () => editorRef.value?.editor,
    async (ed) => {
        if (!ed || editorInitialized.value) {
            return;
        }

        editorInitialized.value = true;

        ed.on('transaction', () => {
            transactionTick.value++;
        });

        // novel-vue's internal watchEffect fires synchronously in the same flush
        // batch and sets its default/localStorage content. Yield to that flush
        // first, then override with the actual file content.
        await nextTick();

        suppressSave = true;
        ed.commands.setContent(props.content ?? '', false);
        rawContent.value = getMarkdownFrom(ed as TiptapEditor);
        suppressSave = false;
    },
    { immediate: true },
);

const saveFile = useDebounceFn(async (markdown: string) => {
    saveStatus.value = 'saving';

    try {
        const target = saveFileRoute(
            { workspace: props.workspace },
            { query: { path: props.filePath } },
        );

        const response = await fetch(target.url, {
            method: 'PUT',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken(),
                'X-XSRF-TOKEN': xsrfToken(),
            },
            body: JSON.stringify({ path: props.filePath, content: markdown }),
        });

        if (!response.ok) {
            throw new Error(`Failed to save (${response.status})`);
        }

        saveStatus.value = 'saved';
    } catch (error) {
        saveStatus.value = 'error';
        console.error('Failed to save file:', error);
    }
}, 600);

function csrfToken(): string {
    return (
        document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
            ?.content ?? ''
    );
}

function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
}

function getMarkdownFrom(editor: TiptapEditor): string {
    const holder = editor as unknown as {
        storage?: Record<string, { getMarkdown?: () => string }>;
    };

    return holder.storage?.markdown?.getMarkdown?.() ?? '';
}

const handleUpdate = (editor?: TiptapEditor) => {
    if (!editor) {
        return;
    }

    const markdown = getMarkdownFrom(editor);
    rawContent.value = markdown;

    if (suppressSave || mode.value !== 'editor') {
        return;
    }

    saveFile(markdown);
};

watch(
    () => [props.content, props.filePath],
    ([newContent], [, oldPath]) => {
        const ed = activeEditor.value;

        if (!ed) {
            return;
        }

        if (props.filePath === oldPath) {
            return;
        }

        suppressSave = true;
        const next = (newContent as string | null) ?? '';
        ed.commands.setContent(next, false);
        rawContent.value = next;
        saveStatus.value = 'saved';
        suppressSave = false;
    },
);

watch(rawContent, (value) => {
    if (suppressSave || mode.value !== 'raw') {
        return;
    }

    saveFile(value);
});

watch(mode, (newMode) => {
    const ed = activeEditor.value;

    if (!ed) {
        return;
    }

    if (newMode === 'raw') {
        return;
    }

    suppressSave = true;
    ed.commands.setContent(rawContent.value, false);
    suppressSave = false;
});

const openLinkDialog = () => {
    const ed = activeEditor.value;

    if (!ed) {
        return;
    }

    const previousUrl = ed.getAttributes('link').href as string | undefined;
    linkUrl.value = previousUrl ?? '';
    linkDialogOpen.value = true;
};

const applyLink = () => {
    const ed = activeEditor.value;

    if (!ed) {
        return;
    }

    const chain = ed.chain().focus().extendMarkRange('link') as unknown as {
        setLink(attributes: { href: string }): { run(): boolean };
        unsetLink(): { run(): boolean };
    };

    if (linkUrl.value.trim() === '') {
        chain.unsetLink().run();
    } else {
        chain.setLink({ href: linkUrl.value.trim() }).run();
    }

    linkDialogOpen.value = false;
};

function selectDocument(path: string) {
    if (!path) {
        return;
    }

    linkUrl.value = relativeMarkdownPath(props.filePath, path);
}

function handleDocumentChange(event: Event) {
    if (event.target instanceof HTMLSelectElement) {
        selectDocument(event.target.value);
    }
}

const insertTable = () => {
    activeEditor.value
        ?.chain()
        .focus()
        .insertTable({ rows: 2, cols: 2, withHeaderRow: true })
        .run();
};

const statusLabel = computed(() => {
    switch (saveStatus.value) {
        case 'saving':
            return 'Saving…';
        case 'saved':
            return 'Saved';
        case 'error':
            return 'Save failed';
    }

    return '';
});

const isActive = (
    name: string,
    attributes: Record<string, unknown> = {},
): boolean => {
    void transactionTick.value; // reactive dependency on editor state changes

    return activeEditor.value?.isActive(name, attributes) ?? false;
};

const runCommand = (name: string, ...args: unknown[]): void => {
    const ed = activeEditor.value;

    if (!ed) {
        return;
    }

    const chain = ed.chain().focus() as unknown as Record<
        string,
        (...a: unknown[]) => { run: () => boolean }
    >;
    chain[name](...args).run();
};

const layoutScrollArea = ref<HTMLElement | null>(null);
router.on('success', (event) => {
    nextTick(() => {
        if (event?.detail?.page?.url.startsWith('/browser')) {
            layoutScrollArea.value?.scrollTo({
                top: 0,
                left: 0,
                behavior: 'auto',
            });
        }
    });
});

function flattenMarkdownFiles(nodes: FileTreeNode[]): string[] {
    return nodes.flatMap((node) => {
        if (node.type === 'folder') {
            return flattenMarkdownFiles(node.children ?? []);
        }

        return node.path.toLowerCase().endsWith('.md') ? [node.path] : [];
    });
}

function relativeMarkdownPath(fromPath: string, toPath: string): string {
    const fromSegments = fromPath.split('/').slice(0, -1);
    const toSegments = toPath.split('/');

    while (
        fromSegments.length > 0 &&
        toSegments.length > 0 &&
        fromSegments[0] === toSegments[0]
    ) {
        fromSegments.shift();
        toSegments.shift();
    }

    return [...fromSegments.map(() => '..'), ...toSegments].join('/');
}

function isLocalMarkdownHref(href: string): boolean {
    if (!href || href.startsWith('#') || href.startsWith('/')) {
        return false;
    }

    return !/^[a-z][a-z\d+.-]*:/i.test(href);
}

function isMarkdownPath(href: string): boolean {
    return href.split('#')[0].split('?')[0].toLowerCase().endsWith('.md');
}

function handleEditorClick(event: MouseEvent) {
    const target =
        event.target instanceof Element ? event.target.closest('a') : null;

    if (
        !(target instanceof HTMLAnchorElement) ||
        !isLocalMarkdownHref(target.getAttribute('href') ?? '') ||
        !isMarkdownPath(target.getAttribute('href') ?? '')
    ) {
        return;
    }

    event.preventDefault();
    router.visit(
        resolveLink.url(
            { workspace: props.workspace },
            {
                query: {
                    from: props.filePath,
                    href: target.getAttribute('href') ?? '',
                },
            },
        ),
    );
}
</script>

<template>
    <div class="flex flex-col">
        <Teleport defer to="#browser-header-slot">
            <div class="flex flex-wrap items-center gap-2">
                <button
                    type="button"
                    class="hidden rounded p-1 hover:!bg-neutral-100 lg:block dark:hover:!bg-neutral-700"
                    :class="{
                        '!bg-neutral-200 dark:!bg-neutral-600': isActive(
                            'heading',
                            { level: 1 },
                        ),
                    }"
                    title="Heading 1"
                    @click="runCommand('toggleHeading', { level: 1 })"
                >
                    <svg
                        class="h-4 w-4"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M6 12h12M6 6h12M6 18h8"
                        />
                    </svg>
                </button>
                <button
                    type="button"
                    class="hidden rounded p-1 hover:!bg-neutral-100 lg:block dark:hover:!bg-neutral-700"
                    :class="{
                        '!bg-neutral-200 dark:!bg-neutral-600': isActive(
                            'heading',
                            { level: 2 },
                        ),
                    }"
                    title="Heading 2"
                    @click="runCommand('toggleHeading', { level: 2 })"
                >
                    <svg
                        class="h-4 w-4"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M6 12h12M6 6h12M6 18h10"
                        />
                    </svg>
                </button>
                <button
                    type="button"
                    class="hidden rounded p-1 hover:!bg-neutral-100 lg:block dark:hover:!bg-neutral-700"
                    :class="{
                        '!bg-neutral-200 dark:!bg-neutral-600': isActive(
                            'heading',
                            { level: 3 },
                        ),
                    }"
                    title="Heading 3"
                    @click="runCommand('toggleHeading', { level: 3 })"
                >
                    <svg
                        class="h-4 w-4"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M6 12h12M6 6h12M6 18h6"
                        />
                    </svg>
                </button>

                <div
                    class="hidden h-6 w-px !bg-neutral-300 lg:block dark:!bg-neutral-600"
                />

                <button
                    type="button"
                    class="rounded p-1 font-bold hover:!bg-neutral-100 dark:hover:!bg-neutral-700"
                    :class="{
                        '!bg-neutral-200 dark:!bg-neutral-600':
                            isActive('bold'),
                    }"
                    title="Bold"
                    @click="runCommand('toggleBold')"
                >
                    B
                </button>
                <button
                    type="button"
                    class="rounded p-1 italic hover:!bg-neutral-100 dark:hover:!bg-neutral-700"
                    :class="{
                        '!bg-neutral-200 dark:!bg-neutral-600':
                            isActive('italic'),
                    }"
                    title="Italic"
                    @click="runCommand('toggleItalic')"
                >
                    I
                </button>
                <button
                    type="button"
                    class="hidden rounded p-1 line-through hover:!bg-neutral-100 lg:block dark:hover:!bg-neutral-700"
                    :class="{
                        '!bg-neutral-200 dark:!bg-neutral-600':
                            isActive('strike'),
                    }"
                    title="Strikethrough"
                    @click="runCommand('toggleStrike')"
                >
                    S
                </button>

                <div
                    class="hidden h-6 w-px !bg-neutral-300 lg:block dark:!bg-neutral-600"
                />

                <button
                    type="button"
                    class="hidden rounded p-1 hover:!bg-neutral-100 lg:block dark:hover:!bg-neutral-700"
                    :class="{
                        '!bg-neutral-200 dark:!bg-neutral-600':
                            isActive('bulletList'),
                    }"
                    title="Bullet List"
                    @click="runCommand('toggleBulletList')"
                >
                    <svg
                        class="h-4 w-4"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M4 6h16M4 12h16M4 18h16"
                        />
                    </svg>
                </button>
                <button
                    type="button"
                    class="hidden rounded p-1 hover:!bg-neutral-100 lg:block dark:hover:!bg-neutral-700"
                    :class="{
                        '!bg-neutral-200 dark:!bg-neutral-600':
                            isActive('orderedList'),
                    }"
                    title="Ordered List"
                    @click="runCommand('toggleOrderedList')"
                >
                    <svg
                        class="h-4 w-4"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M7 8h10M7 12h10M7 16h10M3 8h.01M3 12h.01M3 16h.01"
                        />
                    </svg>
                </button>
                <button
                    type="button"
                    class="hidden rounded p-1 hover:!bg-neutral-100 lg:block dark:hover:!bg-neutral-700"
                    :class="{
                        '!bg-neutral-200 dark:!bg-neutral-600':
                            isActive('taskList'),
                    }"
                    title="Task List"
                    @click="runCommand('toggleTaskList')"
                >
                    <svg
                        class="h-4 w-4"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M9 11l3 3L22 4M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"
                        />
                    </svg>
                </button>

                <div
                    class="hidden h-6 w-px !bg-neutral-300 lg:block dark:!bg-neutral-600"
                />

                <button
                    type="button"
                    class="hidden rounded p-1 hover:!bg-neutral-100 lg:block dark:hover:!bg-neutral-700"
                    :class="{
                        '!bg-neutral-200 dark:!bg-neutral-600':
                            isActive('code'),
                    }"
                    title="Inline Code"
                    @click="runCommand('toggleCode')"
                >
                    <svg
                        class="h-4 w-4"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"
                        />
                    </svg>
                </button>
                <button
                    type="button"
                    class="hidden rounded p-1 hover:!bg-neutral-100 lg:block dark:hover:!bg-neutral-700"
                    :class="{
                        '!bg-neutral-200 dark:!bg-neutral-600':
                            isActive('codeBlock'),
                    }"
                    title="Code Block"
                    @click="runCommand('toggleCodeBlock')"
                >
                    <svg
                        class="h-4 w-4"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M8 9l-4 3 4 3M16 9l4 3-4 3M14 4l-4 16"
                        />
                    </svg>
                </button>
                <button
                    type="button"
                    class="hidden rounded p-1 hover:!bg-neutral-100 lg:block dark:hover:!bg-neutral-700"
                    :class="{
                        '!bg-neutral-200 dark:!bg-neutral-600':
                            isActive('blockquote'),
                    }"
                    title="Blockquote"
                    @click="runCommand('toggleBlockquote')"
                >
                    <svg
                        class="h-4 w-4"
                        fill="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            d="M14.017 21v-7.391c0-5.704 3.731-9.57 8.983-10.609l.995 2.151c-2.432.917-3.995 3.638-3.995 5.849h4v10h-9.983zm-14.017 0v-7.391c0-5.704 3.748-9.57 9-10.609l.996 2.151c-2.433.917-3.996 3.638-3.996 5.849h3.983v10h-9.983z"
                        />
                    </svg>
                </button>

                <div class="h-6 w-px !bg-neutral-300 dark:!bg-neutral-600" />

                <button
                    type="button"
                    class="rounded p-1 hover:!bg-neutral-100 dark:hover:!bg-neutral-700"
                    :class="{
                        '!bg-neutral-200 dark:!bg-neutral-600':
                            isActive('link'),
                    }"
                    title="Link"
                    @click="openLinkDialog"
                >
                    <Link2 class="h-4 w-4" />
                </button>
                <button
                    type="button"
                    class="hidden rounded p-1 hover:!bg-neutral-100 lg:block dark:hover:!bg-neutral-700"
                    :class="{
                        '!bg-neutral-200 dark:!bg-neutral-600':
                            isActive('table'),
                    }"
                    title="Table"
                    @click="insertTable"
                >
                    <svg
                        class="h-4 w-4"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M4 6h16M4 12h16M4 18h16M8 4v16M16 4v16"
                        />
                    </svg>
                </button>

                <div class="flex-1" />

                <div class="rounded-md !border-1 !border-border bg-background">
                    <button
                        type="button"
                        class="rounded-l-md px-3 py-1 text-sm hover:!bg-neutral-100 dark:hover:!bg-neutral-700"
                        :class="{ '!bg-border': mode === 'editor' }"
                        @click="mode = 'editor'"
                    >
                        Rich
                    </button>
                    <button
                        type="button"
                        class="rounded-r-md px-3 py-1 text-sm hover:!bg-neutral-100 dark:hover:!bg-neutral-700"
                        :class="{ '!bg-border': mode === 'raw' }"
                        @click="mode = 'raw'"
                    >
                        Raw
                    </button>
                </div>

                <div
                    class="ml-2 flex h-7 w-7 items-center justify-center"
                    :title="statusLabel"
                    :aria-label="statusLabel"
                    role="status"
                >
                    <Loader2
                        v-if="saveStatus === 'saving'"
                        class="h-4 w-4 animate-spin text-neutral-500 dark:text-neutral-400"
                    />
                    <Check
                        v-else-if="saveStatus === 'saved'"
                        class="h-4 w-4 text-green-600 dark:text-green-400"
                    />
                    <X
                        v-else-if="saveStatus === 'error'"
                        class="h-4 w-4 text-red-600 dark:text-red-400"
                    />
                </div>
            </div>
        </Teleport>

        <div
            ref="layoutScrollArea"
            class="editor-wrapper flex-1 overflow-auto pb-1"
            @click="handleEditorClick"
        >
            <NovelEditor
                v-show="mode === 'editor'"
                ref="editorRef"
                :extensions="tableExtensions"
                :storage-key="storageKey"
                :on-update="handleUpdate"
                class="h-full"
                class-name="relative min-h-[500px] w-full bg-background p-12 px-8 sm:mb-[calc(20vh)] sm:rounded-lg sm:px-12 sm:shadow-lg max-w-[1024px]"
            />
            <TableBubbleMenu :editor="activeEditor" />
            <textarea
                v-show="mode === 'raw'"
                v-model="rawContent"
                class="h-full w-full resize-none bg-background !p-4 p-4 font-mono text-sm text-foreground focus:outline-none md:!p-8 lg:p-10 xl:!p-12"
                spellcheck="false"
            />
        </div>

        <div
            v-if="linkDialogOpen"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
            @click.self="linkDialogOpen = false"
        >
            <div
                class="w-full max-w-md rounded-lg border bg-background p-4 shadow-lg"
            >
                <div class="mb-4 flex items-center gap-2">
                    <Link2 class="size-4 text-muted-foreground" />
                    <h2 class="text-sm font-medium">Link</h2>
                </div>

                <div class="grid gap-3">
                    <label class="grid gap-1 text-sm">
                        <span class="text-muted-foreground">URL</span>
                        <input
                            v-model="linkUrl"
                            class="h-9 rounded-md border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-ring"
                            placeholder="https://example.com or ../notes.md"
                            @keydown.enter.prevent="applyLink"
                        />
                    </label>

                    <label class="grid gap-1 text-sm">
                        <span
                            class="flex items-center gap-1 text-muted-foreground"
                        >
                            <FileText class="size-3.5" />
                            Document
                        </span>
                        <select
                            class="h-9 rounded-md border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-ring"
                            @change="handleDocumentChange"
                        >
                            <option value="">Select a document</option>
                            <option
                                v-for="path in markdownDocuments"
                                :key="path"
                                :value="path"
                            >
                                {{ path }}
                            </option>
                        </select>
                    </label>
                </div>

                <div class="mt-4 flex justify-end gap-2">
                    <button
                        type="button"
                        class="rounded-md px-3 py-1.5 text-sm hover:bg-muted"
                        @click="linkDialogOpen = false"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        class="rounded-md bg-primary px-3 py-1.5 text-sm text-primary-foreground hover:bg-primary/90"
                        @click="applyLink"
                    >
                        Apply
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
