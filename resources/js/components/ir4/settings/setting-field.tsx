import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { SettingSchema } from '@/types/settings';

type Props = {
    setting: SettingSchema;
    value: string | number | boolean | null;
    error?: string;
    onChange: (value: string | number | boolean) => void;
};

export function SettingField({ setting, value, error, onChange }: Props) {
    const disabled = !setting.editable;
    const id = `setting-${setting.key}`;

    return (
        <div className="grid gap-2">
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <Label htmlFor={id} className="text-sm font-medium">
                        {setting.label}
                        {setting.unit ? (
                            <span className="text-muted-foreground">
                                {' '}
                                ({setting.unit})
                            </span>
                        ) : null}
                    </Label>
                    {setting.description ? (
                        <p className="text-muted-foreground mt-0.5 text-xs">
                            {setting.description}
                        </p>
                    ) : null}
                    <p className="text-muted-foreground mt-1 font-mono text-[11px]">
                        {setting.key}
                    </p>
                </div>
                {setting.requires_confirm ? (
                    <span className="shrink-0 rounded border border-amber-500/40 px-1.5 py-0.5 text-[10px] tracking-wide text-amber-500 uppercase">
                        Confirm
                    </span>
                ) : null}
            </div>

            {setting.type === 'bool' ? (
                <label className="flex items-center gap-2 text-sm">
                    <input
                        id={id}
                        type="checkbox"
                        className="size-4 rounded border"
                        checked={Boolean(value)}
                        disabled={disabled}
                        onChange={(event) => onChange(event.target.checked)}
                    />
                    <span>{value ? 'Enabled' : 'Disabled'}</span>
                </label>
            ) : null}

            {(setting.type === 'int' ||
                setting.type === 'float' ||
                setting.type === 'string' ||
                setting.type === 'timezone' ||
                setting.type === 'time') && (
                <Input
                    id={id}
                    type={
                        setting.type === 'int' || setting.type === 'float'
                            ? 'number'
                            : setting.type === 'time'
                              ? 'time'
                              : 'text'
                    }
                    step={setting.type === 'float' ? '0.1' : undefined}
                    min={setting.min ?? undefined}
                    max={setting.max ?? undefined}
                    value={value === null || value === undefined ? '' : String(value)}
                    disabled={disabled}
                    onChange={(event) => {
                        if (setting.type === 'int') {
                            onChange(Number.parseInt(event.target.value, 10) || 0);

                            return;
                        }

                        if (setting.type === 'float') {
                            onChange(Number.parseFloat(event.target.value) || 0);

                            return;
                        }

                        onChange(event.target.value);
                    }}
                />
            )}

            {setting.type === 'enum' && setting.options ? (
                <Select
                    value={String(value ?? '')}
                    disabled={disabled}
                    onValueChange={(next) => onChange(next)}
                >
                    <SelectTrigger id={id}>
                        <SelectValue placeholder="Select…" />
                    </SelectTrigger>
                    <SelectContent>
                        {setting.options.map((option) => (
                            <SelectItem key={option} value={option}>
                                {option}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            ) : null}

            {setting.updated_at ? (
                <p className="text-muted-foreground text-xs">
                    Last changed{' '}
                    {new Date(setting.updated_at).toLocaleString()}
                    {setting.updated_by
                        ? ` by ${setting.updated_by.name}`
                        : ''}
                </p>
            ) : (
                <p className="text-muted-foreground text-xs">Using default</p>
            )}

            {error ? <p className="text-destructive text-xs">{error}</p> : null}
        </div>
    );
}
