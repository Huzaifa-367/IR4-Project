import { Volume2, X } from 'lucide-react';
import {
    createContext,
    useCallback,
    useContext,
    useEffect,
    useState,
} from 'react';
import type { ReactNode } from 'react';
import { LiveStatusPill } from '@/components/ir4/live-status-pill';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { useAuth, useSharedSettings } from '@/hooks/use-auth';
import { useReverbChannel } from '@/hooks/use-reverb-channel';
import type { ReverbLiveStatus } from '@/hooks/use-reverb-channel';
import alerts from '@/routes/alerts';
import type { Alert } from '@/types/alert';

/** Session flag — browsers still require one gesture before AudioContext runs. */
const AUDIO_UNLOCK_KEY = 'ir4.alert-audio-unlocked';

let sharedAlertAudioCtx: AudioContext | null = null;

function alertAudioCtor(): typeof AudioContext | null {
    if (typeof window === 'undefined') {
        return null;
    }

    return (
        window.AudioContext ||
        (window as unknown as { webkitAudioContext?: typeof AudioContext })
            .webkitAudioContext ||
        null
    );
}

function readAudioUnlockFlag(): boolean {
    if (typeof window === 'undefined') {
        return false;
    }

    try {
        return sessionStorage.getItem(AUDIO_UNLOCK_KEY) === '1';
    } catch {
        return false;
    }
}

function writeAudioUnlockFlag(): void {
    try {
        sessionStorage.setItem(AUDIO_UNLOCK_KEY, '1');
    } catch {
        // Private mode / blocked storage — in-memory unlock still works this tab.
    }
}

async function unlockAlertAudio(): Promise<boolean> {
    const Ctor = alertAudioCtor();

    if (!Ctor) {
        return false;
    }

    if (
        sharedAlertAudioCtx === null ||
        sharedAlertAudioCtx.state === 'closed'
    ) {
        sharedAlertAudioCtx = new Ctor();
    }

    await sharedAlertAudioCtx.resume();

    if (sharedAlertAudioCtx.state !== 'running') {
        return false;
    }

    writeAudioUnlockFlag();

    return true;
}

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
        onEvent: (payload) => {
            if (payload.alert) {
                onEvent(payload.alert);
            }
        },
        snapshotUrl: alerts.open.url(),
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
    const [audioUnlocked, setAudioUnlocked] = useState(false);

    useEffect(() => {
        if (!isAuthenticated || !readAudioUnlockFlag()) {
            return;
        }

        // Prior session unlock — try resume; browsers often still need the gate.
        void unlockAlertAudio().then((ok) => {
            setAudioUnlocked(ok);
        });
    }, [isAuthenticated]);

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

    const sessionAlerts = isAuthenticated ? openAlerts : [];
    const sessionStatus: ReverbLiveStatus = isAuthenticated
        ? status
        : 'offline';
    const bellCount = sessionAlerts.filter(
        (alert) => alert.status === 'open',
    ).length;
    const hasAudibleCritical = sessionAlerts.some(
        (alert) =>
            alert.audible &&
            alert.severity === 'critical' &&
            alert.status === 'open',
    );
    const needsAudioPermission = isAuthenticated && !audioUnlocked;

    return (
        <AlertContext.Provider
            value={{
                openAlerts: sessionAlerts,
                bellCount,
                live: sessionStatus === 'live',
                status: sessionStatus,
                refresh: isAuthenticated ? refreshFn : async () => undefined,
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
            <AlertToasts alerts={sessionAlerts} />
            <CriticalAudibleLoop
                active={hasAudibleCritical}
                audioUnlocked={audioUnlocked}
            />
            <AlertAudioPermissionGate
                open={needsAudioPermission}
                urgent={hasAudibleCritical}
                onEnabled={() => setAudioUnlocked(true)}
            />
        </AlertContext.Provider>
    );
}

function AlertAudioPermissionGate({
    open,
    urgent,
    onEnabled,
}: {
    open: boolean;
    urgent: boolean;
    onEnabled: () => void;
}): ReactNode {
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const enable = async (): Promise<void> => {
        setBusy(true);
        setError(null);

        const ok = await unlockAlertAudio();
        setBusy(false);

        if (!ok) {
            setError(
                'Browser blocked alarm audio. Check site sound settings, then try again.',
            );

            return;
        }

        onEnabled();
    };

    return (
        <AlertDialog
            open={open}
            onOpenChange={() => {
                // Compulsory — ignore dismiss / Escape until audio is enabled.
            }}
        >
            <AlertDialogContent
                className="z-[100]"
                onEscapeKeyDown={(event) => event.preventDefault()}
            >
                <AlertDialogHeader>
                    <AlertDialogTitle className="flex items-center gap-2">
                        <Volume2 className="size-5 shrink-0 text-red-600" />
                        Enable critical alarm sound
                    </AlertDialogTitle>
                    <AlertDialogDescription>
                        {urgent
                            ? 'A critical audible alert is open. Alarm sound is required before you continue.'
                            : 'This console plays an automatic chime for critical audible alerts. Browsers block sound until you allow it once per session.'}
                    </AlertDialogDescription>
                </AlertDialogHeader>
                {error && (
                    <p className="text-sm text-red-600" role="alert">
                        {error}
                    </p>
                )}
                <AlertDialogFooter>
                    <AlertDialogAction
                        disabled={busy}
                        onClick={(event) => {
                            event.preventDefault();
                            void enable();
                        }}
                    >
                        {busy ? 'Enabling…' : 'Enable alarm sound'}
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}

function CriticalAudibleLoop({
    active,
    audioUnlocked,
}: {
    active: boolean;
    audioUnlocked: boolean;
}): null {
    useEffect(() => {
        if (!active || !audioUnlocked || typeof window === 'undefined') {
            return;
        }

        const ctx = sharedAlertAudioCtx;

        if (!ctx || ctx.state === 'closed') {
            return;
        }

        let stopped = false;
        let timeoutId = 0;
        let looping = false;

        const beep = (): void => {
            if (stopped || ctx.state !== 'running') {
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

        const startLoop = (): void => {
            if (stopped || looping || ctx.state !== 'running') {
                return;
            }

            looping = true;
            beep();
        };

        void ctx.resume().then(startLoop);

        return () => {
            stopped = true;
            window.clearTimeout(timeoutId);
        };
    }, [active, audioUnlocked]);

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
    }, [alert.id, alert.severity, signature, warningToastSeconds, onDismiss]);

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
