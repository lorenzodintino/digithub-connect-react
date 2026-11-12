import type { LucideIcon } from 'lucide-react';
import { cn } from '@/lib/utils';

export function StatCard({
    label,
    value,
    icon: Icon,
    hint,
    className,
    valueClassName,
}: {
    label: string;
    value: number | string;
    icon?: LucideIcon;
    hint?: string | null;
    className?: string;
    valueClassName?: string;
}) {
    return (
        <div className={cn('rounded-xl border bg-card p-5', className)}>
            <div className="flex items-start justify-between gap-3">
                <span className="text-sm font-medium text-muted-foreground">{label}</span>

                {Icon && (
                    <span className="flex size-8 items-center justify-center rounded-lg bg-muted text-muted-foreground">
                        <Icon className="size-4" />
                    </span>
                )}
            </div>

            <div className={cn('mt-3 text-3xl font-semibold tabular-nums', valueClassName)}>{value}</div>

            {hint && <p className="mt-1 text-sm text-muted-foreground">{hint}</p>}
        </div>
    );
}
