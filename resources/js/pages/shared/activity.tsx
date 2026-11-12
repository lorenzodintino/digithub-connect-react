import { Head, usePage } from '@inertiajs/react';
import { ClipboardList } from 'lucide-react';
import { EmptyState } from '@/components/empty-state';
import { FormField } from '@/components/form-field';
import { PageHeader } from '@/components/page-header';
import { Pagination } from '@/components/pagination';
import { SearchInput } from '@/components/search-input';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useFilters } from '@/hooks/use-filters';
import { __ } from '@/lib/i18n';
import type { Paginated } from '@/types/pagination';

interface Entry {
    id: number;
    date: string | null;
    time: string | null;
    actor: string;
    role: string | null;
    action: string;
    subject: string | null;
    portal: string | null;
    context: string[];
    ip: string | null;
}

interface Option {
    id: string;
    name: string;
}

interface Filters extends Record<string, string> {
    q: string;
    group: string;
    portal: string;
    actor: string;
    from: string;
    to: string;
}

interface Props {
    entries: Paginated<Entry>;
    filters: Filters;
    seesEverything: boolean;
    groups: string[];
    actors: Option[];
    portals: Option[];
}

const defaults: Filters = { q: '', group: 'all', portal: 'all', actor: 'all', from: '', to: '' };

function FilterSelect({ id, label, value, onChange, allLabel, options }: { id: string; label: string; value: string; onChange: (value: string) => void; allLabel: string; options: Option[] }) {
    return (
        <FormField id={id} label={label} className="lg:w-52">
            <Select value={value} onValueChange={onChange}>
                <SelectTrigger id={id} className="w-full">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">{allLabel}</SelectItem>
                    {options.map((option) => (
                        <SelectItem key={option.id} value={option.id}>
                            {option.name}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </FormField>
    );
}

/** Shared by the administrator (every portal) and the manager (their own). */
export default function Activity({ entries, filters: current, seesEverything, groups, actors, portals }: Props) {
    const path = usePage().url.split('?')[0];
    const [filters, setFilter] = useFilters(path, current, defaults);

    return (
        <>
            <Head title={__('Register')} />

            <div className="flex w-full flex-col gap-6">
                <PageHeader
                    heading={__('Register')}
                    subheading={seesEverything ? __('Every action taken on the platform.') : __('Every action taken inside your portal.')}
                />

                <div className="flex flex-col gap-3 lg:flex-row lg:flex-wrap lg:items-end">
                    <SearchInput
                        value={filters.q}
                        onChange={(value) => setFilter('q', value)}
                        placeholder={__('Search by person or action')}
                        className="lg:w-72"
                    />

                    <FilterSelect
                        id="group"
                        label={__('Kind')}
                        value={filters.group}
                        onChange={(value) => setFilter('group', value)}
                        allLabel={__('All kinds')}
                        options={groups.map((group) => ({ id: group, name: group.charAt(0).toUpperCase() + group.slice(1) }))}
                    />

                    <FilterSelect
                        id="actor"
                        label={__('Person')}
                        value={filters.actor}
                        onChange={(value) => setFilter('actor', value)}
                        allLabel={__('Everyone')}
                        options={actors}
                    />

                    {seesEverything && (
                        <FilterSelect
                            id="portal"
                            label={__('Portal')}
                            value={filters.portal}
                            onChange={(value) => setFilter('portal', value)}
                            allLabel={__('All portals')}
                            options={portals}
                        />
                    )}

                    <div className="flex items-end gap-2">
                        <FormField id="from" label={__('From date')}>
                            <Input id="from" type="date" value={filters.from} onChange={(event) => setFilter('from', event.target.value)} className="w-40" />
                        </FormField>
                        <span className="pb-2 text-muted-foreground">–</span>
                        <FormField id="to" label={__('To date')}>
                            <Input id="to" type="date" value={filters.to} onChange={(event) => setFilter('to', event.target.value)} className="w-40" />
                        </FormField>
                    </div>
                </div>

                {entries.data.length === 0 ? (
                    <EmptyState icon={ClipboardList} title={__('Nothing in the register')}>
                        {__('Nothing matches these filters yet.')}
                    </EmptyState>
                ) : (
                    <>
                        <div className="rounded-xl border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="ps-4">{__('When')}</TableHead>
                                        <TableHead>{__('Who')}</TableHead>
                                        <TableHead>{__('What')}</TableHead>
                                        {seesEverything && <TableHead>{__('Portal')}</TableHead>}
                                        <TableHead className="pe-4">{__('Details')}</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {entries.data.map((entry) => (
                                        <TableRow key={entry.id}>
                                            <TableCell className="ps-4 tabular-nums">
                                                {entry.date}
                                                <div className="text-sm text-muted-foreground">{entry.time}</div>
                                            </TableCell>
                                            <TableCell>
                                                <div className="truncate font-medium">{entry.actor}</div>
                                                {entry.role && <div className="text-sm text-muted-foreground">{entry.role}</div>}
                                            </TableCell>
                                            <TableCell>
                                                <div className="truncate">{entry.action}</div>
                                                {entry.subject && <div className="truncate text-sm text-muted-foreground">{entry.subject}</div>}
                                            </TableCell>
                                            {seesEverything && <TableCell>{entry.portal ?? '—'}</TableCell>}
                                            <TableCell className="pe-4 text-sm whitespace-normal text-muted-foreground">
                                                {entry.context.map((line) => (
                                                    <span key={line} className="me-2">
                                                        {line}
                                                    </span>
                                                ))}
                                                {entry.ip && <span className="opacity-70">{entry.ip}</span>}
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
