import { Form, Head, Link } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
            <div className="mx-auto flex max-w-xl flex-col gap-6 p-6">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title="Report settings"
                        description="Schedule and completeness threshold"
                    />
                    <Button variant="outline" asChild>
                        <Link href="/reports">Back</Link>
                    </Button>
                </div>

                <Form
                    method="put"
                    action="/reports/settings"
                    className="grid gap-4 rounded-lg border p-4"
                >
                    <div>
                        <Label htmlFor="generation_day">Generation day</Label>
                        <select
                            id="generation_day"
                            name="generation_day"
                            defaultValue={settings.generation_day}
                            className="w-full rounded-md border px-3 py-2 text-sm"
                        >
                            {days.map((day) => (
                                <option key={day} value={day}>
                                    {day}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div>
                        <Label htmlFor="generation_time">Generation time</Label>
                        <Input
                            id="generation_time"
                            name="generation_time"
                            type="time"
                            defaultValue={settings.generation_time}
                            required
                        />
                    </div>
                    <div className="flex items-center gap-2">
                        <input
                            id="auto_publish"
                            name="auto_publish"
                            type="checkbox"
                            value="1"
                            defaultChecked={settings.auto_publish}
                        />
                        <Label htmlFor="auto_publish">Auto-publish</Label>
                    </div>
                    <div>
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
                    <Button type="submit">Save settings</Button>
                </Form>
            </div>
        </>
    );
}

ReportSettingsPage.layout = {
    breadcrumbs: [
        { title: 'Reports', href: '/reports' },
        { title: 'Settings', href: '/reports/settings' },
    ],
};
