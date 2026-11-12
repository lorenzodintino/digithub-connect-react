import { Head, router, useForm } from '@inertiajs/react';
import { ArrowRight, Download, IdCard } from 'lucide-react';
import type { FormEvent } from 'react';
import { ColorBadge } from '@/components/color-badge';
import { EmptyState } from '@/components/empty-state';
import { FormField } from '@/components/form-field';
import { PageHeader } from '@/components/page-header';
import { Pagination } from '@/components/pagination';
import { SearchInput } from '@/components/search-input';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { useFilters } from '@/hooks/use-filters';
import { __ } from '@/lib/i18n';
import { customers as customersRoute } from '@/routes/app';
import { exportMethod, notes as notesRoute } from '@/routes/app/customers';
import type { Paginated } from '@/types/pagination';

interface Customer {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    bookings_count: number;
    upcoming_count: number;
    no_show_count: number;
    last_booking: string | null;
}

interface Card {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    notes: string;
    history: {
        id: number;
        date: string;
        time: string;
        service: string;
        color: string;
        operator: string;
        status: string;
        status_color: string;
    }[];
}

interface Props {
    customers: Paginated<Customer>;
    filters: { q: string };
    card: Card | null;
}

function NoteForm({ card }: { card: Card }) {
    const form = useForm({ notes: card.notes });

    function save(event: FormEvent): void {
        event.preventDefault();

        form.patch(notesRoute.url(card.id), { preserveScroll: true });
    }

    return (
        <form onSubmit={save} className="space-y-3">
            <FormField
                id="notes"
                label={__('Internal note')}
                description={__('Only your portal sees this. The customer never does.')}
                error={form.errors.notes}
            >
                <Textarea id="notes" rows={3} value={form.data.notes} onChange={(event) => form.setData('notes', event.target.value)} />
            </FormField>

            <div className="flex justify-end">
                <Button size="sm" type="submit" disabled={form.processing}>
                    {__('Save note')}
                </Button>
            </div>
        </form>
    );
}

function CustomerCard({ card, onClose }: { card: Card | null; onClose: () => void }) {
    return (
        <Sheet open={card !== null} onOpenChange={(open) => !open && onClose()}>
            <SheetContent className="w-full overflow-y-auto sm:max-w-2xl">
                {card && (
                    <div className="space-y-6 p-6">
                        <SheetHeader className="p-0 pe-8">
                            <SheetTitle className="text-lg">{card.name}</SheetTitle>
                            <SheetDescription>
                                {card.email}
                                {card.phone && ` · ${card.phone}`}
                            </SheetDescription>
                        </SheetHeader>

                        <NoteForm key={card.id} card={card} />

                        <div className="flex items-center gap-3">
                            <Separator className="flex-1" />
                            <span className="text-sm text-muted-foreground">{__('History')}</span>
                            <Separator className="flex-1" />
                        </div>

                        <div className="flex flex-col divide-y">
                            {card.history.map((booking) => (
                                <div key={booking.id} className="flex items-center gap-3 py-3">
                                    <span className="w-28 shrink-0 text-sm tabular-nums">
                                        {booking.date}
                                        <span className="block text-xs text-muted-foreground">{booking.time}</span>
                                    </span>

                                    <span className="size-2.5 shrink-0 rounded-full" style={{ backgroundColor: booking.color }} />

                                    <span className="min-w-0 flex-1">
                                        <span className="block truncate font-medium">{booking.service}</span>
                                        <span className="block truncate text-sm text-muted-foreground">{booking.operator}</span>
                                    </span>

                                    <ColorBadge color={booking.status_color} className="shrink-0">
                                        {booking.status}
                                    </ColorBadge>
                                </div>
                            ))}
                        </div>
                    </div>
                )}
            </SheetContent>
        </Sheet>
    );
}

export default function Customers({ customers, filters: current, card }: Props) {
    const [filters, setFilter] = useFilters(customersRoute.url(), current, { q: '' });

    function openCard(customerId: number | null): void {
        const query = { ...(filters.q !== '' ? { q: filters.q } : {}), ...(customerId !== null ? { customer: String(customerId) } : {}) };

        router.get(customersRoute.url(), query, { only: ['card'], preserveState: true, preserveScroll: true, replace: true });
    }

    return (
        <>
            <Head title={__('Customers')} />

            <div className="flex w-full flex-col gap-6">
                <PageHeader heading={__('Customers')} subheading={__('Everybody who ever booked with your portal, and what they booked.')} />

                <div className="flex flex-wrap items-end justify-between gap-3">
                    <SearchInput
                        value={filters.q}
                        onChange={(value) => setFilter('q', value)}
                        placeholder={__('Search by name, email or phone')}
                        className="w-full max-w-sm"
                    />

                    {/* A file, not a page: a plain link lets the browser download it. */}
                    <Button size="sm" variant="secondary" asChild>
                        <a href={exportMethod.url(filters.q !== '' ? { query: { q: filters.q } } : undefined)}>
                            <Download />
                            {__('Export CSV')}
                        </a>
                    </Button>
                </div>

                {customers.data.length === 0 ? (
                    <EmptyState icon={IdCard} title={__('No customer yet')}>
                        {__('They appear here as soon as somebody books.')}
                    </EmptyState>
                ) : (
                    <>
                        <div className="rounded-xl border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="ps-4">{__('Customer')}</TableHead>
                                        <TableHead>{__('Phone')}</TableHead>
                                        <TableHead>{__('Bookings')}</TableHead>
                                        <TableHead>{__('No shows')}</TableHead>
                                        <TableHead>{__('Last booking')}</TableHead>
                                        <TableHead className="pe-4" />
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {customers.data.map((customer) => (
                                        <TableRow key={customer.id}>
                                            <TableCell className="ps-4">
                                                <div className="min-w-0">
                                                    <div className="truncate font-medium">{customer.name}</div>
                                                    <div className="truncate text-sm text-muted-foreground">{customer.email}</div>
                                                </div>
                                            </TableCell>
                                            <TableCell className="tabular-nums">{customer.phone ?? '—'}</TableCell>
                                            <TableCell className="tabular-nums">
                                                {customer.bookings_count}
                                                {customer.upcoming_count > 0 && (
                                                    <ColorBadge color="emerald" className="ms-1">
                                                        {__(':count upcoming', { count: customer.upcoming_count })}
                                                    </ColorBadge>
                                                )}
                                            </TableCell>
                                            <TableCell className="tabular-nums">
                                                {customer.no_show_count > 0 ? <ColorBadge color="orange">{customer.no_show_count}</ColorBadge> : '—'}
                                            </TableCell>
                                            <TableCell className="tabular-nums">{customer.last_booking ?? '—'}</TableCell>
                                            <TableCell className="pe-4 text-end">
                                                <Button size="sm" variant="ghost" onClick={() => openCard(customer.id)}>
                                                    {__('Open')}
                                                    <ArrowRight />
                                                </Button>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>

                        <Pagination paginator={customers} />
                    </>
                )}
            </div>

            <CustomerCard card={card} onClose={() => openCard(null)} />
        </>
    );
}
