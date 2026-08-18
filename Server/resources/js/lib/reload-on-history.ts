import { router } from '@inertiajs/react';

type InertiaPageSnapshot = {
    component: string;
    url: string;
    version?: string;
    props: Record<string, unknown>;
};

const VOLATILE_PROP_KEYS = new Set(['errors', 'flash', 'ziggy', 'sidebarOpen']);

/**
 * Inertia restores the previous page from history on back/forward. Refetch
 * Laravel in the background and only swap the page when domain data changed.
 */
export function enableHistoryReload(): void {
    if (typeof window === 'undefined') {
        return;
    }

    let shouldCheck = isBackForwardNavigation();
    let checking = false;
    let latestPage: InertiaPageSnapshot | null = null;

    window.addEventListener('popstate', (event) => {
        if (event.state === null) {
            return;
        }

        shouldCheck = true;
    });

    router.on('navigate', (event) => {
        latestPage = toSnapshot(event.detail.page);

        if (!shouldCheck || checking) {
            return;
        }

        shouldCheck = false;
        void checkAndReload();
    });

    window.addEventListener('pageshow', (event) => {
        if (event.persisted) {
            shouldCheck = true;
        }
    });

    async function checkAndReload(): Promise<void> {
        const stale = latestPage;

        if (stale === null) {
            return;
        }

        checking = true;

        try {
            const fresh = await fetchInertiaPage(stale.version);

            if (fresh === 'reload' || pageDataChanged(stale, fresh)) {
                router.reload({ replace: true });
            }
        } catch {
            // Keep the restored snapshot if the probe fails.
        } finally {
            checking = false;
        }
    }
}

export function pageDataChanged(
    stale: InertiaPageSnapshot,
    fresh: InertiaPageSnapshot,
): boolean {
    return pageDataFingerprint(stale) !== pageDataFingerprint(fresh);
}

export function pageDataFingerprint(page: InertiaPageSnapshot): string {
    return JSON.stringify({
        component: page.component,
        url: normalizePageUrl(page.url),
        props: omitVolatileProps(page.props),
    });
}

function omitVolatileProps(
    props: Record<string, unknown>,
): Record<string, unknown> {
    const kept: Record<string, unknown> = {};

    for (const [key, value] of Object.entries(props)) {
        if (!VOLATILE_PROP_KEYS.has(key)) {
            kept[key] = value;
        }
    }

    return kept;
}

function normalizePageUrl(url: string): string {
    try {
        const parsed = new URL(url, window.location.origin);

        return `${parsed.pathname}${parsed.search}`;
    } catch {
        return url;
    }
}

function toSnapshot(page: {
    component: string;
    url: string;
    version?: string | number | null;
    props: unknown;
}): InertiaPageSnapshot {
    return {
        component: page.component,
        url: page.url,
        version:
            page.version === null || page.version === undefined
                ? undefined
                : String(page.version),
        props:
            page.props !== null &&
            typeof page.props === 'object' &&
            !Array.isArray(page.props)
                ? (page.props as Record<string, unknown>)
                : {},
    };
}

async function fetchInertiaPage(
    version: string | undefined,
): Promise<InertiaPageSnapshot | 'reload'> {
    const headers: Record<string, string> = {
        'X-Inertia': 'true',
        'X-Requested-With': 'XMLHttpRequest',
        Accept: 'text/html, application/xhtml+xml',
    };

    if (version !== undefined && version !== '') {
        headers['X-Inertia-Version'] = version;
    }

    const response = await fetch(window.location.href, {
        headers,
        credentials: 'same-origin',
        cache: 'no-store',
    });

    if (response.status === 409) {
        return 'reload';
    }

    const contentType = response.headers.get('content-type') ?? '';

    if (!response.ok || !contentType.includes('json')) {
        return 'reload';
    }

    const payload: unknown = await response.json();

    if (
        payload === null ||
        typeof payload !== 'object' ||
        !('component' in payload) ||
        !('url' in payload) ||
        !('props' in payload)
    ) {
        return 'reload';
    }

    const page = payload as {
        component: string;
        url: string;
        version?: string | number | null;
        props: unknown;
    };

    return toSnapshot(page);
}

function isBackForwardNavigation(): boolean {
    const entry = window.performance?.getEntriesByType('navigation')[0];

    return (
        entry !== undefined && 'type' in entry && entry.type === 'back_forward'
    );
}
