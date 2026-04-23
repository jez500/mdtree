<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import BrowserSidebar from '@/components/BrowserSidebar.vue';
import { Toaster } from '@/components/ui/sonner';
</script>

<template>
    <AppShell variant="sidebar">
        <BrowserSidebar />
        <AppContent variant="sidebar" class="h-svh overflow-y-auto">
            <slot />
        </AppContent>
        <Toaster />
    </AppShell>
</template>
