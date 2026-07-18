import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { CustodyBadge, OverdueBadge } from '@/components/ir4/equipment-badges';
import { ReturnDialog } from '@/components/ir4/return-dialog';
import { Button } from '@/components/ui/button';
import type { EquipmentCheckout } from '@/types/equipment';

type Props = {
    checkouts: {
        data: EquipmentCheckout[];
        meta: {
            current_page: number;
            last_page: number;
            per_page: number;
            total: number;
        };
    };
    filters: {
        open: boolean;
        search: string;
    };
    canManage: boolean;
};

export default function EquipmentCheckoutsIndex({
    checkouts,
    filters,
    canManage,
}: Props) {
    const [returnTarget, setReturnTarget] = useState<EquipmentCheckout | null>(
        null,
    );

    function setOpenFilter(open: boolean): void {
        router.get(
            '/equipment/checkouts',
            {
                open: open ? '1' : '0',
                search: filters.search || undefined,
            },
            { preserveState: true, replace: true },
        );
    }

    return (
        <>
            <Head title="Equipment checkouts" />
            <div className="space-y-6 p-4 sm:p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Checkouts"
                        description="Open custody and return history across all equipment."
                    />
                    <Button asChild variant="outline" size="sm">
                        <Link href="/equipment">Equipment list</Link>
                    </Button>
                </div>

                <div className="flex flex-wrap gap-2">
                    <Button
                        type="button"
                        size="sm"
                        variant={filters.open ? 'default' : 'outline'}
                        onClick={() => setOpenFilter(true)}
                    >
                        Open
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        variant={!filters.open ? 'default' : 'outline'}
                        onClick={() => setOpenFilter(false)}
                    >
                        History
                    </Button>
                </div>

                <form
                    className="flex flex-wrap items-end gap-3"
                    onSubmit={(event) => {
                        event.preventDefault();
                        const form = new FormData(event.currentTarget);
                        router.get(
                            '/equipment/checkouts',
                            {
                                open: filters.open ? '1' : '0',
                                search: String(form.get('search') ?? ''),
                            },
                            { preserveState: true, replace: true },
                        );
                    }}
                >
                    <input
                        name="search"
                        defaultValue={filters.search}
                        placeholder="Worker, code, reason…"
                        className="h-10 w-56 rounded-md border border-input bg-background px-3 text-sm"
                    />
                    <Button type="submit" variant="secondary" size="sm">
                        Search
                    </Button>
                </form>

                <div className="overflow-x-auto rounded-lg border border-border">
                    <table className="w-full min-w-[640px] text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="px-3 py-2 font-medium">
                                    Equipment
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    Worker
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    Out since
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    Reason / zone
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    Expected back
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    Status
                                </th>
                                <th className="px-3 py-2 font-medium" />
                            </tr>
                        </thead>
                        <tbody>
                            {checkouts.data.map((row) => {
                                const isOpen = row.returned_at === null;
                                const isOverdue =
                                    row.is_overdue_return === true;

                                return (
                                    <tr
                                        key={row.id}
                                        className="border-t border-border"
                                    >
                                        <td className="px-3 py-2">
                                            {row.equipment ? (
                                                <Link
                                                    href={`/equipment/${row.equipment.id}`}
                                                    className="underline-offset-2 hover:underline"
                                                >
                                                    {
                                                        row.equipment
                                                            .equipment_code
                                                    }{' '}
                                                    · {row.equipment.name}
                                                </Link>
                                            ) : (
                                                `#${row.equipment_id}`
                                            )}
                                        </td>
                                        <td className="px-3 py-2">
                                            {row.worker?.name ??
                                                `Worker #${row.worker_id}`}
                                        </td>
                                        <td className="px-3 py-2">
                                            {new Date(
                                                row.checked_out_at,
                                            ).toLocaleString()}
                                        </td>
                                        <td className="px-3 py-2">
                                            {row.reason ?? '—'}
                                            {row.zone
                                                ? ` · ${row.zone.name}`
                                                : ''}
                                        </td>
                                        <td className="px-3 py-2">
                                            {row.expected_return_at
                                                ? new Date(
                                                      row.expected_return_at,
                                                  ).toLocaleString()
                                                : '—'}
                                        </td>
                                        <td className="px-3 py-2">
                                            <div className="flex flex-wrap gap-1">
                                                {isOpen ? (
                                                    <CustodyBadge
                                                        state={
                                                            isOverdue
                                                                ? 'overdue_return'
                                                                : 'checked_out'
                                                        }
                                                    />
                                                ) : (
                                                    <span className="text-xs text-muted-foreground">
                                                        Returned
                                                        {row.returned_at
                                                            ? ` ${new Date(row.returned_at).toLocaleString()}`
                                                            : ''}
                                                    </span>
                                                )}
                                                <OverdueBadge
                                                    isReturnOverdue={isOverdue}
                                                />
                                            </div>
                                        </td>
                                        <td className="px-3 py-2 text-right">
                                            {canManage && isOpen && (
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    variant="secondary"
                                                    onClick={() =>
                                                        setReturnTarget(row)
                                                    }
                                                >
                                                    Return
                                                </Button>
                                            )}
                                        </td>
                                    </tr>
                                );
                            })}
                            {checkouts.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={7}
                                        className="px-3 py-8 text-center text-muted-foreground"
                                    >
                                        {filters.open
                                            ? 'No open checkouts.'
                                            : 'No checkout history.'}
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <p className="text-sm text-muted-foreground">
                    Page {checkouts.meta.current_page} of{' '}
                    {checkouts.meta.last_page} · {checkouts.meta.total} total
                </p>
            </div>

            <ReturnDialog
                open={returnTarget !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setReturnTarget(null);
                    }
                }}
                checkout={returnTarget}
                equipmentLabel={
                    returnTarget?.equipment
                        ? `${returnTarget.equipment.equipment_code} · ${returnTarget.equipment.name}`
                        : undefined
                }
            />
        </>
    );
}
