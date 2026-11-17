import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';

/** The dashed box a list shows when there is nothing in it. */
export function EmptyState({ icon: Icon, title, children }: { icon: LucideIcon; title: string; children?: ReactNode }) {
    return (
        <div className="flex flex-col items-center gap-3 rounded-xl border border-dashed bg-card/50 px-6 py-20 text-center">
            <span className="flex size-12 items-center justify-center rounded-xl bg-muted text-muted-foreground">
                <Icon className="size-6" />
            </span>
            <h2 className="text-lg font-bold tracking-tight">{title}</h2>
            {children && <div className="text-muted-foreground">{children}</div>}
        </div>
    );
}
