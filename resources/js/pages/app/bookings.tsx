import { Head, Link, router, useForm } from '@inertiajs/react';
import { CalendarDays, Check, ChevronLeft, ChevronRight, Download, Ellipsis, ExternalLink, Eye, List, MessageSquareText, Plus, UserMinus, X } from 'lucide-react';
import { type FormEvent, type ReactNode, useState } from 'react';
import { ColorBadge } from '@/components/color-badge';
import { EmptyState } from '@/components/empty-state';
import { FormField, InputError } from '@/components/form-field';
import { PageHeader } from '@/components/page-header';
import { Pagination } from '@/components/pagination';
import { SearchInput } from '@/components/search-input';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useFilters } from '@/hooks/use-filters';
import { __ } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { bookings as bookingsRoute } from '@/routes/app';
import * as bookingRoutes from '@/routes/app/bookings';
import * as sessionRoutes from '@/routes/app/bookings/session';
import type { Paginated } from '@/types/pagination';

/** App\Support\Agenda::row() */
interface BookingRow {
    id: number;
    uid: string;
    date: string;
    time: string;
    hours: string;
    customer: string;
    email: string;
    service: string;
    color: string;
    operator: string;
    status: string;
    status_label: string;
    status_color: string;
    cancelled: boolean;
    notes: string | null;
    notes_preview: string | null;
    customer_page: string;
    video_confirmation: { meeting_url: string | null } | null;
    can: { confirm: boolean; cancel: boolean; anonymise: boolean };
    outcomes: { value: string; label: string }[];
}

interface Details extends BookingRow {
    phone: string | null;
    meeting_url: string | null;
    session: { seats: string; operators: { id: number; name: string }[]; candidates: { id: number; name: string }[]; can_manage: boolean } | null;
    phases: { service: string; hours: string; pause: string | null }[];
    allocations: { title: string; rows: { id: number; name: string; period: string }[] };
    answers: { id: number; label: string; value: string }[];
}

interface GridEntry {
    start: number;
    end: number;
    lane: number;
    lanes: number;
    booking: BookingRow;
    session: boolean;
    label: string;
}

interface CalendarDay {
    date: string;
    number: number;
    weekday: string;
    in_month: boolean;
    is_today: boolean;
    bookings: BookingRow[];
}

interface Calendar {
    heading: string;
    focus: string;
    previous: string;
    next: string;
    today: string;
    weekdays: string[];
    days: CalendarDay[];
    grid: { startHour: number; endHour: number; days: Record<string, GridEntry[]> } | null;
    now_minute: number;
    empty: boolean;
}

type Filters = { view: string; d: string; q: string; range: string; status: string; service: string; operator: string };

interface Option {
    id: string;
    name: string;
}

interface Props {
    filters: Filters;
    seesWholePortal: boolean;
    pendingCount: number;
    statuses: Option[];
    services: Option[];
    operators: Option[];
    table: Paginated<BookingRow> | null;
    calendar: Calendar | null;
    details: Details | null;
}

const defaults: Filters = { view: 'table', d: '', q: '', range: 'upcoming', status: 'all', service: 'all', operator: 'all' };

/** The query of the page as it stands, so a dialog can open on top of it. */
function currentQuery(filters: Filters): Record<string, string> {
    return Object.fromEntries(Object.entries(filters).filter(([key, value]) => value !== defaults[key as keyof Filters]));
}

interface Handlers {
    open: (booking: BookingRow) => void;
    confirm: (booking: BookingRow) => void;
    cancel: (booking: BookingRow) => void;
    anonymise: (booking: BookingRow) => void;
}

function BookingActions({ booking, handlers, showDetails = true }: { booking: BookingRow; handlers: Handlers; showDetails?: boolean }) {
    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button size="icon" variant="ghost" aria-label={__('Booking actions')}>
                    <Ellipsis />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                {showDetails && (
                    <DropdownMenuItem onSelect={() => handlers.open(booking)}>
                        <Eye />
                        {__('Details')}
                    </DropdownMenuItem>
                )}
                {booking.can.confirm && (
                    <DropdownMenuItem onSelect={() => handlers.confirm(booking)}>
                        <Check />
                        {__('Confirm')}
                    </DropdownMenuItem>
                )}
                {booking.outcomes.map((outcome) => (
                    <DropdownMenuItem key={outcome.value} onSelect={() => router.post(bookingRoutes.outcome.url(booking.uid), { outcome: outcome.value }, { preserveScroll: true })}>
                        {outcome.value === 'no_show' ? <UserMinus /> : <Check />}
                        {outcome.label}
                    </DropdownMenuItem>
                ))}
                {booking.outcomes.length > 0 && <DropdownMenuSeparator />}
                <DropdownMenuItem asChild>
                    <a href={booking.customer_page} target="_blank" rel="noopener">
                        <ExternalLink />
                        {__('Open customer page')}
                    </a>
                </DropdownMenuItem>
                {booking.can.cancel && (
                    <>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem variant="destructive" onSelect={() => handlers.cancel(booking)}>
                            <X />
                            {__('Cancel')}
                        </DropdownMenuItem>
                    </>
                )}
                {booking.can.anonymise && (
                    <DropdownMenuItem variant="destructive" onSelect={() => handlers.anonymise(booking)}>
                        <UserMinus />
                        {__('Erase customer data')}
                    </DropdownMenuItem>
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

function FilterSelect({ id, label, value, onChange, all, options, className }: { id: string; label: string; value: string; onChange: (value: string) => void; all: string | null; options: Option[]; className?: string }) {
    return (
        <FormField id={id} label={label} className={cn('min-w-0', className)}>
            <Select value={value} onValueChange={onChange}>
                <SelectTrigger id={id} className="w-full">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    {all !== null && <SelectItem value="all">{all}</SelectItem>}
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

function BookingTable({ table, seesWholePortal, handlers, onNotes }: { table: Paginated<BookingRow>; seesWholePortal: boolean; handlers: Handlers; onNotes: (booking: BookingRow) => void }) {
    if (table.data.length === 0) {
        return (
            <EmptyState icon={CalendarDays} title={__('No bookings here')}>
                {__('Nothing matches these filters yet.')}
            </EmptyState>
        );
    }

    return (
        <>
            <div className="overflow-hidden rounded-xl border bg-card">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead className="ps-4">{__('When')}</TableHead>
                            <TableHead>{__('Customer')}</TableHead>
                            <TableHead>{__('Service')}</TableHead>
                            {seesWholePortal && <TableHead>{__('Operator')}</TableHead>}
                            <TableHead>{__('Status')}</TableHead>
                            <TableHead className="w-36">{__('Notes')}</TableHead>
                            <TableHead className="pe-4" />
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {table.data.map((booking) => (
                            <TableRow key={booking.id}>
                                <TableCell className="ps-4">
                                    <div className="font-medium capitalize">{booking.date}</div>
                                    <div className="text-sm text-muted-foreground tabular-nums">{booking.hours}</div>
                                </TableCell>
                                <TableCell>
                                    <div className="min-w-0">
                                        <div className="truncate font-medium">{booking.customer}</div>
                                        <div className="truncate text-sm text-muted-foreground">{booking.email}</div>
                                    </div>
                                </TableCell>
                                <TableCell>
                                    <div className="flex items-center gap-2">
                                        <span className="size-2.5 shrink-0 rounded-full" style={{ backgroundColor: booking.color }} />
                                        <span className="truncate">{booking.service}</span>
                                    </div>
                                </TableCell>
                                {seesWholePortal && <TableCell>{booking.operator}</TableCell>}
                                <TableCell>
                                    <ColorBadge color={booking.status_color}>{booking.status_label}</ColorBadge>
                                </TableCell>
                                <TableCell className="w-36">
                                    {booking.notes_preview && (
                                        <Button size="sm" variant="ghost" className="max-w-full" onClick={() => onNotes(booking)}>
                                            <MessageSquareText />
                                            <span className="block truncate italic">{booking.notes_preview}</span>
                                        </Button>
                                    )}
                                </TableCell>
                                <TableCell className="pe-4 text-end">
                                    <BookingActions booking={booking} handlers={handlers} />
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </div>
            <Pagination paginator={table} />
        </>
    );
}

function MonthView({ calendar, onDay, handlers }: { calendar: Calendar; onDay: (date: string) => void; handlers: Handlers }) {
    return (
        <>
            <div className="grid grid-cols-7 pb-2 text-center text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                {calendar.weekdays.map((label) => (
                    <div key={label}>{label}</div>
                ))}
            </div>
            <div className="grid grid-cols-7 gap-px overflow-hidden rounded-lg border bg-border">
                {calendar.days.map((day) => (
                    <div key={day.date} className={cn('min-h-24 space-y-1 bg-card p-1.5', !day.in_month && 'bg-background', day.is_today && 'bg-primary/5')}>
                        <button
                            type="button"
                            title={__('Open this day')}
                            onClick={() => onDay(day.date)}
                            className={cn(
                                'flex size-6 items-center justify-center rounded-full text-xs font-medium transition hover:bg-accent',
                                day.is_today && 'bg-primary font-semibold text-primary-foreground hover:bg-primary/80',
                                !day.is_today && !day.in_month && 'text-muted-foreground/60',
                                !day.is_today && day.in_month && 'text-muted-foreground',
                            )}
                        >
                            {day.number}
                        </button>
                        {/* A phone cell is about 45 px wide: coloured dots say that something is booked, the day opens on a tap. */}
                        {day.bookings.length > 0 && (
                            <button
                                type="button"
                                title={__('Open this day')}
                                aria-label={__('Open this day')}
                                onClick={() => onDay(day.date)}
                                className="flex w-full flex-wrap gap-1 rounded px-0.5 py-0.5 md:hidden"
                            >
                                {day.bookings.slice(0, 6).map((booking) => (
                                    <span key={booking.id} className={cn('size-1.5 rounded-full', booking.cancelled && 'opacity-40')} style={{ backgroundColor: booking.color }} />
                                ))}
                            </button>
                        )}
                        {day.bookings.slice(0, 3).map((booking) => (
                            <button
                                key={booking.id}
                                type="button"
                                onClick={() => handlers.open(booking)}
                                className="hidden w-full items-center gap-1.5 rounded-md px-1 py-0.5 text-start text-xs transition hover:bg-accent md:flex"
                            >
                                <span className="size-1.5 shrink-0 rounded-full" style={{ backgroundColor: booking.color }} />
                                <span className="shrink-0 text-muted-foreground tabular-nums">{booking.time}</span>
                                <span className={cn('truncate', booking.cancelled && 'text-muted-foreground line-through')}>{booking.customer}</span>
                            </button>
                        ))}
                        {day.bookings.length > 3 && (
                            <button
                                type="button"
                                onClick={() => onDay(day.date)}
                                className="hidden w-full rounded px-1 py-0.5 text-start text-xs font-medium text-muted-foreground transition hover:bg-accent md:block"
                            >
                                {__(':count more', { count: day.bookings.length - 3 })}
                            </button>
                        )}
                    </div>
                ))}
            </div>
            {calendar.empty && <p className="pt-4 text-center text-sm text-muted-foreground">{__('Nothing booked this month with these filters.')}</p>}
        </>
    );
}

/**
 * The week on a time grid, like a wall calendar: one column per day, each
 * appointment as tall as it lasts, side by side where they overlap. One pixel
 * per minute. On a phone seven columns do not fit: a list per day instead.
 */
function WeekView({ calendar, onDay, handlers }: { calendar: Calendar; onDay: (date: string) => void; handlers: Handlers }) {
    const grid = calendar.grid!;
    const firstMinute = grid.startHour * 60;
    const height = (grid.endHour - grid.startHour) * 60;
    const hours = Array.from({ length: grid.endHour - grid.startHour }, (_, index) => grid.startHour + index);

    return (
        <>
            <div className="hidden md:block">
                <div className="grid" style={{ gridTemplateColumns: '3.25rem repeat(7, minmax(0, 1fr))' }}>
                    <div />
                    {calendar.days.map((day) => (
                        <button key={day.date} type="button" title={__('Open this day')} onClick={() => onDay(day.date)} className="flex items-baseline justify-center gap-1 rounded py-1.5 transition hover:bg-accent">
                            <span className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">{day.weekday}</span>
                            <span className={cn('flex size-6 items-center justify-center rounded-full text-sm', day.is_today ? 'bg-primary font-semibold text-primary-foreground' : 'text-muted-foreground')}>
                                {day.number}
                            </span>
                        </button>
                    ))}

                    <div className="relative" style={{ height }}>
                        {hours.map((hour) => (
                            <span key={hour} className="absolute end-2 -translate-y-1/2 text-xs text-muted-foreground tabular-nums" style={{ top: (hour - grid.startHour) * 60 }}>
                                {String(hour).padStart(2, '0')}:00
                            </span>
                        ))}
                    </div>

                    {calendar.days.map((day) => (
                        <div
                            key={day.date}
                            className={cn('relative border-s', day.is_today && 'bg-primary/5')}
                            style={{ height, backgroundImage: 'linear-gradient(to bottom, var(--border) 1px, transparent 1px)', backgroundSize: '100% 60px' }}
                        >
                            {(grid.days[day.date] ?? []).map((entry) => (
                                <button
                                    key={entry.booking.id}
                                    type="button"
                                    onClick={() => (entry.session ? onDay(day.date) : handlers.open(entry.booking))}
                                    title={`${entry.booking.hours} · ${entry.label} · ${entry.booking.service}`}
                                    className={cn(
                                        'absolute overflow-hidden rounded-md border-s-[3px] px-1.5 py-0.5 text-start text-xs leading-tight transition hover:z-10 hover:shadow-md',
                                        entry.booking.cancelled && 'opacity-50',
                                    )}
                                    style={{
                                        top: entry.start - firstMinute,
                                        height: Math.max(20, entry.end - entry.start),
                                        left: `calc(${entry.lane} * 100% / ${entry.lanes} + 1px)`,
                                        width: `calc(100% / ${entry.lanes} - 2px)`,
                                        borderColor: entry.booking.color,
                                        backgroundColor: `color-mix(in srgb, ${entry.booking.color} 22%, var(--card))`,
                                    }}
                                >
                                    {entry.end - entry.start < 30 ? (
                                        <span className={cn('block truncate font-medium', entry.booking.cancelled && 'line-through')}>
                                            <span className="font-normal text-muted-foreground tabular-nums">{entry.booking.time}</span> {entry.session ? entry.booking.service : entry.label}
                                        </span>
                                    ) : (
                                        <>
                                            <span className="block text-muted-foreground tabular-nums">{entry.booking.time}</span>
                                            <span className={cn('block truncate font-medium', entry.booking.cancelled && 'line-through')}>{entry.session ? entry.booking.service : entry.label}</span>
                                            {entry.session ? (
                                                <span className="block truncate text-muted-foreground">{entry.label}</span>
                                            ) : (
                                                entry.end - entry.start >= 45 && <span className="block truncate text-muted-foreground">{entry.booking.service}</span>
                                            )}
                                        </>
                                    )}
                                </button>
                            ))}

                            {day.is_today && calendar.now_minute >= firstMinute && calendar.now_minute <= firstMinute + height && (
                                <div className="pointer-events-none absolute inset-x-0 z-20 h-0.5 bg-primary" style={{ top: calendar.now_minute - firstMinute }} aria-hidden="true">
                                    <span className="absolute -start-1 -top-[3px] size-2 rounded-full bg-primary" />
                                </div>
                            )}
                        </div>
                    ))}
                </div>

                {calendar.empty && <p className="pt-4 text-center text-sm text-muted-foreground">{__('Nothing booked this week with these filters.')}</p>}
            </div>

            <div className="grid gap-px overflow-hidden rounded-lg border bg-border md:hidden">
                {calendar.days.map((day) => (
                    <div key={day.date} className={cn('space-y-1 bg-card p-2', day.is_today && 'bg-primary/5')}>
                        <button type="button" onClick={() => onDay(day.date)} className={cn('mb-1 flex w-full items-baseline gap-1 rounded px-1 py-0.5 text-start transition hover:bg-accent', day.is_today && 'font-semibold')}>
                            <span className="text-xs text-muted-foreground uppercase">{day.weekday}</span>
                            <span className="text-sm">{day.number}</span>
                        </button>
                        {day.bookings.length === 0 ? (
                            <p className="px-1 text-xs text-muted-foreground">—</p>
                        ) : (
                            day.bookings.map((booking) => (
                                <button
                                    key={booking.id}
                                    type="button"
                                    onClick={() => handlers.open(booking)}
                                    className="flex w-full items-center gap-2 rounded-md border-s-[3px] bg-muted/60 px-2 py-1 text-start text-xs transition hover:bg-accent max-md:min-h-11"
                                    style={{ borderColor: booking.color }}
                                >
                                    <span className="text-muted-foreground tabular-nums">{booking.time}</span>
                                    <span className={cn('truncate font-medium', booking.cancelled && 'text-muted-foreground line-through')}>{booking.customer}</span>
                                </button>
                            ))
                        )}
                    </div>
                ))}
            </div>
        </>
    );
}

function DayView({ calendar, seesWholePortal, handlers }: { calendar: Calendar; seesWholePortal: boolean; handlers: Handlers }) {
    const bookings = calendar.days[0]?.bookings ?? [];

    if (bookings.length === 0) {
        return (
            <EmptyState icon={CalendarDays} title={__('Nothing booked on this day')}>
                {__('Pick another day, or widen the filters.')}
            </EmptyState>
        );
    }

    return (
        <div className="flex flex-col divide-y">
            {bookings.map((booking) => (
                <div key={booking.id} className="flex items-center gap-3 py-3">
                    <button type="button" onClick={() => handlers.open(booking)} className="flex min-w-0 flex-1 flex-col gap-1 rounded-lg px-2 py-1 text-start transition hover:bg-accent sm:flex-row sm:items-center sm:gap-3">
                        <span className="shrink-0 text-sm font-semibold tabular-nums">{booking.hours}</span>
                        <span className="flex min-w-0 flex-1 items-center gap-3">
                            <span className="size-2.5 shrink-0 rounded-full" style={{ backgroundColor: booking.color }} />
                            <span className="min-w-0 flex-1">
                                <span className={cn('block truncate font-medium', booking.cancelled && 'text-muted-foreground line-through')}>{booking.customer}</span>
                                <span className="block truncate text-sm text-muted-foreground">
                                    {booking.service}
                                    {seesWholePortal && ` · ${booking.operator}`}
                                </span>
                            </span>
                            {booking.notes && <MessageSquareText className="size-4 shrink-0 text-muted-foreground" />}
                        </span>
                    </button>
                    <ColorBadge color={booking.status_color} className="shrink-0">
                        {booking.status_label}
                    </ColorBadge>
                    <BookingActions booking={booking} handlers={handlers} />
                </div>
            ))}
        </div>
    );
}

function Field({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div>
            <dt className="text-muted-foreground">{label}</dt>
            <dd className="font-medium">{children}</dd>
        </div>
    );
}

function DetailsDialog({ details, onClose, handlers }: { details: Details | null; onClose: () => void; handlers: Handlers }) {
    const [sessionError, setSessionError] = useState<string | undefined>();

    function removeTeacher(userId: number): void {
        if (details) {
            router.delete(sessionRoutes.destroy.url({ booking: details.uid, user: userId }), {
                preserveScroll: true,
                onError: (errors) => setSessionError(errors.session),
                onSuccess: () => setSessionError(undefined),
            });
        }
    }

    return (
        <Dialog open={details !== null} onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                {details && (
                    <div className="space-y-6">
                        <DialogHeader className="space-y-2 pe-8 text-start">
                            <DialogTitle>{details.customer}</DialogTitle>
                            <div className="flex flex-wrap items-center gap-2">
                                <ColorBadge color={details.status_color}>{details.status_label}</ColorBadge>
                                <DialogDescription className="capitalize">
                                    {details.date} · {details.hours}
                                </DialogDescription>
                            </div>
                        </DialogHeader>

                        <dl className="grid gap-3 text-sm sm:grid-cols-2">
                            <Field label={__('Service')}>
                                <span className="flex items-center gap-2">
                                    <span className="size-2.5 shrink-0 rounded-full" style={{ backgroundColor: details.color }} />
                                    {details.service}
                                </span>
                            </Field>
                            <Field label={__('Operator')}>{details.operator}</Field>
                            <Field label={__('Email')}>
                                <span className="block truncate">{details.email}</span>
                            </Field>
                            {details.phone && <Field label={__('Phone')}>{details.phone}</Field>}
                        </dl>

                        {details.meeting_url && (
                            <Button variant="outline" asChild>
                                <a href={details.meeting_url} target="_blank" rel="noreferrer">
                                    <ExternalLink />
                                    {__('Join the video call')}
                                </a>
                            </Button>
                        )}

                        {details.session && (
                            <div className="space-y-3 rounded-lg border p-3 text-sm">
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <div className="font-medium">{__('Group session')}</div>
                                    <ColorBadge color="zinc">{details.session.seats}</ColorBadge>
                                </div>
                                <InputError message={sessionError} />
                                <div className="flex flex-wrap items-center gap-2">
                                    {details.session.operators.map((teacher) => (
                                        <span key={teacher.id} className="inline-flex items-center gap-1 rounded-full bg-muted py-1 ps-3 pe-1">
                                            {teacher.name}
                                            {details.session!.can_manage && (
                                                <Button
                                                    size="icon"
                                                    variant="ghost"
                                                    className="size-6"
                                                    title={__('Remove')}
                                                    aria-label={__('Remove :name from this session', { name: teacher.name })}
                                                    onClick={() => removeTeacher(teacher.id)}
                                                >
                                                    <X />
                                                </Button>
                                            )}
                                        </span>
                                    ))}
                                </div>
                                {details.session.can_manage && details.session.candidates.length > 0 && (
                                    <div className="flex flex-wrap items-center gap-2">
                                        <span className="text-sm text-muted-foreground">{__('Add somebody free:')}</span>
                                        {details.session.candidates.map((candidate) => (
                                            <Button
                                                key={candidate.id}
                                                size="sm"
                                                variant="secondary"
                                                onClick={() => router.post(sessionRoutes.store.url(details.uid), { user: candidate.id }, { preserveScroll: true })}
                                            >
                                                <Plus />
                                                {candidate.name}
                                            </Button>
                                        ))}
                                    </div>
                                )}
                            </div>
                        )}

                        {details.phases.length > 0 && (
                            <div className="space-y-3 rounded-xl border p-4">
                                <h3 className="font-medium">{__('Package programme')}</h3>
                                {details.phases.map((phase, index) => (
                                    <div key={index}>
                                        <div className="flex justify-between gap-4 text-sm">
                                            <span>{phase.service}</span>
                                            <span className="tabular-nums">{phase.hours}</span>
                                        </div>
                                        {phase.pause && <p className="text-sm text-muted-foreground">{phase.pause}</p>}
                                    </div>
                                ))}
                            </div>
                        )}

                        {details.allocations.rows.length > 0 && (
                            <div className="text-sm">
                                <h3 className="font-medium">{details.allocations.title}</h3>
                                {details.allocations.rows.map((allocation) => (
                                    <div key={allocation.id} className="mt-1">
                                        <p>{allocation.name}</p>
                                        <p className="text-muted-foreground tabular-nums">{allocation.period}</p>
                                    </div>
                                ))}
                            </div>
                        )}

                        {details.answers.length > 0 && (
                            <dl className="space-y-2 rounded-lg bg-muted p-3 text-sm">
                                {details.answers.map((answer) => (
                                    <div key={answer.id}>
                                        <dt className="text-xs font-medium text-muted-foreground">{answer.label}</dt>
                                        <dd className="whitespace-pre-line">{answer.value}</dd>
                                    </div>
                                ))}
                            </dl>
                        )}

                        {details.notes && (
                            <div className="rounded-lg bg-muted p-3">
                                <div className="text-xs font-medium text-muted-foreground">{__('Note from the customer')}</div>
                                <p className="mt-1 text-sm whitespace-pre-line">{details.notes}</p>
                            </div>
                        )}

                        <DialogFooter className="flex-row flex-wrap items-center justify-end">
                            <BookingActions booking={details} handlers={handlers} showDetails={false} />
                            <Button variant="outline" onClick={onClose}>
                                {__('Close')}
                            </Button>
                            {/* The one thing a request waiting for approval asks for, not left behind the menu. */}
                            {details.can.confirm && (
                                <Button onClick={() => handlers.confirm(details)}>
                                    <Check />
                                    {__('Confirm')}
                                </Button>
                            )}
                        </DialogFooter>
                    </div>
                )}
            </DialogContent>
        </Dialog>
    );
}

function VideoConfirmationDialog({ booking, onClose }: { booking: BookingRow; onClose: () => void }) {
    const sharedLink = booking.video_confirmation?.meeting_url;
    const form = useForm({ meeting_url: '' });

    function submit(event: FormEvent): void {
        event.preventDefault();
        form.post(bookingRoutes.confirm.url(booking.uid), { preserveScroll: true, onSuccess: onClose });
    }

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent>
                <form onSubmit={submit} className="space-y-6">
                    <DialogHeader>
                        <DialogTitle>{__('Confirm booking')}</DialogTitle>
                        <DialogDescription>
                            {booking.customer} · {booking.date}, {booking.time}
                        </DialogDescription>
                    </DialogHeader>
                    <FormField
                        id="booking-meeting-url"
                        label={__('Video call link')}
                        description={sharedLink ? __('This group session already has a video call link.') : __('Create the video call for this time, then paste its link here.')}
                        error={form.errors.meeting_url}
                    >
                        <Input
                            id="booking-meeting-url"
                            type="url"
                            value={sharedLink ?? form.data.meeting_url}
                            maxLength={2048}
                            readOnly={sharedLink != null}
                            required
                            onChange={(event) => form.setData('meeting_url', event.target.value)}
                        />
                    </FormField>
                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={onClose}>{__('Cancel')}</Button>
                        <Button type="submit" disabled={form.processing}>{__('Confirm')}</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function CancelDialog({ booking, onClose }: { booking: BookingRow | null; onClose: () => void }) {
    const form = useForm({ reason: '' });

    function submit(event: FormEvent): void {
        event.preventDefault();

        if (booking) {
            form.post(bookingRoutes.cancel.url(booking.uid), {
                preserveScroll: true,
                onSuccess: () => {
                    form.reset();
                    onClose();
                },
            });
        }
    }

    return (
        <Dialog open={booking !== null} onOpenChange={(open) => !open && (form.clearErrors(), onClose())}>
            <DialogContent className="sm:max-w-md">
                <form onSubmit={submit} className="space-y-6">
                    <DialogHeader>
                        <DialogTitle>{__('Cancel this booking?')}</DialogTitle>
                        <DialogDescription>{__('The slot is released and the reason is kept on the record.')}</DialogDescription>
                    </DialogHeader>
                    <FormField id="cancel-reason" label={__('Reason')} error={form.errors.reason}>
                        <Input id="cancel-reason" required placeholder={__('Shown on the booking record')} value={form.data.reason} onChange={(event) => form.setData('reason', event.target.value)} />
                    </FormField>
                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={onClose}>
                            {__('Keep it')}
                        </Button>
                        <Button type="submit" variant="destructive" disabled={form.processing}>
                            {__('Cancel booking')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function AnonymiseDialog({ booking, onClose }: { booking: BookingRow | null; onClose: () => void }) {
    const [error, setError] = useState<string | undefined>();

    function close(): void {
        setError(undefined);
        onClose();
    }

    return (
        <Dialog open={booking !== null} onOpenChange={(open) => !open && close()}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{__('Erase this customer?')}</DialogTitle>
                    <DialogDescription>
                        {__('Name, email, phone and notes are removed from every booking of this customer in your portal. The appointments stay, so your history and counts do not change. This cannot be undone.')}
                    </DialogDescription>
                </DialogHeader>
                {booking && (
                    <div className="rounded-lg bg-muted p-3 text-sm">
                        <div className="font-medium">{booking.customer}</div>
                        <div className="text-muted-foreground">{booking.email}</div>
                    </div>
                )}
                <InputError message={error} />
                <DialogFooter>
                    <Button variant="outline" onClick={close}>
                        {__('Keep it')}
                    </Button>
                    <Button
                        variant="destructive"
                        onClick={() =>
                            booking &&
                            router.post(bookingRoutes.anonymise.url(booking.uid), {}, { preserveScroll: true, onSuccess: close, onError: (errors) => setError(errors.anonymise) })
                        }
                    >
                        {__('Erase customer data')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function NotesDialog({ booking, onClose }: { booking: BookingRow | null; onClose: () => void }) {
    return (
        <Dialog open={booking !== null} onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{__('Note from the customer')}</DialogTitle>
                    {booking && (
                        <DialogDescription>
                            {booking.customer} · {booking.date}, {booking.time}
                        </DialogDescription>
                    )}
                </DialogHeader>
                {booking && <p className="text-sm whitespace-pre-line">{booking.notes}</p>}
                <DialogFooter>
                    <Button variant="outline" onClick={onClose}>
                        {__('Close')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

export default function Bookings(props: Props) {
    const { seesWholePortal, pendingCount, table, calendar, details } = props;
    const [filters, setFilter, apply] = useFilters<Filters>(bookingsRoute.url(), props.filters, defaults);
    const [confirming, setConfirming] = useState<BookingRow | null>(null);
    const [cancelling, setCancelling] = useState<BookingRow | null>(null);
    const [anonymising, setAnonymising] = useState<BookingRow | null>(null);
    const [notes, setNotes] = useState<BookingRow | null>(null);

    function openDetails(uid: string | null): void {
        router.get(bookingsRoute.url(), { ...currentQuery(filters), ...(uid ? { booking: uid } : {}) }, { only: ['details'], preserveState: true, preserveScroll: true, replace: true });
    }

    const handlers: Handlers = {
        open: (booking) => openDetails(booking.uid),
        confirm: (booking) => {
            if (booking.video_confirmation) {
                setConfirming(booking);
            } else {
                router.post(bookingRoutes.confirm.url(booking.uid), {}, { preserveScroll: true });
            }
        },
        cancel: (booking) => {
            openDetails(null);
            setCancelling(booking);
        },
        anonymise: (booking) => {
            openDetails(null);
            setAnonymising(booking);
        },
    };

    const view = filters.view;
    /** What the server last sent: `filters.view` changes on the click, the calendar props only when the reply arrives. */
    const shownView = props.filters.view;
    const exportQuery = currentQuery(filters);

    return (
        <>
            <Head title={__('Bookings')} />

            <div className="flex w-full flex-col gap-6">
                <PageHeader
                    heading={__('Bookings')}
                    subheading={seesWholePortal ? __('Every booking taken by your portal.') : __('The bookings assigned to you.')}
                    actions={
                        <>
                            {pendingCount > 0 && <ColorBadge color="amber">{__(':count waiting for approval', { count: pendingCount })}</ColorBadge>}
                            <Button asChild>
                                <Link href={bookingRoutes.create()}>
                                    <Plus />
                                    {__('New booking')}
                                </Link>
                            </Button>
                        </>
                    }
                />

                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div className="flex w-fit items-center gap-1 rounded-xl border bg-card p-1" role="group" aria-label={__('View')}>
                        <Button size="sm" variant={view === 'table' ? 'secondary' : 'ghost'} aria-pressed={view === 'table'} onClick={() => apply({ view: 'table' })}>
                            <List />
                            {__('Table')}
                        </Button>
                        <span className="mx-1 h-5 w-px bg-border" />
                        {(['month', 'week', 'day'] as const).map((mode) => (
                            <Button key={mode} size="sm" variant={view === mode ? 'secondary' : 'ghost'} aria-pressed={view === mode} onClick={() => apply({ view: mode })}>
                                {{ month: __('Month'), week: __('Week'), day: __('Day') }[mode]}
                            </Button>
                        ))}
                    </div>

                    {/* A file, not a page. */}
                    <Button size="sm" variant="secondary" asChild>
                        <a href={bookingRoutes.exportMethod.url({ query: exportQuery })}>
                            <Download />
                            {__('Export CSV')}
                        </a>
                    </Button>
                </div>

                <div className="grid grid-cols-2 gap-3 lg:flex lg:flex-row lg:flex-wrap lg:items-end">
                    <SearchInput
                        id="booking-search"
                        label={__('Customer')}
                        value={filters.q}
                        onChange={(value) => setFilter('q', value)}
                        placeholder={__('Search by customer')}
                        className="col-span-2 lg:col-span-1 lg:w-72"
                    />
                    {view === 'table' && (
                        <FilterSelect
                            id="booking-range"
                            label={__('Period')}
                            value={filters.range}
                            onChange={(value) => setFilter('range', value)}
                            all={null}
                            options={[
                                { id: 'upcoming', name: __('Upcoming') },
                                { id: 'today', name: __('Today') },
                                { id: 'past', name: __('Past') },
                                { id: 'all', name: __('All dates') },
                            ]}
                            className="lg:w-40"
                        />
                    )}
                    <FilterSelect id="booking-status" label={__('Status')} value={filters.status} onChange={(value) => setFilter('status', value)} all={__('All statuses')} options={props.statuses} className="lg:w-44" />
                    <FilterSelect id="booking-service" label={__('Service')} value={filters.service} onChange={(value) => setFilter('service', value)} all={__('All services')} options={props.services} className="lg:w-52" />
                    {seesWholePortal && (
                        <FilterSelect id="booking-operator" label={__('Operator')} value={filters.operator} onChange={(value) => setFilter('operator', value)} all={__('Everyone')} options={props.operators} className="lg:w-52" />
                    )}
                </div>

                {table && <BookingTable table={table} seesWholePortal={seesWholePortal} handlers={handlers} onNotes={setNotes} />}

                {calendar && (
                    <div className="rounded-xl border bg-card p-4">
                        <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <h2 className="font-semibold capitalize">{calendar.heading}</h2>
                            <div className="flex items-center gap-2">
                                <Label htmlFor="booking-day" className="sr-only">
                                    {__('Go to date')}
                                </Label>
                                <Input id="booking-day" type="date" className="h-8 w-40" value={calendar.focus} onChange={(event) => event.target.value && apply({ d: event.target.value })} />
                                <Button size="icon" variant="ghost" className="size-8" title={__('Previous')} aria-label={__('Previous')} onClick={() => apply({ d: calendar.previous })}>
                                    <ChevronLeft />
                                </Button>
                                <Button size="sm" variant="secondary" onClick={() => apply({ d: calendar.today })}>
                                    {__('Today')}
                                </Button>
                                <Button size="icon" variant="ghost" className="size-8" title={__('Next')} aria-label={__('Next')} onClick={() => apply({ d: calendar.next })}>
                                    <ChevronRight />
                                </Button>
                            </div>
                        </div>

                        {shownView === 'month' && <MonthView calendar={calendar} onDay={(date) => apply({ view: 'day', d: date })} handlers={handlers} />}
                        {shownView === 'week' && <WeekView calendar={calendar} onDay={(date) => apply({ view: 'day', d: date })} handlers={handlers} />}
                        {shownView === 'day' && <DayView calendar={calendar} seesWholePortal={seesWholePortal} handlers={handlers} />}
                    </div>
                )}
            </div>

            <DetailsDialog details={details} onClose={() => openDetails(null)} handlers={handlers} />
            {confirming && <VideoConfirmationDialog key={confirming.uid} booking={confirming} onClose={() => setConfirming(null)} />}
            <CancelDialog booking={cancelling} onClose={() => setCancelling(null)} />
            <AnonymiseDialog booking={anonymising} onClose={() => setAnonymising(null)} />
            <NotesDialog booking={notes} onClose={() => setNotes(null)} />
        </>
    );
}
