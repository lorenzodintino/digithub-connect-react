import type { ReactNode } from 'react';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

/** The error of one field, as Laravel's validation wrote it. */
export function InputError({ message, className }: { message?: string; className?: string }) {
    return message ? <p className={cn('text-sm text-destructive', className)}>{message}</p> : null;
}

/**
 * Label, control, description and error stacked in one field: the
 * description sits between the label and the control, the error below it.
 */
export function FormField({
    id,
    label,
    description,
    error,
    className,
    children,
}: {
    id: string;
    label: string;
    description?: string;
    error?: string;
    className?: string;
    children: ReactNode;
}) {
    return (
        <div className={cn('grid gap-2', className)}>
            <Label htmlFor={id}>{label}</Label>
            {description && <p className="text-sm text-muted-foreground">{description}</p>}
            {children}
            <InputError message={error} />
        </div>
    );
}
