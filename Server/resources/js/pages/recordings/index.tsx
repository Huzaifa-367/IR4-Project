import { Head, Link, router } from '@inertiajs/react';
import {
    ChevronRight,
    Film,
    Folder,
    HardDrive,
    Video,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { Panel } from '@/components/ir4/panel';
import { StatusPill } from '@/components/ir4/status-pill';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

type BrowseEntry = {
    name: string;
    path: string;
    type: 'dir' | 'file';
    size: number | null;
    mtime: string | null;
    is_playable: boolean;
};

type Breadcrumb = {
    name: string;
    path: string;
};

type Browse = {
    root_exists: boolean;
    root_readable: boolean;
    relative: string;
    breadcrumbs: Breadcrumb[];
    entries: BrowseEntry[];
    truncated: boolean;
    top_level_count: number | null;
};

type Props = {
    browse: Browse;
    streamBaseUrl: string;
};

function formatBytes(size: number | null): string {
    if (size === null) {
        return '—';
    }
    if (size < 1024) {
        return `${size} B`;
    }
    if (size < 1024 * 1024) {
        return `${(size / 1024).toFixed(1)} KB`;
    }
    if (size < 1024 * 1024 * 1024) {
        return `${(size / (1024 * 1024)).toFixed(1)} MB`;
    }

    return `${(size / (1024 * 1024 * 1024)).toFixed(2)} GB`;
}

function formatMtime(iso: string | null): string {
    if (!iso) {
        return '—';
    }
    try {
        return new Date(iso).toLocaleString();
    } catch {
        return iso;
    }
}

function streamUrl(base: string, relativePath: string): string {
    const suffix = relativePath
        .split('/')
        .filter(Boolean)
        .map((segment) => encodeURIComponent(segment))
        .join('/');

    return `${base.replace(/\/$/, '')}/${suffix}`;
}

function browseHref(relativePath: string): string {
    if (relativePath === '') {
        return '/recordings';
    }

    return `/recordings/${relativePath
        .split('/')
        .map((segment) => encodeURIComponent(segment))
        .join('/')}`;
}

export default function RecordingsIndex({ browse, streamBaseUrl }: Props) {
    const [selected, setSelected] = useState<BrowseEntry | null>(null);

    const selectedSrc = useMemo(() => {
        if (!selected?.is_playable) {
            return null;
        }

        return streamUrl(streamBaseUrl, selected.path);
    }, [selected, streamBaseUrl]);

    const rootStatus = !browse.root_exists
        ? { label: 'Root missing', tone: 'crit' as const }
        : !browse.root_readable
          ? { label: 'Not readable', tone: 'warn' as const }
          : {
                label:
                    browse.top_level_count !== null
                        ? `${browse.top_level_count} top-level`
                        : 'Ready',
                tone: 'ok' as const,
            };

    return (
        <>
            <Head title="Recordings" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4 md:p-6">
                <header className="flex flex-wrap items-start justify-between gap-3">
                    <div className="min-w-0">
                        <p className="text-[11px] font-medium tracking-[0.14em] text-text-faint uppercase">
                            Archive
                        </p>
                        <h1 className="mt-1 flex items-center gap-2 text-xl font-semibold tracking-tight text-text md:text-2xl">
                            <HardDrive className="size-5 text-accent" />
                            Recordings
                        </h1>
                        <p className="mt-1 max-w-2xl text-sm text-text-dim">
                            Browse the on-disk video archive as stored on the
                            SCC. Folder names are shown exactly as on disk.
                        </p>
                    </div>
                    <StatusPill tone={rootStatus.tone} label={rootStatus.label} />
                </header>

                <nav
                    aria-label="Path"
                    className="flex flex-wrap items-center gap-1 rounded-[var(--radius)] border border-border bg-surface px-3 py-2 text-sm"
                >
                    {browse.breadcrumbs.map((crumb, index) => {
                        const isLast =
                            index === browse.breadcrumbs.length - 1;

                        return (
                            <span
                                key={`${crumb.path}-${index}`}
                                className="flex items-center gap-1"
                            >
                                {index > 0 ? (
                                    <ChevronRight className="size-3.5 text-text-faint" />
                                ) : null}
                                {isLast ? (
                                    <span className="font-medium text-text">
                                        {crumb.name}
                                    </span>
                                ) : (
                                    <Link
                                        href={browseHref(crumb.path)}
                                        className="text-accent hover:underline"
                                    >
                                        {crumb.name}
                                    </Link>
                                )}
                            </span>
                        );
                    })}
                </nav>

                <div className="grid min-h-0 flex-1 gap-4 xl:grid-cols-[minmax(0,1.4fr)_minmax(320px,0.9fr)]">
                    <Panel
                        title="Contents"
                        subtitle={
                            browse.relative
                                ? browse.relative
                                : 'Archive root'
                        }
                        action={
                            browse.truncated ? (
                                <span className="text-xs text-warn">
                                    List truncated
                                </span>
                            ) : null
                        }
                        className="min-h-[280px]"
                    >
                        {!browse.root_readable ? (
                            <p className="text-sm text-text-dim">
                                Recordings root is missing or not readable.
                                Check RECORDINGS_ROOT and volume mounts on the
                                SCC.
                            </p>
                        ) : browse.entries.length === 0 ? (
                            <p className="text-sm text-text-dim">
                                This folder is empty.
                            </p>
                        ) : (
                            <ul className="divide-y divide-border">
                                {browse.entries.map((entry) => {
                                    const isActive =
                                        selected?.path === entry.path;
                                    const Icon =
                                        entry.type === 'dir' ? Folder : Film;

                                    return (
                                        <li key={entry.path}>
                                            <button
                                                type="button"
                                                className={cn(
                                                    'flex w-full items-center gap-3 px-1 py-2.5 text-left transition-colors',
                                                    isActive
                                                        ? 'bg-surface-2'
                                                        : 'hover:bg-surface-2/60',
                                                )}
                                                onClick={() => {
                                                    if (entry.type === 'dir') {
                                                        setSelected(null);
                                                        router.visit(
                                                            browseHref(
                                                                entry.path,
                                                            ),
                                                        );

                                                        return;
                                                    }
                                                    if (entry.is_playable) {
                                                        setSelected(entry);
                                                    }
                                                }}
                                            >
                                                <Icon
                                                    className={cn(
                                                        'size-4 shrink-0',
                                                        entry.type === 'dir'
                                                            ? 'text-accent'
                                                            : entry.is_playable
                                                              ? 'text-text'
                                                              : 'text-text-faint',
                                                    )}
                                                />
                                                <span className="min-w-0 flex-1 truncate text-sm text-text">
                                                    {entry.name}
                                                </span>
                                                <span className="hidden shrink-0 text-xs text-text-faint sm:inline">
                                                    {formatMtime(entry.mtime)}
                                                </span>
                                                <span className="w-20 shrink-0 text-right text-xs text-text-dim tabular-nums">
                                                    {entry.type === 'dir'
                                                        ? 'Folder'
                                                        : formatBytes(
                                                              entry.size,
                                                          )}
                                                </span>
                                            </button>
                                        </li>
                                    );
                                })}
                            </ul>
                        )}
                    </Panel>

                    <Panel
                        title="Player"
                        subtitle={
                            selected
                                ? selected.name
                                : 'Select a video file to play'
                        }
                        className="min-h-[280px]"
                    >
                        {selectedSrc ? (
                            <div className="space-y-3">
                                <div className="overflow-hidden rounded-[var(--radius)] border border-border bg-bg">
                                    <video
                                        key={selectedSrc}
                                        className="aspect-video w-full bg-black"
                                        controls
                                        playsInline
                                        preload="metadata"
                                        src={selectedSrc}
                                    >
                                        Your browser cannot play this file.
                                    </video>
                                </div>
                                <div className="flex flex-wrap items-center gap-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={() => setSelected(null)}
                                    >
                                        Clear
                                    </Button>
                                    <a
                                        href={selectedSrc}
                                        className="inline-flex items-center gap-1 text-xs text-accent hover:underline"
                                        target="_blank"
                                        rel="noreferrer"
                                    >
                                        <Video className="size-3.5" />
                                        Open stream URL
                                    </a>
                                </div>
                                <p className="text-xs text-text-faint">
                                    Playback uses a same-origin authenticated
                                    stream (Range / seek supported).
                                </p>
                            </div>
                        ) : (
                            <div className="flex h-48 flex-col items-center justify-center gap-2 text-center text-sm text-text-dim">
                                <Film className="size-8 text-text-faint" />
                                <p>No video selected.</p>
                                <p className="max-w-xs text-xs text-text-faint">
                                    Open a date folder and click an hour video
                                    file (mp4, mkv, …).
                                </p>
                            </div>
                        )}
                    </Panel>
                </div>
            </div>
        </>
    );
}

RecordingsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Recordings',
            href: '/recordings',
        },
    ],
};
