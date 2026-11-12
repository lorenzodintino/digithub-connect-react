import { Head, Link, router, useForm } from '@inertiajs/react';
import { Check, Copy, Plus, Trash2, TriangleAlert } from 'lucide-react';
import { type FormEvent, type ReactNode, useEffect, useState } from 'react';
import { toast } from 'sonner';
import { ColorBadge } from '@/components/color-badge';
import { FormField, InputError } from '@/components/form-field';
import { PageHeader } from '@/components/page-header';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { __, trans_choice } from '@/lib/i18n';
import { availability, bookings } from '@/routes/app';
import { schedule as scheduleRoute } from '@/routes/app/availability';
import * as blockRoutes from '@/routes/app/availability/blocks';
import * as closureRoutes from '@/routes/app/availability/closures';
import * as exceptionRoutes from '@/routes/app/availability/exceptions';

// A type, not an interface: Inertia only sends plain records.
type Range = {
    starts_at: string;
    ends_at: string;
};

interface Day {
    weekday: number;
    label: string;
    enabled: boolean;
    ranges: Range[];
}

interface Props {
    member: { id: number; name: string; is_bookable: boolean };
    members: { id: number; name: string; is_manager: boolean }[];
    isManager: boolean;
    schedule: Day[];
    uncovered: { count: number; items: { id: number; day: string; line: string }[] };
    exceptions: { id: number; date: string; closed: boolean; hours: string | null; reason: string | null }[];
    timeBlocks: { id: number; date: string; hours: string; reason: string | null }[];
    closures: { id: number; label: string; days: string; reason: string | null }[];
}

function Panel({ title, description, onAdd, empty, children }: { title: string; description: string; onAdd: () => void; empty: string | null; children: ReactNode }) {
    return (
        <section className="rounded-xl border">
            <div className="flex items-center justify-between gap-4 border-b px-5 py-4">
                <div>
                    <h2 className="text-lg font-semibold">{title}</h2>
                    <p className="text-sm text-muted-foreground">{description}</p>
                </div>
                <Button size="sm" variant="secondary" onClick={onAdd}>
                    <Plus />
                    {__('Add')}
                </Button>
            </div>

            {empty !== null ? <p className="px-5 py-10 text-center text-muted-foreground">{empty}</p> : <div className="divide-y">{children}</div>}
        </section>
    );
}

function Row({ badge, title, reason, removeLabel, onRemove }: { badge: ReactNode; title: string; reason: string | null; removeLabel: string; onRemove: () => void }) {
    return (
        <div className="flex items-center justify-between gap-4 px-5 py-3">
            <div className="flex items-center gap-3">
                {badge}
                <div>
                    <div className="font-medium capitalize tabular-nums">{title}</div>
                    {reason && <p className="text-sm text-muted-foreground">{reason}</p>}
                </div>
            </div>
            <Button size="icon" variant="ghost" aria-label={removeLabel} onClick={onRemove}>
                <Trash2 />
            </Button>
        </div>
    );
}

function FormDialog({ open, onClose, title, description, submitLabel, processing, onSubmit, children }: {
    open: boolean;
    onClose: () => void;
    title: string;
    description: string;
    submitLabel: string;
    processing: boolean;
    onSubmit: (event: FormEvent) => void;
    children: ReactNode;
}) {
    return (
        <Dialog open={open} onOpenChange={(isOpen) => !isOpen && onClose()}>
            <DialogContent className="sm:max-w-md">
                <form onSubmit={onSubmit} className="space-y-6">
                    <DialogHeader>
                        <DialogTitle>{title}</DialogTitle>
                        <DialogDescription>{description}</DialogDescription>
                    </DialogHeader>
                    {children}
                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={onClose}>
                            {__('Cancel')}
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {submitLabel}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function ExceptionDialog({ memberId, open, onClose }: { memberId: number; open: boolean; onClose: () => void }) {
    const form = useForm({ member: memberId, date: '', unavailable: true, starts_at: '10:00', ends_at: '14:00', reason: '' });

    function save(event: FormEvent): void {
        event.preventDefault();
        form.post(exceptionRoutes.store.url(), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset('date', 'reason');
                onClose();
            },
        });
    }

    return (
        <FormDialog
            open={open}
            onClose={onClose}
            title={__('Date override')}
            description={__('Overrides the weekly hours for that single day.')}
            submitLabel={__('Save override')}
            processing={form.processing}
            onSubmit={save}
        >
            <FormField id="exception-date" label={__('Date')} error={form.errors.date}>
                <Input id="exception-date" type="date" required value={form.data.date} onChange={(event) => form.setData('date', event.target.value)} />
            </FormField>
            <div className="flex items-center gap-3">
                <Switch id="exception-closed" checked={form.data.unavailable} onCheckedChange={(checked) => form.setData('unavailable', checked)} />
                <Label htmlFor="exception-closed">{__('Closed all day')}</Label>
            </div>
            {!form.data.unavailable && (
                <div className="grid grid-cols-2 gap-4">
                    <FormField id="exception-from" label={__('From')} error={form.errors.starts_at}>
                        <Input id="exception-from" type="time" value={form.data.starts_at} onChange={(event) => form.setData('starts_at', event.target.value)} />
                    </FormField>
                    <FormField id="exception-to" label={__('To')} error={form.errors.ends_at}>
                        <Input id="exception-to" type="time" value={form.data.ends_at} onChange={(event) => form.setData('ends_at', event.target.value)} />
                    </FormField>
                </div>
            )}
            <FormField id="exception-reason" label={__('Reason')} error={form.errors.reason}>
                <Input id="exception-reason" value={form.data.reason} placeholder={__('Holidays, training, …')} onChange={(event) => form.setData('reason', event.target.value)} />
            </FormField>
        </FormDialog>
    );
}

function TimeBlockDialog({ memberId, open, onClose }: { memberId: number; open: boolean; onClose: () => void }) {
    const form = useForm({ member: memberId, date: '', starts_at: '09:00', ends_at: '13:00', reason: '' });

    function save(event: FormEvent): void {
        event.preventDefault();
        form.post(blockRoutes.store.url(), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset('date', 'reason');
                onClose();
            },
        });
    }

    return (
        <FormDialog
            open={open}
            onClose={onClose}
            title={__('Time block')}
            description={__('Removes the slots it covers, leaving the rest of the day open.')}
            submitLabel={__('Save block')}
            processing={form.processing}
            onSubmit={save}
        >
            <FormField id="block-date" label={__('Date')} error={form.errors.date}>
                <Input id="block-date" type="date" required value={form.data.date} onChange={(event) => form.setData('date', event.target.value)} />
            </FormField>
            <div className="grid grid-cols-2 gap-4">
                <FormField id="block-from" label={__('From')} error={form.errors.starts_at}>
                    <Input id="block-from" type="time" required value={form.data.starts_at} onChange={(event) => form.setData('starts_at', event.target.value)} />
                </FormField>
                <FormField id="block-to" label={__('To')} error={form.errors.ends_at}>
                    <Input id="block-to" type="time" required value={form.data.ends_at} onChange={(event) => form.setData('ends_at', event.target.value)} />
                </FormField>
            </div>
            <FormField id="block-reason" label={__('Reason')} error={form.errors.reason}>
                <Input id="block-reason" value={form.data.reason} placeholder={__('Meeting, appointment, …')} onChange={(event) => form.setData('reason', event.target.value)} />
            </FormField>
        </FormDialog>
    );
}

function ClosureDialog({ open, onClose }: { open: boolean; onClose: () => void }) {
    const form = useForm({ starts_on: '', ends_on: '', reason: '' });

    function save(event: FormEvent): void {
        event.preventDefault();
        form.post(closureRoutes.store.url(), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                onClose();
            },
        });
    }

    return (
        <FormDialog
            open={open}
            onClose={onClose}
            title={__('Close the portal')}
            description={__('Whole days, for every operator. Refused if anything is already booked inside.')}
            submitLabel={__('Save closure')}
            processing={form.processing}
            onSubmit={save}
        >
            <div className="grid grid-cols-2 gap-4">
                <FormField id="closure-from" label={__('First day')} error={form.errors.starts_on}>
                    <Input id="closure-from" type="date" required value={form.data.starts_on} onChange={(event) => form.setData('starts_on', event.target.value)} />
                </FormField>
                <FormField id="closure-to" label={__('Last day')} error={form.errors.ends_on}>
                    <Input id="closure-to" type="date" required value={form.data.ends_on} onChange={(event) => form.setData('ends_on', event.target.value)} />
                </FormField>
            </div>
            <FormField id="closure-reason" label={__('Reason')} error={form.errors.reason}>
                <Input id="closure-reason" value={form.data.reason} placeholder={__('Holidays, public holiday, …')} onChange={(event) => form.setData('reason', event.target.value)} />
            </FormField>
        </FormDialog>
    );
}

export default function Availability(props: Props) {
    const { member, members, isManager, uncovered, exceptions, timeBlocks, closures } = props;
    const [days, setDays] = useState<Day[]>(props.schedule);
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [dialog, setDialog] = useState<'exception' | 'block' | 'closure' | null>(null);

    // Another member, or the hours saved again: start from what the server holds.
    useEffect(() => setDays(props.schedule), [props.schedule]);

    function update(weekday: number, change: (day: Day) => Day): void {
        setDays((current) => current.map((day) => (day.weekday === weekday ? change(day) : day)));
    }

    function removeRange(day: Day, index: number): void {
        const ranges = day.ranges.filter((_, position) => position !== index);

        update(day.weekday, () => (ranges.length === 0 ? { ...day, enabled: false, ranges: [{ starts_at: '09:00', ends_at: '18:00' }] } : { ...day, ranges }));
    }

    function copyToOtherDays(source: Day): void {
        setDays((current) => current.map((day) => (day.weekday !== source.weekday && day.enabled ? { ...day, ranges: source.ranges.map((range) => ({ ...range })) } : day)));
        toast.success(__('Hours copied to the other working days.'));
    }

    function save(): void {
        // In time order, like the server checks them: an error then lands on
        // the range shown at that position.
        const sorted = days.map((day) => ({ ...day, ranges: [...day.ranges].sort((a, b) => a.starts_at.localeCompare(b.starts_at)) }));
        setDays(sorted);

        router.put(
            scheduleRoute.url(),
            { member: member.id, schedule: Object.fromEntries(sorted.map((day) => [day.weekday, { enabled: day.enabled, ranges: day.ranges }])) },
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onSuccess: () => setErrors({}),
                onError: (failed) => setErrors(failed as Record<string, string>),
            },
        );
    }

    const actions = (
        <>
            {members.length > 1 && (
                <FormField id="availability-member" label={__('Operator')} className="min-w-56">
                    <Select value={String(member.id)} onValueChange={(value) => router.get(availability.url(), { member: value }, { preserveScroll: true })}>
                        <SelectTrigger id="availability-member" className="w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {members.map((person) => (
                                <SelectItem key={person.id} value={String(person.id)}>
                                    {person.name}
                                    {person.is_manager ? ` · ${__('Manager')}` : ''}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </FormField>
            )}
            <Button onClick={save} disabled={processing}>
                <Check />
                {__('Save')}
            </Button>
        </>
    );

    return (
        <>
            <Head title={__('Availability')} />

            <div className="flex w-full flex-col gap-6">
                <PageHeader heading={__('Availability')} subheading={__('Weekly working hours and one-off exceptions used to generate bookable slots.')} actions={actions} />

                {uncovered.count > 0 && (
                    <Alert className="border-amber-300 text-amber-900 dark:border-amber-800 dark:text-amber-100">
                        <TriangleAlert />
                        <AlertTitle>{trans_choice('One booking falls outside these hours|:count bookings fall outside these hours', uncovered.count)}</AlertTitle>
                        <AlertDescription>
                            <p>{__('They are still confirmed. Move or cancel them, or widen the hours to cover them again.')}</p>
                            <ul className="mt-2 space-y-1">
                                {uncovered.items.map((item) => (
                                    <li key={item.id} className="tabular-nums">
                                        <span className="capitalize">{item.day}</span> {item.line}
                                    </li>
                                ))}
                            </ul>
                            <Button size="sm" variant="outline" className="mt-3" asChild>
                                <Link href={bookings()}>{__('Open bookings')}</Link>
                            </Button>
                        </AlertDescription>
                    </Alert>
                )}

                {!member.is_bookable && (
                    <Alert className="border-amber-300 text-amber-900 dark:border-amber-800 dark:text-amber-100">
                        <TriangleAlert />
                        <AlertDescription>{__('This person is not bookable, so these hours will not appear on the public page.')}</AlertDescription>
                    </Alert>
                )}

                <div className="divide-y rounded-xl border">
                    {days.map((day) => (
                        <div key={day.weekday} className="flex flex-col gap-4 p-5 sm:flex-row sm:items-start">
                            <div className="flex w-40 shrink-0 items-center gap-3 pt-1">
                                <Switch id={`day-${day.weekday}`} checked={day.enabled} onCheckedChange={(checked) => update(day.weekday, (current) => ({ ...current, enabled: checked }))} />
                                <Label htmlFor={`day-${day.weekday}`}>{day.label}</Label>
                            </div>

                            <div className="flex-1">
                                {day.enabled ? (
                                    <div className="space-y-3">
                                        {day.ranges.map((range, index) => (
                                            <div key={index} className="space-y-1">
                                                <div className="flex items-start gap-2">
                                                    <Input
                                                        type="time"
                                                        className="min-w-0 max-w-36 flex-1"
                                                        aria-label={`${day.label} ${__('From')}`}
                                                        value={range.starts_at}
                                                        onChange={(event) =>
                                                            update(day.weekday, (current) => ({
                                                                ...current,
                                                                ranges: current.ranges.map((item, position) => (position === index ? { ...item, starts_at: event.target.value } : item)),
                                                            }))
                                                        }
                                                    />
                                                    <span className="pt-2 text-muted-foreground">–</span>
                                                    <Input
                                                        type="time"
                                                        className="min-w-0 max-w-36 flex-1"
                                                        aria-label={`${day.label} ${__('To')}`}
                                                        value={range.ends_at}
                                                        onChange={(event) =>
                                                            update(day.weekday, (current) => ({
                                                                ...current,
                                                                ranges: current.ranges.map((item, position) => (position === index ? { ...item, ends_at: event.target.value } : item)),
                                                            }))
                                                        }
                                                    />
                                                    <Button size="icon" variant="ghost" title={__('Remove')} aria-label={__('Remove')} onClick={() => removeRange(day, index)}>
                                                        <Trash2 />
                                                    </Button>
                                                </div>
                                                <InputError message={errors[`schedule.${day.weekday}.ranges.${index}.starts_at`]} />
                                                <InputError message={errors[`schedule.${day.weekday}.ranges.${index}.ends_at`]} />
                                            </div>
                                        ))}

                                        <div className="flex flex-wrap gap-2">
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                onClick={() => update(day.weekday, (current) => ({ ...current, enabled: true, ranges: [...current.ranges, { starts_at: '09:00', ends_at: '13:00' }] }))}
                                            >
                                                <Plus />
                                                {__('Add range')}
                                            </Button>
                                            <Button size="sm" variant="ghost" onClick={() => copyToOtherDays(day)}>
                                                <Copy />
                                                {__('Copy to other days')}
                                            </Button>
                                        </div>
                                    </div>
                                ) : (
                                    <p className="pt-2 text-muted-foreground">{__('Closed')}</p>
                                )}
                            </div>
                        </div>
                    ))}
                </div>

                {isManager && (
                    <Panel
                        title={__('Portal closures')}
                        description={__('Holidays and days off for the whole business: nobody takes bookings on these days.')}
                        onAdd={() => setDialog('closure')}
                        empty={closures.length === 0 ? __('No closure planned.') : null}
                    >
                        {closures.map((closure) => (
                            <Row
                                key={closure.id}
                                badge={<ColorBadge color="rose">{closure.days}</ColorBadge>}
                                title={closure.label}
                                reason={closure.reason}
                                removeLabel={__('Remove closure')}
                                onRemove={() => router.delete(closureRoutes.destroy.url(closure.id), { preserveScroll: true })}
                            />
                        ))}
                    </Panel>
                )}

                <Panel
                    title={__('Time blocks')}
                    description={__('Short periods you are away, without touching the weekly hours.')}
                    onAdd={() => setDialog('block')}
                    empty={timeBlocks.length === 0 ? __('No upcoming time blocks.') : null}
                >
                    {timeBlocks.map((block) => (
                        <Row
                            key={block.id}
                            badge={<ColorBadge color="zinc">{block.hours}</ColorBadge>}
                            title={block.date}
                            reason={block.reason}
                            removeLabel={__('Remove time block')}
                            onRemove={() => router.delete(blockRoutes.destroy.url(block.id), { preserveScroll: true })}
                        />
                    ))}
                </Panel>

                <Panel
                    title={__('Date overrides')}
                    description={__('Holidays, closures or special hours for a single day.')}
                    onAdd={() => setDialog('exception')}
                    empty={exceptions.length === 0 ? __('No upcoming overrides.') : null}
                >
                    {exceptions.map((exception) => (
                        <Row
                            key={exception.id}
                            badge={<ColorBadge color={exception.closed ? 'rose' : 'indigo'}>{exception.closed ? __('Closed') : exception.hours}</ColorBadge>}
                            title={exception.date}
                            reason={exception.reason}
                            removeLabel={__('Remove exception')}
                            onRemove={() => router.delete(exceptionRoutes.destroy.url(exception.id), { preserveScroll: true })}
                        />
                    ))}
                </Panel>
            </div>

            <ExceptionDialog key={`exception-${member.id}`} memberId={member.id} open={dialog === 'exception'} onClose={() => setDialog(null)} />
            <TimeBlockDialog key={`block-${member.id}`} memberId={member.id} open={dialog === 'block'} onClose={() => setDialog(null)} />
            {isManager && <ClosureDialog open={dialog === 'closure'} onClose={() => setDialog(null)} />}
        </>
    );
}
