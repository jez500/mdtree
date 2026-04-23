<script setup lang="ts">
import type { Editor as TiptapEditor } from '@tiptap/core';
import { useDebounceFn } from '@vueuse/core';
import { Check, Loader2, X } from 'lucide-vue-next';
import { Editor as NovelEditor } from 'novel-vue';
import { computed, nextTick, ref, watch } from 'vue';
import { save as saveFileRoute } from '@/routes/files';

const props = defineProps<{
    content: string | null;
    workspace: string;
    filePath: string;
}>();

type SaveStatus = 'saving' | 'saved' | 'error';
type Mode = 'editor' | 'raw';

const saveStatus = ref<SaveStatus>('saved');
const mode = ref<Mode>('editor');
const rawContent = ref(props.content ?? '');
// exposeProxy auto-unwraps refs, so .editor is the Editor instance directly (not a ShallowRef)
const editorRef = ref<{ editor: TiptapEditor | undefined } | null>(null);
const transactionTick = ref(0);

let suppressSave = true;
const editorInitialized = ref(false);

const storageKey = computed(() => `mdtree__${props.workspace}__${props.filePath}`);

watch(
    () => editorRef.value?.editor,
    async (ed) => {
        if (!ed || editorInitialized.value) return;
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
        rawContent.value = getMarkdownFrom(ed);
        suppressSave = false;
    },
    { immediate: true },
);

const saveFile = useDebounceFn(async (markdown: string) => {
    saveStatus.value = 'saving';

    try {
        const target = saveFileRoute({ workspace: props.workspace }, { query: { path: props.filePath } });

        const response = await fetch(target.url, {
            method: 'PUT',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken(),
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
    return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
}

function getMarkdownFrom(editor: TiptapEditor): string {
    const holder = editor as unknown as { storage?: Record<string, { getMarkdown?: () => string }> };

    return holder.storage?.markdown?.getMarkdown?.() ?? '';
}

const handleUpdate = (editor?: TiptapEditor) => {
    if (!editor) return;
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
        const ed = editorRef.value?.editor;
        if (!ed) return;

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
    const ed = editorRef.value?.editor;
    if (!ed) return;

    if (newMode === 'raw') {
        return;
    }

    suppressSave = true;
    ed.commands.setContent(rawContent.value, false);
    suppressSave = false;
});

const setLink = () => {
    const ed = editorRef.value?.editor;
    if (!ed) return;

    const previousUrl = ed.getAttributes('link').href as string | undefined;
    const url = window.prompt('Enter URL', previousUrl ?? '');

    if (url === null) {
        return;
    }

    const chain = ed.chain().focus().extendMarkRange('link');

    if (url === '') {
        chain.unsetLink().run();
    } else {
        chain.setLink({ href: url }).run();
    }
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

const isActive = (name: string, attributes: Record<string, unknown> = {}): boolean => {
    transactionTick.value; // reactive dependency on editor state changes
    return editorRef.value?.editor?.isActive(name, attributes) ?? false;
};

const runCommand = (name: string, ...args: unknown[]): void => {
    const ed = editorRef.value?.editor;
    if (!ed) return;

    const chain = ed.chain().focus() as unknown as Record<string, (...a: unknown[]) => { run: () => boolean }>;
    chain[name](...args).run();
};
</script>

<template>
    <div class="flex h-full flex-col">
        <div class="flex flex-wrap items-center gap-2 border-b bg-sidebar p-2 dark:border-neutral-700 !border-b !border-border">
            <button
                type="button"
                class="rounded p-1 hover:!bg-neutral-100 dark:hover:!bg-neutral-700"
                :class="{ '!bg-neutral-200 dark:!bg-neutral-600': isActive('heading', { level: 1 }) }"
                title="Heading 1"
                @click="runCommand('toggleHeading', { level: 1 })"
            >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 12h12M6 6h12M6 18h8" />
                </svg>
            </button>
            <button
                type="button"
                class="rounded p-1 hover:!bg-neutral-100 dark:hover:!bg-neutral-700"
                :class="{ '!bg-neutral-200 dark:!bg-neutral-600': isActive('heading', { level: 2 }) }"
                title="Heading 2"
                @click="runCommand('toggleHeading', { level: 2 })"
            >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 12h12M6 6h12M6 18h10" />
                </svg>
            </button>
            <button
                type="button"
                class="rounded p-1 hover:!bg-neutral-100 dark:hover:!bg-neutral-700"
                :class="{ '!bg-neutral-200 dark:!bg-neutral-600': isActive('heading', { level: 3 }) }"
                title="Heading 3"
                @click="runCommand('toggleHeading', { level: 3 })"
            >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 12h12M6 6h12M6 18h6" />
                </svg>
            </button>

            <div class="h-6 w-px !bg-neutral-300 dark:!bg-neutral-600" />

            <button
                type="button"
                class="rounded p-1 font-bold hover:!bg-neutral-100 dark:hover:!bg-neutral-700"
                :class="{ '!bg-neutral-200 dark:!bg-neutral-600': isActive('bold') }"
                title="Bold"
                @click="runCommand('toggleBold')"
            >
                B
            </button>
            <button
                type="button"
                class="rounded p-1 italic hover:!bg-neutral-100 dark:hover:!bg-neutral-700"
                :class="{ '!bg-neutral-200 dark:!bg-neutral-600': isActive('italic') }"
                title="Italic"
                @click="runCommand('toggleItalic')"
            >
                I
            </button>
            <button
                type="button"
                class="rounded p-1 line-through hover:!bg-neutral-100 dark:hover:!bg-neutral-700"
                :class="{ '!bg-neutral-200 dark:!bg-neutral-600': isActive('strike') }"
                title="Strikethrough"
                @click="runCommand('toggleStrike')"
            >
                S
            </button>

            <div class="h-6 w-px !bg-neutral-300 dark:!bg-neutral-600" />

            <button
                type="button"
                class="rounded p-1 hover:!bg-neutral-100 dark:hover:!bg-neutral-700"
                :class="{ '!bg-neutral-200 dark:!bg-neutral-600': isActive('bulletList') }"
                title="Bullet List"
                @click="runCommand('toggleBulletList')"
            >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>
            <button
                type="button"
                class="rounded p-1 hover:!bg-neutral-100 dark:hover:!bg-neutral-700"
                :class="{ '!bg-neutral-200 dark:!bg-neutral-600': isActive('orderedList') }"
                title="Ordered List"
                @click="runCommand('toggleOrderedList')"
            >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h10M7 16h10M3 8h.01M3 12h.01M3 16h.01" />
                </svg>
            </button>
            <button
                type="button"
                class="rounded p-1 hover:!bg-neutral-100 dark:hover:!bg-neutral-700"
                :class="{ '!bg-neutral-200 dark:!bg-neutral-600': isActive('taskList') }"
                title="Task List"
                @click="runCommand('toggleTaskList')"
            >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 11l3 3L22 4M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11" />
                </svg>
            </button>

            <div class="h-6 w-px !bg-neutral-300 dark:!bg-neutral-600" />

            <button
                type="button"
                class="rounded p-1 hover:!bg-neutral-100 dark:hover:!bg-neutral-700"
                :class="{ '!bg-neutral-200 dark:!bg-neutral-600': isActive('code') }"
                title="Inline Code"
                @click="runCommand('toggleCode')"
            >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" />
                </svg>
            </button>
            <button
                type="button"
                class="rounded p-1 hover:!bg-neutral-100 dark:hover:!bg-neutral-700"
                :class="{ '!bg-neutral-200 dark:!bg-neutral-600': isActive('codeBlock') }"
                title="Code Block"
                @click="runCommand('toggleCodeBlock')"
            >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l-4 3 4 3M16 9l4 3-4 3M14 4l-4 16" />
                </svg>
            </button>
            <button
                type="button"
                class="rounded p-1 hover:!bg-neutral-100 dark:hover:!bg-neutral-700"
                :class="{ '!bg-neutral-200 dark:!bg-neutral-600': isActive('blockquote') }"
                title="Blockquote"
                @click="runCommand('toggleBlockquote')"
            >
                <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M14.017 21v-7.391c0-5.704 3.731-9.57 8.983-10.609l.995 2.151c-2.432.917-3.995 3.638-3.995 5.849h4v10h-9.983zm-14.017 0v-7.391c0-5.704 3.748-9.57 9-10.609l.996 2.151c-2.433.917-3.996 3.638-3.996 5.849h3.983v10h-9.983z" />
                </svg>
            </button>

            <div class="h-6 w-px !bg-neutral-300 dark:!bg-neutral-600" />

            <button
                type="button"
                class="rounded p-1 hover:!bg-neutral-100 dark:hover:!bg-neutral-700"
                :class="{ '!bg-neutral-200 dark:!bg-neutral-600': isActive('link') }"
                title="Link"
                @click="setLink"
            >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                </svg>
            </button>

            <div class="flex-1" />

            <div class="bg-background !border-1 !border-border rounded-md">
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
                <Loader2 v-if="saveStatus === 'saving'" class="h-4 w-4 animate-spin text-neutral-500 dark:text-neutral-400" />
                <Check v-else-if="saveStatus === 'saved'" class="h-4 w-4 text-green-600 dark:text-green-400" />
                <X v-else-if="saveStatus === 'error'" class="h-4 w-4 text-red-600 dark:text-red-400" />
            </div>
        </div>

        <div class="flex-1 overflow-auto">
            <NovelEditor
                v-show="mode === 'editor'"
                ref="editorRef"
                :storage-key="storageKey"
                :on-update="handleUpdate"
                class="h-full"
                class-name="relative min-h-[500px] w-full max-w-screen-lg bg-background p-12 px-8 sm:mb-[calc(20vh)] sm:rounded-lg sm:px-12 sm:shadow-lg"
            />
            <textarea
                v-show="mode === 'raw'"
                v-model="rawContent"
                class="h-full w-full resize-none bg-background p-4 font-mono text-sm text-foreground focus:outline-none lg:p-10 !p-4 md:!p-8 xl:!p-12"
                spellcheck="false"
            />
        </div>
    </div>
</template>
