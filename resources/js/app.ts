import { createInertiaApp, router } from '@inertiajs/vue3';
import '@/extensions/tableSlashCommand';
import { initializeTheme } from '@/composables/useAppearance';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import BrowserLayout from '@/layouts/BrowserLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { initializeFlashToast } from '@/lib/flashToast';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

const scrollLayoutAreasToTop = (): void => {
    for (const element of document.querySelectorAll<HTMLElement>('.layout-scrollarea')) {
        element.scrollTo({ top: 0, left: 0, behavior: 'auto' });
    }
};

router.on('navigate', () => {
    requestAnimationFrame(() => {
        scrollLayoutAreasToTop();
    });
});

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === 'Welcome':
                return null;
            case name === 'Browser':
                return BrowserLayout;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on page load...
initializeTheme();

// This will listen for flash toast data from the server...
initializeFlashToast();
