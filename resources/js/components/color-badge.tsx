import type { ReactNode } from 'react';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

/**
 * The badge colours the PHP enums name (`BookingStatus::color()`,
 * `UserRole::color()`…), so the server keeps deciding which colour means what.
 */
const tones: Record<string, string> = {
    amber: 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200',
    emerald: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200',
    green: 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-200',
    rose: 'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-200',
    red: 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-200',
    orange: 'bg-orange-100 text-orange-800 dark:bg-orange-950 dark:text-orange-200',
    sky: 'bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-200',
    blue: 'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-200',
    indigo: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-950 dark:text-indigo-200',
    violet: 'bg-violet-100 text-violet-800 dark:bg-violet-950 dark:text-violet-200',
    zinc: 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-200',
};

export function ColorBadge({ color, className, children }: { color: string; className?: string; children: ReactNode }) {
    return (
        <Badge variant="secondary" className={cn('border-transparent font-semibold', tones[color] ?? tones.zinc, className)}>
            {children}
        </Badge>
    );
}
