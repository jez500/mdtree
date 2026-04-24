import type { Editor, Range } from '@tiptap/core';
import type { Component } from 'vue';

export interface SlashCommandItem {
    title: string;
    description: string;
    searchTerms?: string[];
    icon: Component;
    command: (props: { editor: Editor; range: Range }) => void;
}

type SlashRegistryWindow = Window & {
    __novelSlashItems?: SlashCommandItem[];
};

const registry: SlashCommandItem[] = [];

export function registerSlashCommand(item: SlashCommandItem): void {
    registry.push(item);

    if (typeof window !== 'undefined') {
        (window as SlashRegistryWindow).__novelSlashItems = [...registry];
    }
}
