import { Head, router } from '@inertiajs/react';
import { FormField } from '@/components/form-field';
import { PageHeader } from '@/components/page-header';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { __ } from '@/lib/i18n';
import { reports } from '@/routes/app';

interface Row {
    label: string;
    bookings: number;
    completed: number;
    no_shows: number;
    cancelled: number;
    value: string;
}

interface Props {
    period: string;
    seesWholePortal: boolean;
    window: { from: string; to: string; label: string };
    totals: {
        bookings: number;
        confirmed: number;
        pending: number;
        cancelled: number;
        completed: number;
        no_shows: number;
        no_show_rate: string;
        value: number;
        value_label: string;
    };
    byService: Row[];
    byOperator: Row[];
}

function Breakdown({ title, first, rows }: { title: string; first: string; rows: Row[] }) {
    return (
        <section className="rounded-xl border">
            <div className="border-b px-5 py-4">
                <h2 className="text-lg font-semibold">{title}</h2>
            </div>

            {rows.length === 0 ? (
                <p className="px-5 py-10 text-center text-muted-foreground">{__('Nothing booked in this period.')}</p>
            ) : (
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead className="ps-5">{first}</TableHead>
                            <TableHead>{__('Bookings')}</TableHead>
                            <TableHead>{__('Completed')}</TableHead>
                            <TableHead>{__('No shows')}</TableHead>
                            <TableHead>{__('Cancelled')}</TableHead>
                            <TableHead className="pe-5">{__('Value completed')}</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {rows.map((row) => (
                            <TableRow key={row.label}>
                                <TableCell className="ps-5 font-medium">{row.label}</TableCell>
                                <TableCell className="tabular-nums">{row.bookings}</TableCell>
                                <TableCell className="tabular-nums">{row.completed}</TableCell>
                                <TableCell className="tabular-nums">{row.no_shows}</TableCell>
                                <TableCell className="tabular-nums">{row.cancelled}</TableCell>
                                <TableCell className="pe-5 tabular-nums">{row.value}</TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            )}
        </section>
    );
}

export default function Reports({ period, seesWholePortal, window, totals, byService, byOperator }: Props) {
    const cards = [
        { label: __('Bookings'), value: totals.bookings, hint: __(':count cancelled', { count: totals.cancelled }) },
        { label: __('Completed'), value: totals.completed, hint: __(':count still to come', { count: totals.confirmed + totals.pending }) },
        { label: __('No shows'), value: totals.no_shows, hint: __('rate :rate', { rate: totals.no_show_rate }) },
        { label: __('Value completed'), value: totals.value_label, hint: __('services with a price') },
    ];

    const picker = (
        <FormField id="report-period" label={__('Period')} className="w-52">
            <Select
                value={period}
                onValueChange={(value) => router.get(reports.url(), value === 'this_month' ? {} : { period: value }, { preserveScroll: true, replace: true })}
            >
                <SelectTrigger id="report-period" className="w-full">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="this_month">{__('This month')}</SelectItem>
                    <SelectItem value="last_month">{__('Last month')}</SelectItem>
                    <SelectItem value="last_90_days">{__('Last 90 days')}</SelectItem>
                    <SelectItem value="this_year">{__('This year')}</SelectItem>
                </SelectContent>
            </Select>
        </FormField>
    );

    return (
        <>
            <Head title={__('Reports')} />

            <div className="flex w-full flex-col gap-6">
                <PageHeader heading={__('Reports')} subheading={seesWholePortal ? __('How the portal is doing.') : __('How your own work is going.')} actions={picker} />

                <p className="text-muted-foreground capitalize">{window.label}</p>

                <div className="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
                    {cards.map((card) => (
                        <div key={card.label} className="rounded-xl border p-5">
                            <p className="text-sm text-muted-foreground">{card.label}</p>
                            <div className="mt-1 text-2xl font-semibold tabular-nums">{card.value}</div>
                            <p className="mt-1 text-sm text-muted-foreground">{card.hint}</p>
                        </div>
                    ))}
                </div>

                <Breakdown title={__('By service')} first={__('Service')} rows={byService} />
                <Breakdown title={__('By operator')} first={__('Operator')} rows={byOperator} />

                <p className="text-sm text-muted-foreground">
                    {__('Completed appointments are the ones marked as such, by hand or by the nightly close. The value only counts services carrying a price.')}
                </p>
            </div>
        </>
    );
}
