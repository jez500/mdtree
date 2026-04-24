<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import BrowserSidebar from '@/components/BrowserSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import { Toaster } from '@/components/ui/sonner';

</script>

<template>
    <AppShell variant="sidebar">
        <BrowserSidebar />

        <AppContent variant="sidebar" class="flex h-svh min-h-0 flex-col overflow-hidden">
            <AppSidebarHeader>
                <div id="browser-header-slot" class="min-w-0 flex-1" />
                <slot name="header" />
            </AppSidebarHeader>
            <div class="layout-scrollarea min-h-0 flex-1 ">
                <slot />
            </div>
        </AppContent>
        <Toaster />
    </AppShell>
</template>
