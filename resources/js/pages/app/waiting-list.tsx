import { Head, router } from '@inertiajs/react';
import { BellRing, Trash2 } from 'lucide-react';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { Pagination } from '@/components/pagination';
import { SearchInput } from '@/components/search-input';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useFilters } from '@/hooks/use-filters';
import { __ } from '@/lib/i18n';
import { waitingList } from '@/routes/app';
import { destroy } from '@/routes/app/waiting-list';
import type { Paginated } from '@/types/pagination';

interface Entry {
    uid: string;
    name: string;
    email: string;
    phone: string | null;
    service: string;
    period: string;
    notified: string | null;
}

interface Props {
    entries: Paginated<Entry>;
    filters: { q: string };
}

export default function WaitingList({ entries, filters: current }: Props) {
    const [filters, setFilter] = useFilters(waitingList.url(), current, { q: '' });

    return (
        <>
            <Head title={__('Waiting list')} />

            <div className="flex w-full flex-col gap-6">
                <PageHeader heading={__('Waiting list')} subheading={__('People who asked to be told when a place opens up.')} />

                <SearchInput
                    value={filters.q}
                    onChange={(value) => setFilter('q', value)}
                    placeholder={__('Search by name, email or phone')}
                    className="max-w-sm"
                />

                {entries.data.length === 0 ? (
                    <EmptyState icon={BellRing} title={__('Nobody is waiting')}>
                        {__('Customers land here when your calendar has nothing that suits them.')}
                    </EmptyState>
                ) : (
                    <>
                        <div className="rounded-xl border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="ps-4">{__('Customer')}</TableHead>
                                        <TableHead>{__('Service')}</TableHead>
                                        <TableHead>{__('Wanted period')}</TableHead>
                                        <TableHead>{__('Last notified')}</TableHead>
                                        <TableHead className="pe-4" />
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {entries.data.map((entry) => (
                                        <TableRow key={entry.uid}>
                                            <TableCell className="ps-4">
                                                <div className="min-w-0">
                                                    <div className="truncate font-medium">{entry.name}</div>
                                                    <div className="truncate text-sm text-muted-foreground">
                                                        {entry.email}
                                                        {entry.phone && ` · ${entry.phone}`}
                                                    </div>
                                                </div>
                                            </TableCell>
                                            <TableCell>{entry.service}</TableCell>
                                            <TableCell className="tabular-nums">{entry.period}</TableCell>
                                            <TableCell className="tabular-nums">{entry.notified ?? '—'}</TableCell>
                                            <TableCell className="pe-4 text-end">
                                                <Button
                                                    size="icon"
                                                    variant="ghost"
                                                    aria-label={__('Remove :name from the waiting list', { name: entry.name })}
                                                    onClick={() => router.delete(destroy.url(entry.uid), { preserveScroll: true })}
                                                >
                                                    <Trash2 />
                                                </Button>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>

                        <Pagination paginator={entries} />
                    </>
                )}
            </div>
        </>
    );
}
