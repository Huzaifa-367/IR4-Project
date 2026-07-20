import { CheckIcon, ChevronDownIcon } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { cn } from '@/lib/utils';

export type SearchableSelectOption = {
    value: string;
    label: string;
    keywords?: string;
    disabled?: boolean;
};

type Props = {
    options: SearchableSelectOption[];
    value?: string;
    defaultValue?: string;
    onValueChange?: (value: string) => void;
    name?: string;
    id?: string;
    placeholder?: string;
    searchPlaceholder?: string;
    emptyText?: string;
    disabled?: boolean;
    required?: boolean;
    className?: string;
    triggerClassName?: string;
    allowClear?: boolean;
    clearLabel?: string;
};

export function SearchableSelect({
    options,
    value,
    defaultValue = '',
    onValueChange,
    name,
    id,
    placeholder = 'Select…',
    searchPlaceholder = 'Search…',
    emptyText = 'No matches.',
    disabled = false,
    required = false,
    className,
    triggerClassName,
    allowClear = false,
    clearLabel = 'Clear',
}: Props) {
    const [open, setOpen] = useState(false);
    const [uncontrolled, setUncontrolled] = useState(defaultValue);
    const selectedValue = value ?? uncontrolled;

    const selected = useMemo(
        () => options.find((option) => option.value === selectedValue) ?? null,
        [options, selectedValue],
    );

    function selectValue(next: string): void {
        if (value === undefined) {
            setUncontrolled(next);
        }

        onValueChange?.(next);
        setOpen(false);
    }

    const isSizedTrigger = Boolean(triggerClassName);

    return (
        <div
            className={cn(
                isSizedTrigger ? 'inline-flex max-w-full shrink-0' : 'w-full',
                className,
            )}
        >
            {name ? (
                <input
                    type="hidden"
                    name={name}
                    value={selectedValue}
                    required={required && selectedValue === ''}
                />
            ) : null}
            <Popover open={open} onOpenChange={setOpen}>
                <PopoverTrigger asChild>
                    <Button
                        id={id}
                        type="button"
                        variant="outline"
                        role="combobox"
                        aria-expanded={open}
                        disabled={disabled}
                        className={cn(
                            'border-input h-9 justify-between px-3 font-normal shadow-xs',
                            isSizedTrigger ? 'w-auto min-w-0' : 'w-full',
                            !selected && 'text-muted-foreground',
                            triggerClassName,
                        )}
                    >
                        <span className="truncate">
                            {selected?.label ?? placeholder}
                        </span>
                        <ChevronDownIcon className="size-4 opacity-50" />
                    </Button>
                </PopoverTrigger>
                <PopoverContent
                    className="w-[var(--radix-popover-trigger-width)] p-0"
                    align="start"
                >
                    <Command>
                        <CommandInput placeholder={searchPlaceholder} />
                        <CommandList>
                            <CommandEmpty>{emptyText}</CommandEmpty>
                            <CommandGroup>
                                {allowClear ? (
                                    <CommandItem
                                        value={`__clear__ ${clearLabel}`}
                                        onSelect={() => selectValue('')}
                                    >
                                        <CheckIcon
                                            className={cn(
                                                'size-4',
                                                selectedValue === ''
                                                    ? 'opacity-100'
                                                    : 'opacity-0',
                                            )}
                                        />
                                        {clearLabel}
                                    </CommandItem>
                                ) : null}
                                {options.map((option) => (
                                    <CommandItem
                                        key={option.value}
                                        value={`${option.label} ${option.keywords ?? ''} ${option.value}`}
                                        disabled={option.disabled}
                                        onSelect={() =>
                                            selectValue(option.value)
                                        }
                                    >
                                        <CheckIcon
                                            className={cn(
                                                'size-4',
                                                selectedValue === option.value
                                                    ? 'opacity-100'
                                                    : 'opacity-0',
                                            )}
                                        />
                                        {option.label}
                                    </CommandItem>
                                ))}
                            </CommandGroup>
                        </CommandList>
                    </Command>
                </PopoverContent>
            </Popover>
        </div>
    );
}
