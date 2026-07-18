import { createInertiaApp } from '@inertiajs/react';
import { configureEcho } from '@laravel/echo-react';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import DisplayLayout from '@/layouts/display-layout';
import SettingsLayout from '@/layouts/settings/layout';

configureEcho({
    broadcaster: 'reverb',
});

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
            case name.startsWith('settings/roles') ||
                name.startsWith('settings/users') ||
                name.startsWith('settings/assets') ||
                name.startsWith('settings/cameras') ||
                name.startsWith('settings/devices') ||
                name.startsWith('settings/zones') ||
                name.startsWith('settings/repositioning') ||
                name.startsWith('settings/readers'):
                return AppLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    strictMode: true,
    // Providers that do not need usePage() belong here. Toaster uses usePage via
    // useFlashToast, so it must render inside a layout (inside PageContext).
    withApp(app) {
        return (
            <TooltipProvider delayDuration={0}>{app}</TooltipProvider>
        );
    },
    progress: {
        color: '#0B6E4F',
    },
});

// This will set light / dark mode on load...
initializeTheme();
