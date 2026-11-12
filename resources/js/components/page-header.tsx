import type { ReactNode } from 'react';

export function PageHeader({ heading, subheading, actions }: { heading: string; subheading?: string | null; actions?: ReactNode }) {
    return (
        <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div className="space-y-1">
                <h1 className="text-2xl font-semibold tracking-tight">{heading}</h1>
                {subheading && <p className="text-muted-foreground">{subheading}</p>}
            </div>

            {actions && <div className="flex shrink-0 items-center gap-2">{actions}</div>}
        </div>
    );
}
