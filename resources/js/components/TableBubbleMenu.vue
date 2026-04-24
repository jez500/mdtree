<script setup lang="ts">
import type { Editor } from '@tiptap/core';
import { BubbleMenu } from '@tiptap/vue-3';
import { ArrowDown, ArrowLeft, ArrowRight, ArrowUp, Trash2 } from 'lucide-vue-next';

defineProps<{
    editor: Editor | undefined;
}>();
</script>

<template>
    <BubbleMenu
        v-if="editor"
        :editor="editor"
        :plugin-key="'tableBubbleMenu'"
        :should-show="({ editor: activeEditor }) => activeEditor.isActive('table')"
        :tippy-options="{ placement: 'top', duration: 150 }"
    >
        <div class="bg-background border border-border rounded-md shadow-md p-1 flex flex-col gap-1">
            <div class="flex gap-1">
                <button
                    type="button"
                    class="rounded p-1 hover:!bg-neutral-100 dark:hover:!bg-neutral-700"
                    title="Add row above"
                    @click="editor.chain().focus().addRowBefore().run()"
                >
                    <ArrowUp class="h-4 w-4" />
                </button>
                <button
                    type="button"
                    class="rounded p-1 hover:!bg-neutral-100 dark:hover:!bg-neutral-700"
                    title="Add row below"
                    @click="editor.chain().focus().addRowAfter().run()"
                >
                    <ArrowDown class="h-4 w-4" />
                </button>
                <button
                    type="button"
                    class="rounded p-1 hover:!bg-neutral-100 dark:hover:!bg-neutral-700"
                    title="Add column before"
                    @click="editor.chain().focus().addColumnBefore().run()"
                >
                    <ArrowLeft class="h-4 w-4" />
                </button>
                <button
                    type="button"
                    class="rounded p-1 hover:!bg-neutral-100 dark:hover:!bg-neutral-700"
                    title="Add column after"
                    @click="editor.chain().focus().addColumnAfter().run()"
                >
                    <ArrowRight class="h-4 w-4" />
                </button>
            </div>
            <div class="flex gap-1">
                <button
                    type="button"
                    class="rounded p-1 hover:!bg-neutral-100 dark:hover:!bg-neutral-700"
                    title="Delete row"
                    @click="editor.chain().focus().deleteRow().run()"
                >
                    <Trash2 class="h-4 w-4" />
                </button>
                <button
                    type="button"
                    class="rounded p-1 hover:!bg-neutral-100 dark:hover:!bg-neutral-700"
                    title="Delete column"
                    @click="editor.chain().focus().deleteColumn().run()"
                >
                    <Trash2 class="h-4 w-4" />
                </button>
                <button
                    type="button"
                    class="rounded p-1 hover:!bg-neutral-100 dark:hover:!bg-neutral-700"
                    title="Delete table"
                    @click="editor.chain().focus().deleteTable().run()"
                >
                    <Trash2 class="h-4 w-4" />
                </button>
            </div>
        </div>
    </BubbleMenu>
</template>
