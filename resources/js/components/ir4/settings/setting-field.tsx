import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
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
        <div className="flex flex-col gap-2">
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <Label htmlFor={id} className="text-sm font-medium">
                        {setting.label}
                        {setting.unit ? (
                            <span className="text-text-dim">
                                {' '}
                                ({setting.unit})
                            </span>
                        ) : null}
                    </Label>
                    {setting.description ? (
                        <p className="mt-0.5 text-xs text-text-dim">
                            {setting.description}
                        </p>
                    ) : null}
                    <p className="mt-1 font-mono text-[11px] text-text-faint">
                        {setting.key}
                    </p>
                </div>
                {setting.requires_confirm ? (
                    <span className="shrink-0 rounded border border-[color:var(--warn)]/40 px-1.5 py-0.5 text-[10px] tracking-wide text-[color:var(--warn)] uppercase">
                        Confirm
                    </span>
                ) : null}
            </div>

            {setting.type === 'bool' ? (
                <div className="flex items-center gap-3">
                    <Switch
                        id={id}
                        checked={Boolean(value)}
                        disabled={disabled}
                        onCheckedChange={(checked) => onChange(checked)}
                    />
                    <span className="text-sm text-text-dim">
                        {value ? 'Enabled' : 'Disabled'}
                    </span>
                </div>
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
                    value={
                        value === null || value === undefined
                            ? ''
                            : String(value)
                    }
                    disabled={disabled}
                    onChange={(event) => {
                        if (setting.type === 'int') {
                            const next = event.target.value;
                            onChange(
                                next === ''
                                    ? 0
                                    : Number.parseInt(next, 10) || 0,
                            );
                            return;
                        }
                        if (setting.type === 'float') {
                            const next = event.target.value;
                            onChange(
                                next === ''
                                    ? 0
                                    : Number.parseFloat(next) || 0,
                            );
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
                        <SelectGroup>
                            {setting.options.map((option) => (
                                <SelectItem key={option} value={option}>
                                    {option}
                                </SelectItem>
                            ))}
                        </SelectGroup>
                    </SelectContent>
                </Select>
            ) : null}

            {setting.updated_at ? (
                <p className="text-xs text-text-faint">
                    Last changed{' '}
                    {new Date(setting.updated_at).toLocaleString()}
                    {setting.updated_by
                        ? ` by ${setting.updated_by.name}`
                        : ''}
                </p>
            ) : (
                <p className="text-xs text-text-faint">Using default</p>
            )}

            {error ? (
                <p className="text-destructive text-xs">{error}</p>
            ) : null}
        </div>
    );
}
