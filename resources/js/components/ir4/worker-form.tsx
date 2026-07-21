import { Form } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SearchableSelect } from '@/components/ui/searchable-select';

type WorkerTypeOption = { value: string; label: string };

type WorkerFormValues = {
    name?: string;
    contractor?: string;
    worker_type?: string;
    role_title?: string | null;
    badge_number?: string | null;
    employee_code?: string | null;
    phone?: string | null;
    notes?: string | null;
};

type Props = {
    action: string;
    method: 'post' | 'put';
    workerTypes: WorkerTypeOption[];
    defaults?: WorkerFormValues;
    submitLabel: string;
    className?: string;
    onSuccess?: () => void;
};

export function WorkerForm({
    action,
    method,
    workerTypes,
    defaults = {},
    submitLabel,
    className,
    onSuccess,
}: Props) {
    return (
        <Form
            action={action}
            method={method}
            encType="multipart/form-data"
            className={className ?? 'max-w-xl space-y-4'}
            options={{ preserveScroll: true }}
            onSuccess={onSuccess}
        >
            {({ processing, errors }) => (
                <>
                    <div className="grid gap-2">
                        <Label htmlFor="name">Name</Label>
                        <Input
                            id="name"
                            name="name"
                            required
                            maxLength={150}
                            defaultValue={defaults.name ?? ''}
                        />
                        {errors.name && (
                            <p className="text-sm text-destructive">
                                {errors.name}
                            </p>
                        )}
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="contractor">Contractor</Label>
                        <Input
                            id="contractor"
                            name="contractor"
                            required
                            maxLength={150}
                            defaultValue={defaults.contractor ?? ''}
                        />
                        {errors.contractor && (
                            <p className="text-sm text-destructive">
                                {errors.contractor}
                            </p>
                        )}
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="worker_type">Worker type</Label>
                        <SearchableSelect
                            id="worker_type"
                            name="worker_type"
                            required
                            defaultValue={defaults.worker_type ?? 'contractor'}
                            options={workerTypes}
                        />
                        {errors.worker_type && (
                            <p className="text-sm text-destructive">
                                {errors.worker_type}
                            </p>
                        )}
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="role_title">Role title</Label>
                        <Input
                            id="role_title"
                            name="role_title"
                            maxLength={150}
                            defaultValue={defaults.role_title ?? ''}
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="badge_number">Badge number</Label>
                        <Input
                            id="badge_number"
                            name="badge_number"
                            maxLength={100}
                            defaultValue={defaults.badge_number ?? ''}
                        />
                        {errors.badge_number && (
                            <p className="text-sm text-destructive">
                                {errors.badge_number}
                            </p>
                        )}
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="employee_code">Employee code</Label>
                        <Input
                            id="employee_code"
                            name="employee_code"
                            maxLength={100}
                            defaultValue={defaults.employee_code ?? ''}
                        />
                        {errors.employee_code && (
                            <p className="text-sm text-destructive">
                                {errors.employee_code}
                            </p>
                        )}
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="phone">Phone</Label>
                        <Input
                            id="phone"
                            name="phone"
                            maxLength={40}
                            defaultValue={defaults.phone ?? ''}
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="photo">Photo</Label>
                        <Input
                            id="photo"
                            name="photo"
                            type="file"
                            accept="image/jpeg,image/png"
                        />
                        {errors.photo && (
                            <p className="text-sm text-destructive">
                                {errors.photo}
                            </p>
                        )}
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="notes">Notes</Label>
                        <textarea
                            id="notes"
                            name="notes"
                            rows={3}
                            maxLength={5000}
                            defaultValue={defaults.notes ?? ''}
                            className="rounded-md border border-input bg-background px-3 py-2 text-sm"
                        />
                    </div>
                    <Button type="submit" disabled={processing}>
                        {submitLabel}
                    </Button>
                </>
            )}
        </Form>
    );
}
