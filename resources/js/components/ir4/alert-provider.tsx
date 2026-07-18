import {
    createContext,
    useCallback,
    useContext,
    useEffect,
    useState,
} from 'react';
import type { ReactNode } from 'react';
import { LiveStatusPill } from '@/components/ir4/live-status-pill';
import { useAuth } from '@/hooks/use-auth';
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

    useEffect(() => {
        if (!isAuthenticated) {
            setOpenAlerts([]);
            setStatus('offline');
        }
    }, [isAuthenticated]);

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

function AlertToasts({ alerts }: { alerts: Alert[] }): ReactNode {
    const recent = alerts
        .filter((alert) => alert.status === 'open')
        .slice(0, 5);

    if (recent.length === 0) {
        return null;
    }

    return (
        <div className="pointer-events-none fixed right-4 bottom-4 z-50 flex w-80 flex-col gap-2">
            {recent.map((alert) => (
                <div
                    key={alert.id}
                    className={
                        alert.severity === 'critical'
                            ? 'rounded-md border border-red-600 bg-red-50 p-3 text-sm text-red-950 shadow'
                            : alert.severity === 'warning'
                              ? 'rounded-md border border-amber-500 bg-amber-50 p-3 text-sm text-amber-950 shadow'
                              : 'rounded-md border border-border bg-background p-3 text-sm shadow'
                    }
                >
                    <div className="font-medium">
                        {alert.title}
                        {alert.occurrences > 1 ? ` ×${alert.occurrences}` : ''}
                    </div>
                    <div className="text-xs opacity-80">
                        {alert.alert_type_label}
                    </div>
                </div>
            ))}
        </div>
    );
}
