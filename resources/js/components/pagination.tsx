import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { __ } from '@/lib/i18n';
import type { Paginated } from '@/types/pagination';

/**
 * Page links under a list. The first and last of Laravel's links are the
 * previous and next arrows; the labels of the others are page numbers or "…".
 */
export function Pagination({ paginator }: { paginator: Paginated<unknown> }) {
    if (paginator.last_page <= 1) {
        return null;
    }

    const pages = paginator.links.slice(1, -1);

    return (
        <nav aria-label={__('Pagination Navigation')} className="flex flex-wrap items-center justify-between gap-3">
            <p className="text-sm text-muted-foreground tabular-nums">
                {paginator.from}–{paginator.to} / {paginator.total}
            </p>

            <div className="flex items-center gap-1">
                <ArrowLink href={paginator.prev_page_url} label={__('Previous')}>
                    <ChevronLeft />
                </ArrowLink>

                {pages.map((page, index) =>
                    page.url === null ? (
                        <span key={`gap-${index}`} className="px-2 text-muted-foreground">
                            …
                        </span>
                    ) : (
                        <Button key={page.url} size="sm" variant={page.active ? 'default' : 'ghost'} className="tabular-nums" asChild>
                            <Link href={page.url} preserveState aria-current={page.active ? 'page' : undefined}>
                                {page.label}
                            </Link>
                        </Button>
                    ),
                )}

                <ArrowLink href={paginator.next_page_url} label={__('Next')}>
                    <ChevronRight />
                </ArrowLink>
            </div>
        </nav>
    );
}

function ArrowLink({ href, label, children }: { href: string | null; label: string; children: ReactNode }) {
    if (href === null) {
        return (
            <Button size="icon" variant="ghost" disabled aria-label={label}>
                {children}
            </Button>
        );
    }

    return (
        <Button size="icon" variant="ghost" asChild>
            <Link href={href} preserveState aria-label={label}>
                {children}
            </Link>
        </Button>
    );
}
