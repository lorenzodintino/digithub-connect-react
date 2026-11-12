import { usePage } from '@inertiajs/react';
import { ChartColumn, Clock, FileText, type LucideIcon, OctagonAlert, TriangleAlert } from 'lucide-react';
import type { LayoutNotice } from '@/types';
import { cn } from '@/lib/utils';

/** App\Support\LayoutNotices names its icons after Heroicons: mapped to Lucide here. */
const icons: Record<string, LucideIcon> = {
    'exclamation-triangle': TriangleAlert,
    'exclamation-circle': OctagonAlert,
    'document-text': FileText,
    'chart-bar': ChartColumn,
    clock: Clock,
};

const tones: Record<LayoutNotice['tone'], string> = {
    amber: 'border-amber-300 bg-amber-50 text-amber-900 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-100',
    red: 'border-red-300 bg-red-50 text-red-900 dark:border-red-800 dark:bg-red-950 dark:text-red-100',
    sky: 'border-sky-200 bg-sky-50 text-sky-900 dark:border-sky-800 dark:bg-sky-950 dark:text-sky-100',
};

export function LayoutNotices() {
    const { notices } = usePage().props;

    return notices.map((notice) => {
        const Icon = icons[notice.icon] ?? TriangleAlert;

        return (
            <div key={notice.key} className={cn('flex flex-wrap items-center justify-between gap-3 border-b px-4 py-2 text-sm', tones[notice.tone])}>
                <span className="flex items-center gap-2">
                    <Icon className="size-4 shrink-0" />
                    {notice.message}
                    {notice.detail && <span className="opacity-80">{notice.detail}</span>}
                </span>

                {/* A plain link: some lead to an anchor of a page, which a full load scrolls to. */}
                {notice.link && (
                    <a href={notice.link.href} className="font-semibold underline">
                        {notice.link.label}
                    </a>
                )}
            </div>
        );
    });
}
