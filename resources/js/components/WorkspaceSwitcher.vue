<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronsUpDown } from 'lucide-vue-next';
import { computed } from 'vue';
import { show } from '@/actions/App/Http/Controllers/BrowserController';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import type { WorkspaceMap } from '@/types/browser';

const props = defineProps<{
    workspace: string;
    workspaces: WorkspaceMap;
}>();

const { isMobile, state } = useSidebar();

const currentWorkspaceName = computed(() => props.workspaces[props.workspace]?.name ?? props.workspace);
</script>

<template>
    <SidebarMenu>
        <SidebarMenuItem>
            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <SidebarMenuButton
                        size="lg"
                        class="data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground"
                    >
                        <div class="flex flex-col gap-0.5 text-left text-sm leading-tight">
                            <span class="truncate font-semibold">{{ currentWorkspaceName }}</span>
                            <span class="truncate text-xs text-muted-foreground">Workspace</span>
                        </div>
                        <ChevronsUpDown class="ml-auto size-4 shrink-0" />
                    </SidebarMenuButton>
                </DropdownMenuTrigger>
                <DropdownMenuContent
                    class="w-(--reka-dropdown-menu-trigger-width) min-w-48 rounded-lg"
                    :side="isMobile ? 'bottom' : state === 'collapsed' ? 'left' : 'bottom'"
                    align="start"
                    :side-offset="4"
                >
                    <DropdownMenuLabel class="text-xs text-muted-foreground">Workspaces</DropdownMenuLabel>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem
                        v-for="(ws, key) in workspaces"
                        :key="key"
                        as-child
                        :class="key === workspace ? 'font-medium' : ''"
                    >
                        <Link :href="show.url({ workspace: key })">
                            {{ ws.name }}
                        </Link>
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </SidebarMenuItem>
    </SidebarMenu>
</template>
