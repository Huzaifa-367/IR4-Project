import { usePage } from '@inertiajs/react';

export type AuthUser = {
    id: number;
    name: string;
    email: string;
    must_change_password: boolean;
    two_factor_enabled: boolean;
    roles: string[];
    permissions: string[];
};

export type SharedAppSettings = {
    session_timeout_minutes: number;
    display_keep_session_alive: boolean;
    poll_fallback_seconds: number;
    warning_toast_seconds: number;
    theme_default: string;
};

type SharedAuth = {
    auth: {
        user: AuthUser | null;
    };
    settings?: SharedAppSettings;
};

export function useAuth(): {
    user: AuthUser | null;
    isAuthenticated: boolean;
} {
    const { auth } = usePage<SharedAuth>().props;

    return {
        user: auth.user,
        isAuthenticated: auth.user !== null,
    };
}

/** Idle timeout minutes from DOC-18 `auth.session_timeout_minutes`. */
export function useSettingsTimeoutMinutes(): number {
    const { settings } = usePage<SharedAuth>().props;

    return settings?.session_timeout_minutes ?? 15;
}

export function useSharedSettings(): SharedAppSettings {
    const { settings } = usePage<SharedAuth>().props;

    return (
        settings ?? {
            session_timeout_minutes: 15,
            display_keep_session_alive: true,
            poll_fallback_seconds: 30,
            warning_toast_seconds: 10,
            theme_default: 'dark',
        }
    );
}
