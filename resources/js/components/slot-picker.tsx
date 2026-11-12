import { router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Link2, MapPin, Phone, Video } from 'lucide-react';
import { FormField } from '@/components/form-field';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { __, trans_choice } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { Slot, SlotDay, SlotMonth } from '@/types/portal';

/** The icon of App\Enums\LocationType. */
export function LocationIcon({ type, className }: { type: string; className?: string }) {
    const Icon = { in_person: MapPin, phone: Phone, video: Video }[type] ?? Link2;

    return <Icon className={className} />;
}

function MonthGrid({ calendar, selectedDate, onMonth, onDay }: { calendar: SlotMonth; selectedDate: string; onMonth: (month: string) => void; onDay: (date: string) => void }) {
    return (
        <div className="space-y-4">
            <div className="flex items-center justify-between gap-2">
                <h2 className="font-semibold capitalize">{calendar.heading}</h2>

                <div className="flex items-center gap-1">
                    <Button
                        size="icon"
                        variant="ghost"
                        className="size-8"
                        disabled={calendar.previous === null}
                        title={__('Previous month')}
                        aria-label={__('Previous month')}
                        onClick={() => calendar.previous && onMonth(calendar.previous)}
                    >
                        <ChevronLeft />
                    </Button>
                    <Button size="icon" variant="ghost" className="size-8" title={__('Next month')} aria-label={__('Next month')} onClick={() => onMonth(calendar.next)}>
                        <ChevronRight />
                    </Button>
                </div>
            </div>

            <div className="grid grid-cols-7 gap-1 text-center text-xs font-medium text-muted-foreground">
                {calendar.weekdays.map((label) => (
                    <div key={label}>{label}</div>
                ))}
            </div>

            <div className="grid grid-cols-7 gap-1">
                {calendar.days.map((day) => {
                    const selected = day.date === selectedDate;

                    return (
                        <div key={day.date} className="aspect-square">
                            {day.available ? (
                                <button
                                    type="button"
                                    onClick={() => onDay(day.date)}
                                    aria-label={day.seats_left !== null ? `${day.label} — ${trans_choice('last seat left|last :count seats', day.seats_left)}` : day.label}
                                    aria-pressed={selected}
                                    className={cn(
                                        'flex size-full flex-col items-center justify-center rounded-lg text-sm font-medium transition',
                                        selected ? 'bg-primary text-primary-foreground' : 'bg-muted hover:bg-accent',
                                        !day.in_month && 'opacity-60',
                                    )}
                                >
                                    {day.number}
                                    <span
                                        className={cn(
                                            'mt-0.5 size-1 rounded-full',
                                            selected ? 'bg-current' : day.seats_left !== null ? 'bg-amber-500 dark:bg-amber-400' : 'bg-muted-foreground/60',
                                        )}
                                    />
                                </button>
                            ) : (
                                <div
                                    className={cn(
                                        'flex size-full items-center justify-center rounded-lg text-sm',
                                        day.in_month ? 'text-muted-foreground/70' : 'text-muted-foreground/40',
                                        day.is_today && 'ring-1 ring-border ring-inset',
                                    )}
                                >
                                    {day.number}
                                </div>
                            )}
                        </div>
                    );
                })}
            </div>

            <div className="space-y-1.5">
                {calendar.empty && <p className="text-sm text-muted-foreground">{__('No availability this month. Try the next one.')}</p>}

                {/* Why some days cannot be picked: the window, and the days the portal is shut. */}
                {calendar.notes.map((note) => (
                    <p key={note} className="text-xs text-muted-foreground">
                        {note}
                    </p>
                ))}

                {calendar.nearly_full && (
                    <p className="flex items-center gap-2 text-xs text-muted-foreground">
                        <span className="size-1.5 shrink-0 rounded-full bg-amber-500 dark:bg-amber-400" />
                        {__('A day marked in amber is nearly full.')}
                    </p>
                )}
            </div>
        </div>
    );
}

function DaySlots({
    day,
    timezone,
    timezones,
    portalTimezone,
    error,
    onTimezone,
    onSlot,
}: {
    day: SlotDay | null;
    timezone: string;
    timezones: string[];
    portalTimezone: string;
    error?: string;
    onTimezone: (timezone: string) => void;
    onSlot: (slot: Slot) => void;
}) {
    return (
        <div className="space-y-3">
            <div className="space-y-2">
                <h2 className="font-semibold capitalize">{day ? day.heading : __('Pick a day')}</h2>

                {/* Named, because an unlabelled "Europe/Rome" above a column of
                    times reads as a setting nobody asked for. */}
                <FormField id="visitor-timezone" label={__('Timezone')}>
                    <Select value={timezone} onValueChange={onTimezone}>
                        <SelectTrigger id="visitor-timezone" size="sm" className="w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {timezones.map((option) => (
                                <SelectItem key={option} value={option}>
                                    {option}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </FormField>

                {timezone !== portalTimezone && <p className="text-xs text-muted-foreground">{__('The portal works on :zone time.', { zone: portalTimezone })}</p>}
            </div>

            {error && <p className="rounded-lg bg-destructive/10 p-2 text-sm text-destructive">{error}</p>}

            {day === null ? (
                <p className="text-sm text-muted-foreground">{__('Days with a dot have free slots.')}</p>
            ) : day.slots.length === 0 ? (
                <p className="text-sm text-muted-foreground">{__('Nothing free on this day any more.')}</p>
            ) : (
                <div className="grid max-h-96 gap-2 overflow-y-auto pe-1">
                    {day.slots.map((slot) => (
                        <button
                            key={slot.value}
                            type="button"
                            onClick={() => onSlot(slot)}
                            className="w-full rounded-lg border bg-background px-3 py-2 text-sm font-medium tabular-nums transition hover:border-primary hover:bg-accent"
                        >
                            {slot.time}
                            {slot.other_day && <span className="ms-1 text-xs font-normal text-muted-foreground">{slot.other_day}</span>}
                            {slot.seats && (
                                <span className={cn('ms-1 text-xs', slot.seats.low ? 'font-medium text-amber-700 dark:text-amber-400' : 'font-normal text-muted-foreground')}>
                                    · {slot.seats.label}
                                </span>
                            )}
                        </button>
                    ))}
                </div>
            )}
        </div>
    );
}

/**
 * The month with the days that can be picked, and the free times of the
 * chosen day on the visitor's clock. Shared by the booking page of a service
 * and the reschedule of a booking.
 */
export function SlotPicker({
    calendar,
    day,
    selectedDate,
    timezone,
    timezones,
    portalTimezone,
    error,
    onMonth,
    onDay,
    onTimezone,
    onSlot,
}: {
    calendar: SlotMonth;
    day: SlotDay | null;
    selectedDate: string;
    timezone: string;
    timezones: string[];
    portalTimezone: string;
    error?: string;
    onMonth: (month: string) => void;
    onDay: (date: string) => void;
    onTimezone: (timezone: string) => void;
    onSlot: (slot: Slot) => void;
}) {
    return (
        <div className="grid gap-6 sm:grid-cols-[minmax(0,1fr)_200px]">
            <MonthGrid calendar={calendar} selectedDate={selectedDate} onMonth={onMonth} onDay={onDay} />
            <DaySlots day={day} timezone={timezone} timezones={timezones} portalTimezone={portalTimezone} error={error} onTimezone={onTimezone} onSlot={onSlot} />
        </div>
    );
}

/**
 * Visit the current page with a new query, keeping `?embed=1` and leaving
 * empty values out of the URL.
 */
export function visitPage(query: Record<string, string>, embed: boolean): void {
    const clean = Object.fromEntries(Object.entries({ ...query, ...(embed ? { embed: '1' } : {}) }).filter(([, value]) => value !== ''));

    router.get(window.location.pathname, clean, { preserveState: true, preserveScroll: true, replace: true });
}
