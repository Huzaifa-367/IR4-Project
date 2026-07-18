import { Form } from '@inertiajs/react';
import type { FormDataConvertible, Method } from '@inertiajs/core';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    description?: string;
    action: string;
    method?: Method;
    submitLabel?: string;
    disableSubmit?: boolean;
    transform?: (
        data: Record<string, FormDataConvertible>,
    ) => Record<string, FormDataConvertible>;
    children: (bag: {
        processing: boolean;
        errors: Partial<Record<string, string>>;
    }) => ReactNode;
};

export function CrudFormDialog({
    open,
    onOpenChange,
    title,
    description,
    action,
    method = 'post',
    submitLabel = 'Save',
    disableSubmit = false,
    transform,
    children,
}: Props) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    {description ? (
                        <DialogDescription>{description}</DialogDescription>
                    ) : null}
                </DialogHeader>
                <Form
                    action={action}
                    method={method}
                    className="flex flex-col gap-4"
                    options={{ preserveScroll: true }}
                    transform={transform}
                    onSuccess={() => onOpenChange(false)}
                >
                    {({ processing, errors }) => (
                        <>
                            {children({ processing, errors })}
                            <DialogFooter>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => onOpenChange(false)}
                                >
                                    Cancel
                                </Button>
                                {!disableSubmit ? (
                                    <Button type="submit" disabled={processing}>
                                        {submitLabel}
                                    </Button>
                                ) : null}
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
