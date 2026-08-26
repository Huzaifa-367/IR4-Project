import { Form } from '@inertiajs/react';
import { useState } from 'react';
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
    nationality?: string | null;
    date_of_birth?: string | null;
    joined_on?: string | null;
    government_id_number?: string | null;
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
    const [workerType, setWorkerType] = useState(
        defaults.worker_type ?? 'contractor',
    );

    return (
        <Form
            action={action}
            method={method}
            encType="multipart/form-data"
            className={className ?? 'max-w-xl space-y-4'}
            options={{ preserveScroll: true }}
            transform={(data) => ({
                ...data,
                worker_type: workerType,
            })}
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
                        {errors.name ? (
                            <p className="text-sm text-destructive">
                                {errors.name}
                            </p>
                        ) : null}
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
                        {errors.contractor ? (
                            <p className="text-sm text-destructive">
                                {errors.contractor}
                            </p>
                        ) : null}
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="worker_type">Worker type</Label>
                        <SearchableSelect
                            id="worker_type"
                            required
                            value={workerType}
                            onValueChange={setWorkerType}
                            options={workerTypes}
                        />
                        {errors.worker_type ? (
                            <p className="text-sm text-destructive">
                                {errors.worker_type}
                            </p>
                        ) : null}
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="role_title">Job title</Label>
                        <Input
                            id="role_title"
                            name="role_title"
                            maxLength={150}
                            defaultValue={defaults.role_title ?? ''}
                        />
                        {errors.role_title ? (
                            <p className="text-sm text-destructive">
                                {errors.role_title}
                            </p>
                        ) : null}
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="nationality">Nationality</Label>
                        <Input
                            id="nationality"
                            name="nationality"
                            maxLength={100}
                            defaultValue={defaults.nationality ?? ''}
                        />
                        {errors.nationality ? (
                            <p className="text-sm text-destructive">
                                {errors.nationality}
                            </p>
                        ) : null}
                    </div>
                    <div className="grid gap-2 sm:grid-cols-2 sm:gap-3">
                        <div className="grid gap-2">
                            <Label htmlFor="date_of_birth">Birthdate</Label>
                            <Input
                                id="date_of_birth"
                                name="date_of_birth"
                                type="date"
                                defaultValue={defaults.date_of_birth ?? ''}
                            />
                            {errors.date_of_birth ? (
                                <p className="text-sm text-destructive">
                                    {errors.date_of_birth}
                                </p>
                            ) : null}
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="joined_on">Joining date</Label>
                            <Input
                                id="joined_on"
                                name="joined_on"
                                type="date"
                                defaultValue={defaults.joined_on ?? ''}
                            />
                            {errors.joined_on ? (
                                <p className="text-sm text-destructive">
                                    {errors.joined_on}
                                </p>
                            ) : null}
                        </div>
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="government_id_number">
                            Government ID number
                        </Label>
                        <Input
                            id="government_id_number"
                            name="government_id_number"
                            maxLength={100}
                            defaultValue={defaults.government_id_number ?? ''}
                        />
                        {errors.government_id_number ? (
                            <p className="text-sm text-destructive">
                                {errors.government_id_number}
                            </p>
                        ) : null}
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="badge_number">Badge number</Label>
                        <Input
                            id="badge_number"
                            name="badge_number"
                            maxLength={100}
                            defaultValue={defaults.badge_number ?? ''}
                        />
                        {errors.badge_number ? (
                            <p className="text-sm text-destructive">
                                {errors.badge_number}
                            </p>
                        ) : null}
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="employee_code">Employee code</Label>
                        <Input
                            id="employee_code"
                            name="employee_code"
                            maxLength={100}
                            defaultValue={defaults.employee_code ?? ''}
                        />
                        {errors.employee_code ? (
                            <p className="text-sm text-destructive">
                                {errors.employee_code}
                            </p>
                        ) : null}
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="phone">Phone</Label>
                        <Input
                            id="phone"
                            name="phone"
                            maxLength={40}
                            defaultValue={defaults.phone ?? ''}
                        />
                        {errors.phone ? (
                            <p className="text-sm text-destructive">
                                {errors.phone}
                            </p>
                        ) : null}
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="photo">Photo</Label>
                        <Input
                            id="photo"
                            name="photo"
                            type="file"
                            accept="image/jpeg,image/png"
                        />
                        {errors.photo ? (
                            <p className="text-sm text-destructive">
                                {errors.photo}
                            </p>
                        ) : null}
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
                        {errors.notes ? (
                            <p className="text-sm text-destructive">
                                {errors.notes}
                            </p>
                        ) : null}
                    </div>
                    <p className="text-xs text-muted-foreground">
                        Certifications, skills, and vaccination status are
                        managed on the Documents tab — not as fixed profile
                        fields.
                    </p>
                    <Button type="submit" disabled={processing}>
                        {submitLabel}
                    </Button>
                </>
            )}
        </Form>
    );
}
