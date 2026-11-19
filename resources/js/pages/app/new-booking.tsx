import { Head, Link, useForm } from '@inertiajs/react';
import { type FormEvent, useEffect, useState } from 'react';
import { BookingQuestionField, type Question } from '@/components/booking-question-field';
import { FormField, InputError } from '@/components/form-field';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { __, trans_choice } from '@/lib/i18n';
import { bookings } from '@/routes/app';
import * as bookingRoutes from '@/routes/app/bookings';

interface BookableService {
    slug: string;
    name: string;
    is_group: boolean;
    requires_confirmation: boolean;
    operators: { id: string; name: string }[];
    questions: Question[];
}

interface Slot {
    value: string;
    time: string;
    seats_left: number | null;
}

interface Props {
    services: BookableService[];
    canChooseOperator: boolean;
    today: string;
    portalTimezone: string;
}

export default function NewBooking({ services, canChooseOperator, today, portalTimezone }: Props) {
    const [serviceSlug, setServiceSlug] = useState('');
    const [day, setDay] = useState(today);
    const [revision, setRevision] = useState(0);
    const [availability, setAvailability] = useState<{ url: string; revision: number; slots: Slot[] | null } | null>(null);
    const form = useForm({ operator: '', slot: '', name: '', email: '', phone: '', notes: '', answers: {} as Record<string, string | string[]> });
    const { data, errors, setData, clearErrors } = form;
    const service = services.find((item) => item.slug === serviceSlug);
    const slotsUrl = service && day && (!canChooseOperator || data.operator)
        ? bookingRoutes.slots.url(service.slug, { query: { day, operator: data.operator || undefined } })
        : null;
    const currentAvailability = availability?.url === slotsUrl && availability.revision === revision ? availability : null;
    const slots = currentAvailability?.slots ?? [];

    useEffect(() => {
        if (slotsUrl === null) {
            return;
        }

        const controller = new AbortController();
        void (async () => {
            try {
                const response = await fetch(slotsUrl, {
                    signal: controller.signal,
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (!response.ok) {
                    throw new Error();
                }
                const result: { slots: Slot[] } = await response.json();
                if (!controller.signal.aborted) {
                    setAvailability({ url: slotsUrl, revision, slots: result.slots });
                }
            } catch {
                if (!controller.signal.aborted) {
                    setAvailability({ url: slotsUrl, revision, slots: null });
                }
            }
        })();

        return () => controller.abort();
    }, [slotsUrl, revision]);

    function selectService(slug: string): void {
        const operators = services.find((item) => item.slug === slug)?.operators ?? [];
        setServiceSlug(slug);
        setAvailability(null);
        setData({ ...data, operator: canChooseOperator && operators.length === 1 ? operators[0].id : '', slot: '', answers: {} });
        clearErrors();
    }

    function submit(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();
        if (!service || !slots.some((slot) => slot.value === data.slot) || form.processing) {
            return;
        }

        form.post(bookingRoutes.store.url(service.slug), {
            preserveScroll: true,
            onError: (validationErrors) => {
                if (validationErrors.slot) {
                    setData('slot', '');
                    setRevision((current) => current + 1);
                }
            },
        });
    }

    return (
        <div className="flex w-full max-w-3xl flex-col gap-6">
            <Head title={__('New booking')} />
            <PageHeader heading={__('New booking')} actions={<Button variant="outline" asChild><Link href={bookings()}>{__('Back to bookings')}</Link></Button>} />

            {services.length === 0 ? (
                <p className="rounded-xl border bg-card p-6 text-sm text-muted-foreground">{__('No services are available to book.')}</p>
            ) : (
                <form onSubmit={submit} className="flex flex-col gap-6">
                    <div className="grid gap-4 rounded-xl border bg-card p-4 sm:grid-cols-2 sm:p-6">
                        <FormField id="manual-booking-service" label={__('Service')}>
                            <Select value={serviceSlug} onValueChange={selectService}>
                                <SelectTrigger id="manual-booking-service" className="w-full"><SelectValue placeholder={__('Choose…')} /></SelectTrigger>
                                <SelectContent>
                                    {services.map((item) => <SelectItem key={item.slug} value={item.slug}>{item.name}</SelectItem>)}
                                </SelectContent>
                            </Select>
                        </FormField>
                        {canChooseOperator ? (
                            <FormField id="manual-booking-operator" label={__('Operator')} error={errors.operator}>
                                <Select value={data.operator} disabled={!service} onValueChange={(operator) => {
                                    setAvailability(null);
                                    setData({ ...data, operator, slot: '' });
                                    clearErrors('operator', 'slot');
                                }}>
                                    <SelectTrigger id="manual-booking-operator" className="w-full"><SelectValue placeholder={__('Choose…')} /></SelectTrigger>
                                    <SelectContent>
                                        {service?.operators.map((operator) => <SelectItem key={operator.id} value={operator.id}>{operator.name}</SelectItem>)}
                                    </SelectContent>
                                </Select>
                            </FormField>
                        ) : service && (
                            <FormField id="manual-booking-operator" label={__('Operator')} error={errors.operator}>
                                <Input id="manual-booking-operator" readOnly value={service.operators[0].name} />
                            </FormField>
                        )}
                        <FormField id="manual-booking-day" label={__('Date')} description={__('Times are shown in :timezone.', { timezone: portalTimezone })}>
                            <Input id="manual-booking-day" type="date" min={today} required value={day} onChange={(event) => {
                                setDay(event.target.value);
                                setAvailability(null);
                                setData('slot', '');
                                clearErrors('slot');
                            }} />
                        </FormField>
                        <FormField id="manual-booking-slot" label={__('Available times')} error={errors.slot}>
                            <Select value={data.slot} disabled={slots.length === 0} onValueChange={(slot) => {
                                setData('slot', slot);
                                clearErrors('slot');
                            }}>
                                <SelectTrigger id="manual-booking-slot" className="w-full"><SelectValue placeholder={__('Choose…')} /></SelectTrigger>
                                <SelectContent>
                                    {slots.map((slot) => <SelectItem key={slot.value} value={slot.value}>
                                        {slot.time}{slot.seats_left !== null && ` · ${trans_choice('one seat free|:count seats free', slot.seats_left)}`}
                                    </SelectItem>)}
                                </SelectContent>
                            </Select>
                            {slotsUrl && <div role="status" className="text-sm text-muted-foreground">
                                {currentAvailability === null && __('Checking availability…')}
                                {currentAvailability?.slots?.length === 0 && __('No available times for this day.')}
                                {currentAvailability?.slots === null && (
                                    <Button type="button" variant="link" className="h-auto p-0" onClick={() => setRevision((current) => current + 1)}>
                                        {__('Could not load times. Try again.')}
                                    </Button>
                                )}
                            </div>}
                        </FormField>
                        {service?.is_group && <p className="text-sm text-muted-foreground sm:col-span-2">{__('For a group service, the customer joins the session and its assigned staff.')}</p>}
                    </div>

                    {service && (
                        <>
                            <div className="grid gap-4 rounded-xl border bg-card p-4 sm:grid-cols-2 sm:p-6">
                                <h2 className="font-semibold sm:col-span-2">{__('Customer')}</h2>
                                <FormField id="manual-booking-name" label={__('Name')} error={errors.name}>
                                    <Input id="manual-booking-name" required maxLength={120} value={data.name} onChange={(event) => setData('name', event.target.value)} />
                                </FormField>
                                <FormField id="manual-booking-email" label={__('Email')} error={errors.email}>
                                    <Input id="manual-booking-email" type="email" required maxLength={255} value={data.email} onChange={(event) => setData('email', event.target.value)} />
                                </FormField>
                                <FormField id="manual-booking-phone" label={__('Phone')} error={errors.phone}>
                                    <Input id="manual-booking-phone" type="tel" maxLength={40} value={data.phone} onChange={(event) => setData('phone', event.target.value)} />
                                </FormField>
                                <FormField id="manual-booking-notes" label={__('Notes')} error={errors.notes} className="sm:col-span-2">
                                    <Textarea id="manual-booking-notes" rows={3} maxLength={1000} value={data.notes} onChange={(event) => setData('notes', event.target.value)} />
                                </FormField>
                                {service.questions.map((question) => (
                                    <div className="sm:col-span-2" key={question.id}>
                                        <BookingQuestionField
                                            question={question}
                                            value={data.answers[question.id] ?? (question.type === 'multiple_choice' ? [] : '')}
                                            error={errors[`answers.${question.id}`]}
                                            onChange={(value) => setData('answers', { ...data.answers, [question.id]: value })}
                                        />
                                    </div>
                                ))}
                                <InputError message={errors.answers} />
                            </div>
                            {service.requires_confirmation && <p className="text-sm text-muted-foreground">{__('This service requires manual confirmation.')}</p>}
                            <div className="flex justify-end">
                                <Button type="submit" disabled={form.processing || !slots.some((slot) => slot.value === data.slot)}>{__('Create booking')}</Button>
                            </div>
                        </>
                    )}
                </form>
            )}
        </div>
    );
}
