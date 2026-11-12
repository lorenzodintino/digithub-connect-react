import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';

/** The dashed box a list shows when there is nothing in it. */
export function EmptyState({ icon: Icon, title, children }: { icon: LucideIcon; title: string; children?: ReactNode }) {
    return (
        <div className="flex flex-col items-center gap-3 rounded-xl border border-dashed px-6 py-20 text-center">
            <Icon className="size-10 text-muted-foreground" />
            <h2 className="text-lg font-semibold">{title}</h2>
            {children && <div className="text-muted-foreground">{children}</div>}
        </div>
    );
}
