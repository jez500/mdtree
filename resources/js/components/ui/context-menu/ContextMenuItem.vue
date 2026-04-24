<script setup lang="ts">
import type { ContextMenuItemProps } from 'reka-ui';
import type { HTMLAttributes } from 'vue';
import { reactiveOmit } from '@vueuse/core';
import { ContextMenuItem, useForwardProps } from 'reka-ui';
import { cn } from '@/lib/utils';

const props = defineProps<ContextMenuItemProps & { class?: HTMLAttributes['class'] }>();

const delegatedProps = reactiveOmit(props, 'class');
const forwarded = useForwardProps(delegatedProps);
</script>

<template>
    <ContextMenuItem
        data-slot="context-menu-item"
        v-bind="forwarded"
        :class="cn('relative flex cursor-default select-none items-center rounded-sm px-2 py-1.5 text-sm outline-none focus:bg-accent focus:text-accent-foreground', props.class)"
    >
        <slot />
    </ContextMenuItem>
</template>
