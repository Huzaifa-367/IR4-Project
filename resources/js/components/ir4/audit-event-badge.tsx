import type { AuditEvent } from '@/types/audit';

const eventStyles: Record<AuditEvent, string> = {
    login: 'bg-emerald-500/15 text-emerald-300',
    logout: 'bg-slate-500/15 text-slate-300',
    login_failed: 'bg-red-500/15 text-red-300',
    data_access: 'bg-cyan-500/15 text-cyan-300',
    created: 'bg-emerald-500/15 text-emerald-300',
    updated: 'bg-amber-500/15 text-amber-300',
    deleted: 'bg-red-500/15 text-red-300',
    config_changed: 'bg-amber-500/15 text-amber-300',
    published: 'bg-violet-500/15 text-violet-300',
    acknowledged: 'bg-blue-500/15 text-blue-300',
    exported: 'bg-indigo-500/15 text-indigo-300',
    wiped: 'bg-red-500/15 text-red-300',
};

type Props = {
    event: AuditEvent;
};

export function AuditEventBadge({ event }: Props) {
    return (
        <span
            className={`inline-flex rounded-full px-2 py-0.5 text-xs font-medium ${eventStyles[event]}`}
        >
            {event.replaceAll('_', ' ')}
        </span>
    );
}
