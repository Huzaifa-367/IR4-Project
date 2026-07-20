import type { Method } from '@inertiajs/core';
import { Form, router } from '@inertiajs/react';
import type { ReactNode } from 'react';
import {
    AlertDialog,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    description: ReactNode;
    confirmLabel?: string;
    action?: string;
    method?: Method;
    data?: Record<string, string | number | boolean | null>;
    disabled?: boolean;
    destructive?: boolean;
    onConfirm?: () => void;
};

export function ConfirmActionDialog({
    open,
    onOpenChange,
    title,
    description,
    confirmLabel = 'Confirm',
    action,
    method = 'post',
    data,
    disabled = false,
    destructive = false,
    onConfirm,
}: Props) {
    return (
        <AlertDialog open={open} onOpenChange={onOpenChange}>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>{title}</AlertDialogTitle>
                    <AlertDialogDescription asChild>
                        <div className="text-muted-foreground text-sm">
                            {description}
                        </div>
                    </AlertDialogDescription>
                </AlertDialogHeader>
                {action ? (
                    <Form
                        action={action}
                        method={method}
                        options={{ preserveScroll: true }}
                        onSuccess={() => onOpenChange(false)}
                        className="contents"
                    >
                        {({ processing }) => (
                            <>
                                {data
                                    ? Object.entries(data).map(([key, value]) =>
                                          value === null || value === undefined ? null : (
                                              <input
                                                  key={key}
                                                  type="hidden"
                                                  name={key}
                                                  value={
                                                      typeof value === 'boolean'
                                                          ? value
                                                              ? '1'
                                                              : '0'
                                                          : String(value)
                                                  }
                                              />
                                          ),
                                      )
                                    : null}
                                <AlertDialogFooter>
                                    <AlertDialogCancel type="button">
                                        Cancel
                                    </AlertDialogCancel>
                                    <Button
                                        type="submit"
                                        variant={
                                            destructive
                                                ? 'destructive'
                                                : 'default'
                                        }
                                        disabled={disabled || processing}
                                    >
                                        {confirmLabel}
                                    </Button>
                                </AlertDialogFooter>
                            </>
                        )}
                    </Form>
                ) : (
                    <AlertDialogFooter>
                        <AlertDialogCancel type="button">Cancel</AlertDialogCancel>
                        <Button
                            type="button"
                            variant={destructive ? 'destructive' : 'default'}
                            disabled={disabled}
                            onClick={() => {
                                onConfirm?.();
                                onOpenChange(false);
                            }}
                        >
                            {confirmLabel}
                        </Button>
                    </AlertDialogFooter>
                )}
            </AlertDialogContent>
        </AlertDialog>
    );
}

export function visitConfirm(
    url: string,
    method: Method,
    data?: Record<string, string | number | boolean>,
): void {
    router.visit(url, {
        method,
        data,
        preserveScroll: true,
    });
}
