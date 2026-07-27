import { createInertiaApp } from '@inertiajs/react';
import { configureEcho } from '@laravel/echo-react';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import DisplayLayout from '@/layouts/display-layout';
import SettingsLayout from '@/layouts/settings/layout';

const reverbAppKey = import.meta.env.VITE_REVERB_APP_KEY;

// Hostinger / CI builds often have no Reverb key. Never instantiate Pusher
// without a key (blank dashboard). Null broadcaster + poll fallback (DOC-08).
if (typeof reverbAppKey === 'string' && reverbAppKey.length > 0) {
    configureEcho({
        broadcaster: 'reverb',
        key: reverbAppKey,
    });
} else {
    configureEcho({
        broadcaster: 'null',
    });
}

const appName = import.meta.env.VITE_APP_NAME || 'IR4';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === 'welcome':
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('display/'):
                return DisplayLayout;
            // Personal account settings keep the nested settings chrome.
            case name === 'settings/profile' ||
                name === 'settings/security' ||
                name === 'settings/appearance':
                return [AppLayout, SettingsLayout];
            // Command-centre pages (including regrouped hardware/access/workforce).
            default:
                return AppLayout;
        }
    },
    strictMode: true,
    // Providers that do not need usePage() belong here. Toaster uses usePage via
    // useFlashToast, so it must render inside a layout (inside PageContext).
    withApp(app) {
        return <TooltipProvider delayDuration={0}>{app}</TooltipProvider>;
    },
    progress: {
        color: '#0B6E4F',
    },
});

// This will set light / dark mode on load...
initializeTheme();
