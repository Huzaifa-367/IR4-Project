import { X } from 'lucide-react';
import {
    createContext,
    useCallback,
    useContext,
    useEffect,
    useState,
} from 'react';
import type { ReactNode } from 'react';
import { LiveStatusPill } from '@/components/ir4/live-status-pill';
import { useAuth, useSharedSettings } from '@/hooks/use-auth';
import { useReverbChannel } from '@/hooks/use-reverb-channel';
import type { ReverbLiveStatus } from '@/hooks/use-reverb-channel';
import type { Alert } from '@/types/alert';

type AlertStore = {
    openAlerts: Alert[];
    bellCount: number;
    live: boolean;
    status: ReverbLiveStatus;
    refresh: () => Promise<void>;
};

const AlertContext = createContext<AlertStore | null>(null);

function isOpenLike(alert: Alert): boolean {
    return alert.status === 'open' || alert.status === 'acknowledged';
}

function upsertAlert(current: Alert[], alert: Alert): Alert[] {
    const without = current.filter((item) => item.id !== alert.id);

    if (!isOpenLike(alert)) {
        return without;
    }

    return [alert, ...without].sort(
        (a, b) => Date.parse(b.raised_at) - Date.parse(a.raised_at),
    );
}

function AlertsReverbBridge({
    onEvent,
    onSnapshot,
    onBridge,
}: {
    onEvent: (alert: Alert) => void;
    onSnapshot: (alerts: Alert[]) => void;
    onBridge: (bridge: {
        status: ReverbLiveStatus;
        refresh: () => Promise<void>;
    }) => void;
}): null {
    const handleSnapshot = useCallback(
        (data: unknown): void => {
            const json = data as { data: Alert[] };
            onSnapshot(json.data.filter(isOpenLike));
        },
        [onSnapshot],
    );

    const { status, refresh } = useReverbChannel<{ alert: Alert }>({
        channel: 'alerts',
        events: ['.AlertRaised', '.AlertUpdated'],
        onEvent: (payload) => onEvent(payload.alert),
        snapshotUrl: '/api/alerts/open',
        onSnapshot: handleSnapshot,
        pollIntervalMs: 30_000,
    });

    useEffect(() => {
        onBridge({ status, refresh });
    }, [status, refresh, onBridge]);

    return null;
}

export function AlertProvider({
    children,
}: {
    children: ReactNode;
}): ReactNode {
    const { isAuthenticated } = useAuth();
    const [openAlerts, setOpenAlerts] = useState<Alert[]>([]);
    const [status, setStatus] = useState<ReverbLiveStatus>('offline');
    const [refreshFn, setRefreshFn] = useState<() => Promise<void>>(
        () => async () => undefined,
    );

    const onEvent = useCallback((alert: Alert): void => {
        setOpenAlerts((current) => upsertAlert(current, alert));
    }, []);

    const onSnapshot = useCallback((alerts: Alert[]): void => {
        setOpenAlerts(alerts);
    }, []);

    const onBridge = useCallback(
        (bridge: {
            status: ReverbLiveStatus;
            refresh: () => Promise<void>;
        }): void => {
            setStatus(bridge.status);
            setRefreshFn(() => bridge.refresh);
        },
        [],
    );

    if (!isAuthenticated && (openAlerts.length > 0 || status !== 'offline')) {
        setOpenAlerts([]);
        setStatus('offline');
    }

    const bellCount = openAlerts.filter(
        (alert) => alert.status === 'open',
    ).length;
    const hasAudibleCritical = openAlerts.some(
        (alert) =>
            alert.audible &&
            alert.severity === 'critical' &&
            alert.status === 'open',
    );

    return (
        <AlertContext.Provider
            value={{
                openAlerts,
                bellCount,
                live: status === 'live',
                status,
                refresh: refreshFn,
            }}
        >
            {isAuthenticated && (
                <AlertsReverbBridge
                    onEvent={onEvent}
                    onSnapshot={onSnapshot}
                    onBridge={onBridge}
                />
            )}
            {children}
            <AlertToasts alerts={openAlerts} />
            <CriticalAudibleLoop active={hasAudibleCritical} />
        </AlertContext.Provider>
    );
}

function CriticalAudibleLoop({ active }: { active: boolean }): null {
    useEffect(() => {
        if (!active || typeof window === 'undefined') {
            return;
        }

        const AudioCtx =
            window.AudioContext ||
            (window as unknown as { webkitAudioContext?: typeof AudioContext })
                .webkitAudioContext;

        if (!AudioCtx) {
            return;
        }

        const ctx = new AudioCtx();
        let stopped = false;
        let timeoutId = 0;

        const beep = (): void => {
            if (stopped) {
                return;
            }

            const oscillator = ctx.createOscillator();
            const gain = ctx.createGain();
            oscillator.type = 'sine';
            oscillator.frequency.value = 880;
            gain.gain.value = 0.08;
            oscillator.connect(gain);
            gain.connect(ctx.destination);
            oscillator.start();
            oscillator.stop(ctx.currentTime + 0.18);
            timeoutId = window.setTimeout(beep, 900);
        };

        void ctx.resume().then(beep);

        return () => {
            stopped = true;
            window.clearTimeout(timeoutId);
            void ctx.close();
        };
    }, [active]);

    return null;
}

export function useAlertStore(): AlertStore {
    const ctx = useContext(AlertContext);

    if (!ctx) {
        return {
            openAlerts: [],
            bellCount: 0,
            live: false,
            status: 'offline',
            refresh: async () => undefined,
        };
    }

    return ctx;
}

export function AlertLiveIndicator(): ReactNode {
    const { status } = useAlertStore();

    return <LiveStatusPill status={status} />;
}

/** Identity for toast resurfacing when a dedupe bump refreshes raised_at / occurrences. */
function toastSignature(alert: Alert): string {
    return `${alert.id}:${alert.occurrences}:${alert.raised_at}`;
}

function AlertToasts({ alerts }: { alerts: Alert[] }): ReactNode {
    const { warning_toast_seconds: warningToastSeconds } = useSharedSettings();
    const open = alerts.filter((alert) => alert.status === 'open');
    /** id → signature that was dismissed (manual or auto). */
    const [dismissed, setDismissed] = useState<Record<number, string>>({});

    const dismiss = useCallback((alertId: number, signature: string): void => {
        setDismissed((prev) =>
            prev[alertId] === signature
                ? prev
                : { ...prev, [alertId]: signature },
        );
    }, []);

    const visible = open
        .filter((alert) => dismissed[alert.id] !== toastSignature(alert))
        .slice(0, 5);

    if (visible.length === 0) {
        return null;
    }

    return (
        <div className="pointer-events-none fixed right-4 bottom-4 z-50 flex w-80 flex-col gap-2">
            {visible.map((alert) => (
                <AlertToastCard
                    key={toastSignature(alert)}
                    alert={alert}
                    warningToastSeconds={warningToastSeconds}
                    onDismiss={dismiss}
                />
            ))}
        </div>
    );
}

function AlertToastCard({
    alert,
    warningToastSeconds,
    onDismiss,
}: {
    alert: Alert;
    warningToastSeconds: number;
    onDismiss: (alertId: number, signature: string) => void;
}): ReactNode {
    const signature = toastSignature(alert);

    // Critical stays until ack (DOC-07); warning/info auto-dismiss. Manual X always available.
    useEffect(() => {
        if (alert.severity === 'critical') {
            return;
        }

        const ms =
            alert.severity === 'warning'
                ? Math.max(1, warningToastSeconds) * 1000
                : 5_000;
        const timer = window.setTimeout(
            () => onDismiss(alert.id, signature),
            ms,
        );

        return () => window.clearTimeout(timer);
    }, [
        alert.id,
        alert.severity,
        signature,
        warningToastSeconds,
        onDismiss,
    ]);

    const toneClass =
        alert.severity === 'critical'
            ? 'border-red-600 bg-red-50 text-red-950'
            : alert.severity === 'warning'
              ? 'border-amber-500 bg-amber-50 text-amber-950'
              : 'border-border bg-background text-foreground';

    return (
        <div
            className={`pointer-events-auto relative rounded-md border p-3 pr-9 text-sm shadow ${toneClass}`}
            role="status"
        >
            <button
                type="button"
                className="absolute top-2 right-2 rounded p-0.5 opacity-70 transition-opacity hover:opacity-100 focus-visible:ring-2 focus-visible:outline-none"
                aria-label="Dismiss alert"
                onClick={() => onDismiss(alert.id, signature)}
            >
                <X className="size-3.5" />
            </button>
            <div className="font-medium">
                {alert.title}
                {alert.occurrences > 1 ? ` ×${alert.occurrences}` : ''}
            </div>
            <div className="text-xs opacity-80">{alert.alert_type_label}</div>
        </div>
    );
}
