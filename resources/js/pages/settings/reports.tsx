import { Form, Head, Link } from '@inertiajs/react';
import { SettingsPageShell } from '@/components/ir4/settings/settings-page-shell';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SearchableSelect } from '@/components/ui/searchable-select';
import type { ReportSettings } from '@/types/report';

type Props = {
    settings: ReportSettings;
};

const days = [
    'sunday',
    'monday',
    'tuesday',
    'wednesday',
    'thursday',
    'friday',
    'saturday',
];

export default function ReportSettingsPage({ settings }: Props) {
    return (
        <>
            <Head title="Report settings" />
            <SettingsPageShell
                eyebrow="Settings"
                title="Report settings"
                description="Weekly report schedule and completeness threshold."
                actions={
                    <Button variant="outline" asChild>
                        <Link href="/reports">Weekly reports</Link>
                    </Button>
                }
            >
                <Form
                    method="put"
                    action="/settings/reports"
                    className="mx-auto grid max-w-xl gap-4 rounded-[var(--radius)] border border-border bg-surface p-4 shadow-[var(--shadow-card)] md:p-5"
                >
                    <div className="grid gap-2">
                        <Label htmlFor="generation_day">Generation day</Label>
                        <SearchableSelect
                            id="generation_day"
                            name="generation_day"
                            defaultValue={settings.generation_day}
                            options={days.map((day) => ({
                                value: day,
                                label: day,
                            }))}
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="generation_time">Generation time</Label>
                        <Input
                            id="generation_time"
                            name="generation_time"
                            type="time"
                            defaultValue={settings.generation_time}
                            required
                        />
                    </div>
                    <label className="flex items-center gap-2 rounded-md border border-border bg-surface-2/30 px-3 py-2 text-sm">
                        <input
                            id="auto_publish"
                            name="auto_publish"
                            type="checkbox"
                            value="1"
                            defaultChecked={settings.auto_publish}
                            className="size-4 rounded border"
                        />
                        Auto-publish
                    </label>
                    <div className="grid gap-2">
                        <Label htmlFor="completeness_threshold_pct">
                            Completeness threshold (%)
                        </Label>
                        <Input
                            id="completeness_threshold_pct"
                            name="completeness_threshold_pct"
                            type="number"
                            min={0}
                            max={100}
                            step={0.1}
                            defaultValue={settings.completeness_threshold_pct}
                            required
                        />
                    </div>
                    <div className="flex justify-end">
                        <Button type="submit">Save settings</Button>
                    </div>
                </Form>
            </SettingsPageShell>
        </>
    );
}

ReportSettingsPage.layout = {
    breadcrumbs: [
        { title: 'Settings', href: '/settings/general' },
        { title: 'Report settings', href: '/settings/reports' },
    ],
};
