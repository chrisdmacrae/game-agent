import { createInertiaApp } from '@inertiajs/vue3';
import { initializeTheme } from '@/composables/useAppearance';
import AuthLayout from '@/layouts/AuthLayout.vue';
import SiteLayout from '@/layouts/byb/SiteLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { initializeFlashToast } from '@/lib/flashToast';

const appName = import.meta.env.VITE_APP_NAME || 'Build Your Build';

createInertiaApp({
    // Every page title reads "X — Build Your Build".
    title: (title) =>
        title && title !== appName ? `${title} — ${appName}` : appName,
    layout: (name) => {
        switch (true) {
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [SiteLayout, SettingsLayout];
            default:
                return SiteLayout;
        }
    },
    progress: {
        color: '#2DE1C2',
    },
});

// Build Your Build is dark only; this keeps the html class in place...
initializeTheme();

// This will listen for flash toast data from the server...
initializeFlashToast();
