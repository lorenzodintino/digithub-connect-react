import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, BadgeCheck, BellRing, Banknote, Clock } from 'lucide-react';
import { type FormEvent, useEffect, useState } from 'react';
import { BookingQuestionField, type Question } from '@/components/booking-question-field';
import { FormField, InputError } from '@/components/form-field';
import { LocationIcon, SlotPicker, visitPage } from '@/components/slot-picker';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { __ } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { PortalLayoutProps, Slot, SlotDay, SlotMonth } from '@/types/portal';

interface Person {
    id: number;
    name: string;
    initials: string;
    avatar: string | null;
    job_title: string | null;
    bio: string | null;
}

type Filters = { month: string; operator: string; d: string; tz: string };

interface Props {
    portal: PortalLayoutProps;
    service: {
        slug: string;
        name: string;
        description: string | null;
        color: string;
        duration: string;
        location: { type: string; label: string; value: string | null };
        price: string | null;
        requires_confirmation: boolean;
        is_group: boolean;
        capacity: number;
        package: { name: string; duration: string; pause: string | null }[];
        waiting_list_open: boolean;
        urls: { book: string; waiting_list: string };
    };
    operators: Person[];
    filters: Filters;
    chosenOperator: number | null;
    calendar: SlotMonth;
    day: SlotDay | null;
    questions: Question[];
    timezones: string[];
    portalTimezone: string;
    waitingDefaults: { from: string; to: string };
}

type BookingForm = {
    name: string;
    email: string;
    phone: string;
    notes: string;
    privacy: boolean;
    answers: Record<string, string | string[]>;
    slot: string;
    operator: string;
    tz: string;
};

function PersonAvatar({ person, className }: { person: Person; className?: string }) {
    return (
        <Avatar className={className}>
            {person.avatar && <AvatarImage src={person.avatar} alt={person.name} />}
            <AvatarFallback className="text-xs">{person.initials}</AvatarFallback>
        </Avatar>
    );
}

function Consent({ id, portal, checked, onChange, error, withTerms }: { id: string; portal: PortalLayoutProps; checked: boolean; onChange: (checked: boolean) => void; error?: string; withTerms: boolean }) {
    return (
        <div className="space-y-1">
            <div className="flex items-start gap-2 text-sm">
                <Checkbox id={id} checked={checked} onCheckedChange={(value) => onChange(value === true)} className="mt-0.5" />
                <Label htmlFor={id} className="block leading-normal font-normal">
                    {__('I accept how :name handles my data for this booking.', { name: portal.name })}{' '}
                    {portal.privacy_url && (
                        <a href={portal.privacy_url} target="_blank" rel="noopener" className="underline">
                            {__('Privacy policy')}
                        </a>
                    )}
                    {withTerms && portal.terms_url && (
                        <>
                            <span className="text-muted-foreground"> · </span>
                            <a href={portal.terms_url} target="_blank" rel="noopener" className="underline">
                                {__('Terms of service')}
                            </a>
                        </>
                    )}
                </Label>
            </div>
            <InputError message={error} />
        </div>
    );
}

function WaitingList({ props }: { props: Props }) {
    const [open, setOpen] = useState(false);
    const form = useForm({ name: '', email: '', phone: '', from: '', to: '', privacy: false, tz: props.filters.tz });

    function start(): void {
        form.setData((data) => ({ ...data, from: props.waitingDefaults.from, to: props.waitingDefaults.to, tz: props.filters.tz }));
        setOpen(true);
    }

    function join(event: FormEvent): void {
        event.preventDefault();

        form.post(props.service.urls.waiting_list, {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);
                form.reset('name', 'email', 'phone', 'privacy');
            },
        });
    }

    return (
        <div className="mt-6 border-t pt-4">
            {!open ? (
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <span className="text-sm text-muted-foreground">{__('Nothing that suits you?')}</span>
                    <Button size="sm" variant="secondary" onClick={start}>
                        <BellRing />
                        {__('Tell me when a place opens up')}
                    </Button>
                </div>
            ) : (
                <form onSubmit={join} className="space-y-4">
                    <div>
                        <h3 className="font-semibold">{__('Waiting list')}</h3>
                        <p className="text-sm text-muted-foreground">{__('We write to everybody waiting as soon as a place frees up, and the first to book takes it.')}</p>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <FormField id="waiting-name" label={__('Your name')} error={form.errors.name}>
                            <Input id="waiting-name" required value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} />
                        </FormField>
                        <FormField id="waiting-email" label={__('Email')} error={form.errors.email}>
                            <Input id="waiting-email" type="email" required value={form.data.email} onChange={(event) => form.setData('email', event.target.value)} />
                        </FormField>
                        <FormField id="waiting-phone" label={__('Phone')} error={form.errors.phone}>
                            <Input id="waiting-phone" type="tel" value={form.data.phone} onChange={(event) => form.setData('phone', event.target.value)} />
                        </FormField>
                        <div className="grid grid-cols-2 gap-2">
                            <FormField id="waiting-from" label={__('From')} error={form.errors.from}>
                                <Input id="waiting-from" type="date" required value={form.data.from} onChange={(event) => form.setData('from', event.target.value)} />
                            </FormField>
                            <FormField id="waiting-to" label={__('To')} error={form.errors.to}>
                                <Input id="waiting-to" type="date" required value={form.data.to} onChange={(event) => form.setData('to', event.target.value)} />
                            </FormField>
                        </div>
                    </div>

                    <Consent id="waiting-privacy" portal={props.portal} checked={form.data.privacy} onChange={(checked) => form.setData('privacy', checked)} error={form.errors.privacy} withTerms={false} />

                    <div className="flex flex-wrap items-center gap-2">
                        <Button size="sm" type="submit" disabled={form.processing}>
                            {__('Put me on the list')}
                        </Button>
                        <Button
                            size="sm"
                            variant="ghost"
                            type="button"
                            onClick={() => {
                                form.clearErrors();
                                setOpen(false);
                            }}
                        >
                            {__('Cancel')}
                        </Button>
                    </div>
                </form>
            )}
        </div>
    );
}

export default function ServicePage(props: Props) {
    const { portal, service, operators, filters, chosenOperator, calendar, day, questions, timezones, portalTimezone } = props;
    const [slot, setSlot] = useState<Slot | null>(null);
    const form = useForm<BookingForm>({
        name: '',
        email: '',
        phone: '',
        notes: '',
        privacy: false,
        answers: Object.fromEntries(questions.map((question) => [String(question.id), question.type === 'multiple_choice' ? [] : ''])),
        slot: '',
        operator: filters.operator,
        tz: filters.tz,
    });
    const errors = form.errors as Record<string, string | undefined>;
    const chosen = operators.find((person) => person.id === chosenOperator) ?? null;
    const anyone = chosenOperator === null && operators.length > 1;

    function visit(changes: Partial<Filters>): void {
        const next = { ...filters, ...changes };

        visitPage({ month: next.month, operator: next.operator, d: next.d, tz: next.tz === portalTimezone ? '' : next.tz }, portal.embed);
    }

    // Times are printed on the visitor's clock: adopt the browser's timezone
    // once, when it is one we offer and nobody picked another.
    useEffect(() => {
        const detected = Intl.DateTimeFormat().resolvedOptions().timeZone;

        if (!new URLSearchParams(window.location.search).has('tz') && detected !== filters.tz && timezones.includes(detected)) {
            visit({ tz: detected });
        }
        // Only on arrival.
    }, []);

    function choose(picked: Slot): void {
        form.clearErrors();
        form.setData((data) => ({ ...data, slot: picked.value, operator: filters.operator, tz: filters.tz }));
        setSlot(picked);
    }

    function book(event: FormEvent): void {
        event.preventDefault();

        form.post(service.urls.book, {
            preserveScroll: true,
            // The time went to somebody else, or too many attempts: back to the
            // times of the day, which the redirect has already refreshed.
            onError: (errors) => errors.slot && setSlot(null),
        });
    }

    return (
        <>
            <Head title={`${service.name} · ${portal.name}`} />

            <main className="mx-auto w-full max-w-5xl px-4 py-8 sm:px-6 sm:py-12">
                <div className="space-y-6">
                    <Link href={portal.home_url} className="inline-flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground">
                        <ArrowLeft className="size-4" /> {__('All services')}
                    </Link>

                    <div className="overflow-hidden rounded-xl border bg-card">
                        <div className="h-1.5 w-full" style={{ backgroundColor: service.color }} />

                        <div className="grid lg:grid-cols-[300px_minmax(0,1fr)]">
                            <div className="space-y-5 border-b p-6 lg:border-e lg:border-b-0">
                                <div className="space-y-2">
                                    <h1 className="text-xl font-extrabold tracking-tight">{service.name}</h1>
                                    {service.description && <p className="text-sm text-muted-foreground">{service.description}</p>}
                                </div>

                                <dl className="space-y-2 text-sm">
                                    <div className="flex items-center gap-2 text-muted-foreground">
                                        <Clock className="size-4" /> {service.duration}
                                    </div>
                                    <div className="flex items-center gap-2 text-muted-foreground">
                                        <LocationIcon type={service.location.type} className="size-4" /> {service.location.label}
                                    </div>
                                    {service.price && (
                                        <div className="flex items-center gap-2 font-medium">
                                            <Banknote className="size-4" /> {service.price}
                                        </div>
                                    )}
                                    {service.requires_confirmation && (
                                        <div className="flex items-center gap-2 text-amber-700 dark:text-amber-400">
                                            <BadgeCheck className="size-4" /> {__('Needs approval')}
                                        </div>
                                    )}
                                </dl>

                                {service.location.value && <p className="rounded-lg bg-muted p-3 text-sm text-muted-foreground">{service.location.value}</p>}

                                {service.package.length > 0 && (
                                    <div className="space-y-2 text-sm">
                                        <h2 className="font-semibold">{__('Package programme')}</h2>
                                        {service.package.map((item, index) => (
                                            <div key={index}>
                                                <p>
                                                    {item.name} · {item.duration}
                                                </p>
                                                {item.pause && <p className="text-muted-foreground">{item.pause}</p>}
                                            </div>
                                        ))}
                                    </div>
                                )}

                                {service.is_group ? (
                                    <div className="rounded-lg bg-muted p-3 text-sm text-muted-foreground">{__('Up to :count people share this appointment.', { count: service.capacity })}</div>
                                ) : (
                                    operators.length > 1 && (
                                        <div className="space-y-2">
                                            <h2 className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">{__('Choose who you want to meet')}</h2>
                                            <div className="flex flex-wrap gap-2">
                                                <button
                                                    type="button"
                                                    aria-pressed={anyone}
                                                    onClick={() => visit({ operator: '', d: '' })}
                                                    className={cn(
                                                        'rounded-full border px-3 py-1.5 text-sm transition',
                                                        anyone ? 'border-primary bg-primary text-primary-foreground' : 'hover:border-muted-foreground',
                                                    )}
                                                >
                                                    {__('Anyone available')}
                                                </button>
                                                {operators.map((person) => (
                                                    <button
                                                        key={person.id}
                                                        type="button"
                                                        aria-pressed={chosen?.id === person.id}
                                                        onClick={() => visit({ operator: String(person.id), d: '' })}
                                                        className={cn(
                                                            'flex items-center gap-2 rounded-full border px-3 py-1.5 text-sm transition',
                                                            chosen?.id === person.id ? 'border-primary bg-primary text-primary-foreground' : 'hover:border-muted-foreground',
                                                        )}
                                                    >
                                                        <PersonAvatar person={person} className="size-5" />
                                                        {person.name}
                                                    </button>
                                                ))}
                                            </div>
                                        </div>
                                    )
                                )}

                                {anyone ? (
                                    <p className="border-t pt-4 text-sm text-muted-foreground">{__('You will be assigned to whoever is free at the time you choose.')}</p>
                                ) : (
                                    chosen && (
                                        <div className="space-y-2 border-t pt-4">
                                            <div className="flex items-center gap-3">
                                                <PersonAvatar person={chosen} className="size-8" />
                                                <div className="min-w-0">
                                                    <div className="truncate text-sm font-medium">{chosen.name}</div>
                                                    {chosen.job_title && <div className="truncate text-xs text-muted-foreground">{chosen.job_title}</div>}
                                                </div>
                                            </div>
                                            {chosen.bio && <p className="text-sm text-muted-foreground">{chosen.bio}</p>}
                                        </div>
                                    )
                                )}
                            </div>

                            <div className="p-6">
                                {operators.length === 0 ? (
                                    <div className="rounded-xl border border-dashed p-10 text-center">
                                        <p className="text-muted-foreground">{__('This service has nobody available at the moment.')}</p>
                                    </div>
                                ) : slot !== null ? (
                                    <form onSubmit={book} className="space-y-6">
                                        <div className="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-muted p-4">
                                            <div className="space-y-0.5">
                                                <div className="font-semibold capitalize">{slot.date_label}</div>
                                                <div className="text-sm text-muted-foreground tabular-nums">
                                                    {slot.hours} <span className="opacity-70">({filters.tz})</span>
                                                </div>
                                                {/* The same appointment on the portal's clock: no sentence to
                                                    translate, and half-hour offsets need no arithmetic. */}
                                                {slot.portal_hours && (
                                                    <div className="text-xs text-muted-foreground tabular-nums">
                                                        {slot.portal_hours} ({portalTimezone})
                                                    </div>
                                                )}
                                            </div>
                                            <Button size="sm" variant="secondary" type="button" onClick={() => setSlot(null)}>
                                                <ArrowLeft />
                                                {__('Change time')}
                                            </Button>
                                        </div>

                                        <div className="grid gap-4 sm:grid-cols-2">
                                            <FormField id="customer-name" label={__('Your name')} error={form.errors.name}>
                                                <Input id="customer-name" required autoFocus autoComplete="name" value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} />
                                            </FormField>
                                            <FormField id="customer-email" label={__('Email')} error={form.errors.email}>
                                                <Input
                                                    id="customer-email"
                                                    type="email"
                                                    required
                                                    autoComplete="email"
                                                    value={form.data.email}
                                                    onChange={(event) => form.setData('email', event.target.value)}
                                                />
                                            </FormField>
                                        </div>

                                        <FormField id="customer-phone" label={__('Phone')} error={form.errors.phone}>
                                            <Input id="customer-phone" type="tel" autoComplete="tel" value={form.data.phone} onChange={(event) => form.setData('phone', event.target.value)} />
                                        </FormField>

                                        <FormField id="customer-notes" label={__('Anything we should know?')} error={form.errors.notes}>
                                            <Textarea id="customer-notes" rows={3} placeholder={__('Optional')} value={form.data.notes} onChange={(event) => form.setData('notes', event.target.value)} />
                                        </FormField>

                                        {questions.length > 0 && (
                                            <div className="space-y-4 border-t pt-4">
                                                {questions.map((question) => (
                                                    <BookingQuestionField
                                                        key={question.id}
                                                        question={question}
                                                        value={form.data.answers[String(question.id)]}
                                                        error={errors[`answers.${question.id}`]}
                                                        onChange={(value) => form.setData('answers', { ...form.data.answers, [String(question.id)]: value })}
                                                    />
                                                ))}
                                            </div>
                                        )}

                                        <Consent id="customer-privacy" portal={portal} checked={form.data.privacy} onChange={(checked) => form.setData('privacy', checked)} error={form.errors.privacy} withTerms />

                                        <div className="flex flex-wrap items-center gap-3">
                                            <Button type="submit" disabled={form.processing}>
                                                {service.requires_confirmation ? __('Request this slot') : __('Confirm booking')}
                                            </Button>
                                            {form.processing && <span className="text-sm text-muted-foreground">{__('Saving…')}</span>}
                                        </div>

                                        {service.requires_confirmation && (
                                            <p className="text-sm text-muted-foreground">{__('Your request will be reviewed by :name before it is confirmed.', { name: portal.name })}</p>
                                        )}
                                    </form>
                                ) : (
                                    <>
                                        <SlotPicker
                                            calendar={calendar}
                                            day={day}
                                            selectedDate={filters.d}
                                            timezone={filters.tz}
                                            timezones={timezones}
                                            portalTimezone={portalTimezone}
                                            error={errors.slot}
                                            onMonth={(month) => visit({ month, d: '' })}
                                            onDay={(date) => visit({ d: date })}
                                            onTimezone={(tz) => visit({ tz })}
                                            onSlot={choose}
                                        />

                                        {props.service.waiting_list_open && <WaitingList props={props} />}
                                    </>
                                )}
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </>
    );
}
