import { Head, Link, router, usePage } from '@inertiajs/react';
import { CalendarDays, Check, Clock, Download, TriangleAlert, X } from 'lucide-react';
import { type ReactNode, useState } from 'react';
import { CopyableInput } from '@/components/copyable-input';
import { SlotPicker, visitPage } from '@/components/slot-picker';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { __ } from '@/lib/i18n';
import type { PortalLayoutProps, SlotDay, SlotMonth } from '@/types/portal';

interface Booking {
    uid: string;
    state: 'cancelled' | 'pending' | 'confirmed';
    service: string;
    color: string;
    date: string;
    hours: string;
    timezone: string;
    operator: string;
    location: { label: string; value: string | null };
    price: string | null;
    customer: { name: string; email: string };
    phases: { service: string; hours: string; pause: string | null }[];
    moved_from: string | null;
    cancellation_reason: string | null;
    cancellable: boolean;
    can: { cancel: boolean; reschedule: boolean };
    change_deadline: string | null;
    notice_hours: number;
    suspended: boolean;
    contact: { phone: string | null; email: string | null };
    manage: { url: string; qr: string; qr_file_name: string } | null;
    calendar_links: { google: string; outlook: string; outlook_work: string; apple: string; download: string } | null;
    book_again_url: string;
    urls: { cancel: string; reschedule: string };
}

type Filters = { month: string; d: string; tz: string };

interface Props {
    portal: PortalLayoutProps;
    booking: Booking;
    reschedule: { calendar: SlotMonth; day: SlotDay | null } | null;
    filters: Filters;
    timezones: string[];
    portalTimezone: string;
}

function Heading({ booking, portal }: { booking: Booking; portal: PortalLayoutProps }) {
    if (booking.state === 'cancelled') {
        return (
            <>
                <span className="flex size-12 items-center justify-center rounded-full bg-rose-100 text-rose-600 dark:bg-rose-500/15 dark:text-rose-400">
                    <X />
                </span>
                <h1 className="text-2xl font-extrabold tracking-tight">{__('Booking cancelled')}</h1>
            </>
        );
    }

    if (booking.state === 'pending') {
        return (
            <>
                <span className="flex size-12 items-center justify-center rounded-full bg-amber-100 text-amber-600 dark:bg-amber-500/15 dark:text-amber-400">
                    <Clock />
                </span>
                <h1 className="text-2xl font-extrabold tracking-tight">{__('Request received')}</h1>
                <p className="text-muted-foreground">{__(':name will confirm your booking shortly.', { name: portal.name })}</p>
            </>
        );
    }

    return (
        <>
            <span className="flex size-12 items-center justify-center rounded-full bg-emerald-100 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-400">
                <Check />
            </span>
            <h1 className="text-2xl font-extrabold tracking-tight">{__('Booking confirmed')}</h1>
        </>
    );
}

function Row({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="flex items-center justify-between gap-4 px-4 py-3">
            <dt className="text-sm text-muted-foreground">{label}</dt>
            <dd className="text-end">{children}</dd>
        </div>
    );
}

/** The QR of the manage link as a PNG, drawn from the SVG the page shows. */
function downloadQr(svg: string, fileName: string): void {
    const image = new Image();

    image.onload = () => {
        const scale = 4;
        const canvas = document.createElement('canvas');
        canvas.width = image.width * scale;
        canvas.height = image.height * scale;

        const context = canvas.getContext('2d');

        if (context) {
            context.fillStyle = '#ffffff';
            context.fillRect(0, 0, canvas.width, canvas.height);
            context.drawImage(image, 0, 0, canvas.width, canvas.height);
        }

        const link = document.createElement('a');
        link.download = fileName;
        link.href = canvas.toDataURL('image/png');
        link.click();
    };

    image.src = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(svg);
}

function Keep({ booking }: { booking: Booking }) {
    if (booking.manage === null) {
        return null;
    }

    const manage = booking.manage;
    const links = booking.calendar_links;

    return (
        <>
            {links && (
                <div className="rounded-xl border p-4">
                    <h2 className="font-semibold">{__('Add it to your calendar')}</h2>
                    <p className="mt-1 text-sm text-muted-foreground">{__('So you get your own reminder, wherever you keep your appointments.')}</p>

                    <div className="mt-3 flex flex-wrap gap-2">
                        {(
                            [
                                ['Google Calendar', links.google, true],
                                ['Outlook.com', links.outlook, true],
                                ['Microsoft 365', links.outlook_work, true],
                                ['Apple Calendar', links.apple, false],
                            ] as const
                        ).map(([label, href, external]) => (
                            <Button key={label} size="sm" variant="secondary" asChild>
                                <a href={href} {...(external ? { target: '_blank', rel: 'noopener' } : {})}>
                                    <CalendarDays />
                                    {label}
                                </a>
                            </Button>
                        ))}
                        <Button size="sm" variant="ghost" asChild>
                            <a href={links.download}>
                                <Download />
                                {__('Other calendars (.ics)')}
                            </a>
                        </Button>
                    </div>
                </div>
            )}

            <div className="rounded-xl border p-4">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start">
                    <div className="min-w-0 flex-1 space-y-3">
                        <div>
                            <h2 className="font-semibold">{__('Save your booking')}</h2>
                            <p className="mt-1 text-sm text-muted-foreground">{__('This link always shows the current details, even if the booking is moved.')}</p>
                        </div>

                        <div className="grid gap-2">
                            <Label>{__('Booking link')}</Label>
                            <CopyableInput value={manage.url} />
                        </div>
                    </div>

                    <div className="flex flex-col items-center gap-2">
                        {/* Our own QrCode::branded(), never user input; the quiet zone is drawn into the SVG. */}
                        <div className="overflow-hidden rounded-lg bg-white" dangerouslySetInnerHTML={{ __html: manage.qr }} />
                        <Button size="sm" variant="ghost" onClick={() => downloadQr(manage.qr, manage.qr_file_name)}>
                            <Download />
                            {__('Download QR')}
                        </Button>
                        <span className="text-xs text-muted-foreground">{__('Scan to reopen')}</span>
                    </div>
                </div>
            </div>
        </>
    );
}

export default function BookingPage({ portal, booking, reschedule, filters, timezones, portalTimezone }: Props) {
    const [cancelling, setCancelling] = useState(false);
    const { errors } = usePage<{ errors: Record<string, string | undefined> }>().props;

    function visit(changes: Partial<Filters> & { reschedule?: boolean }): void {
        const next = { ...filters, ...changes };
        const open = changes.reschedule ?? reschedule !== null;

        visitPage(open ? { reschedule: '1', month: next.month, d: next.d, tz: next.tz === booking.timezone ? '' : next.tz } : {}, portal.embed);
    }

    return (
        <>
            <Head title={`${__('Your booking')} · ${portal.name}`} />

            <main className="mx-auto w-full max-w-3xl px-4 py-8 sm:px-6 sm:py-12">
                <div className="overflow-hidden rounded-xl border bg-card">
                    <div className="h-1.5 w-full" style={{ backgroundColor: booking.color }} />

                    <div className="space-y-6 p-6 sm:p-8">
                        <div className="flex flex-col items-center gap-3 text-center">
                            <Heading booking={booking} portal={portal} />
                        </div>

                        <dl className="divide-y rounded-xl border">
                            <Row label={__('Service')}>
                                <span className="font-medium">{booking.service}</span>
                            </Row>
                            <Row label={__('When')}>
                                <div className="font-medium capitalize">{booking.date}</div>
                                <div className="text-sm text-muted-foreground tabular-nums">
                                    {booking.hours} ({booking.timezone})
                                </div>
                            </Row>
                            <Row label={__('With')}>
                                <span className="font-medium">{booking.operator}</span>
                            </Row>
                            <Row label={__('Where')}>
                                <div className="font-medium">{booking.location.label}</div>
                                {booking.location.value && <div className="text-sm text-muted-foreground">{booking.location.value}</div>}
                            </Row>
                            {booking.price && (
                                <Row label={__('Price')}>
                                    <span className="font-medium">{booking.price}</span>
                                </Row>
                            )}
                            <Row label={__('Booked by')}>
                                <div className="font-medium">{booking.customer.name}</div>
                                <div className="text-sm text-muted-foreground">{booking.customer.email}</div>
                            </Row>
                        </dl>

                        {booking.phases.length > 0 && (
                            <div className="space-y-3 rounded-xl border p-4">
                                <h2 className="font-semibold">{__('Package programme')}</h2>
                                {booking.phases.map((phase, index) => (
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

                        {booking.moved_from && <p className="text-center text-sm text-muted-foreground">{__('Moved from :date', { date: booking.moved_from })}</p>}

                        {booking.state === 'cancelled' ? (
                            <>
                                {booking.cancellation_reason && <p className="rounded-xl bg-muted p-4 text-center text-sm text-muted-foreground">{booking.cancellation_reason}</p>}
                                <div className="text-center">
                                    <Button asChild>
                                        <Link href={booking.book_again_url}>{__('Book again')}</Link>
                                    </Button>
                                </div>
                            </>
                        ) : (
                            booking.cancellable && (
                                <>
                                    {/* The public page is gone, but this link must keep working:
                                        the customer still has to be able to call it off. */}
                                    {booking.suspended && (
                                        <Alert>
                                            <TriangleAlert />
                                            <AlertDescription>{__('This portal is currently suspended, so the appointment cannot be moved. You can still cancel it.')}</AlertDescription>
                                        </Alert>
                                    )}

                                    {reschedule ? (
                                        <div className="space-y-4 rounded-xl border p-4">
                                            <div className="flex items-center justify-between gap-3">
                                                <h2 className="font-semibold">{__('Pick a new time')}</h2>
                                                <Button size="sm" variant="ghost" onClick={() => visit({ reschedule: false })}>
                                                    {__('Cancel')}
                                                </Button>
                                            </div>

                                            <SlotPicker
                                                calendar={reschedule.calendar}
                                                day={reschedule.day}
                                                selectedDate={filters.d}
                                                timezone={filters.tz}
                                                timezones={timezones}
                                                portalTimezone={portalTimezone}
                                                error={errors.slot}
                                                onMonth={(month) => visit({ month, d: '' })}
                                                onDay={(date) => visit({ d: date })}
                                                onTimezone={(tz) => visit({ tz })}
                                                onSlot={(slot) => router.post(booking.urls.reschedule, { slot: slot.value }, { preserveScroll: true })}
                                            />
                                        </div>
                                    ) : booking.can.cancel ? (
                                        <>
                                            <div className="flex flex-wrap justify-center gap-2">
                                                {booking.can.reschedule && (
                                                    <Button variant="secondary" onClick={() => visit({ reschedule: true, d: '' })}>
                                                        <CalendarDays />
                                                        {__('Reschedule')}
                                                    </Button>
                                                )}
                                                <Button variant="ghost" onClick={() => setCancelling(true)}>
                                                    <X />
                                                    {__('Cancel booking')}
                                                </Button>
                                            </div>

                                            {booking.change_deadline && (
                                                <p className="text-center text-sm text-muted-foreground">{__('You can change it yourself until :date.', { date: booking.change_deadline })}</p>
                                            )}
                                        </>
                                    ) : (
                                        // Past the notice the service asks for: the appointment
                                        // stands, and only the portal can change it now.
                                        <div className="space-y-3 rounded-xl border p-4 text-center text-sm">
                                            <p>
                                                {__('This appointment can no longer be changed online: :name asks for at least :hours hours notice.', {
                                                    name: portal.name,
                                                    hours: booking.notice_hours,
                                                })}
                                            </p>

                                            {(booking.contact.phone || booking.contact.email) && (
                                                <p>
                                                    {__('Need to change it anyway? Get in touch:')}{' '}
                                                    {booking.contact.phone && (
                                                        <a href={`tel:${booking.contact.phone}`} className="underline">
                                                            {booking.contact.phone}
                                                        </a>
                                                    )}
                                                    {booking.contact.phone && booking.contact.email && <span className="text-muted-foreground"> · </span>}
                                                    {booking.contact.email && (
                                                        <a href={`mailto:${booking.contact.email}`} className="underline">
                                                            {booking.contact.email}
                                                        </a>
                                                    )}
                                                </p>
                                            )}
                                        </div>
                                    )}

                                    {!reschedule && <Keep booking={booking} />}
                                </>
                            )
                        )}
                    </div>
                </div>

                {booking.can.cancel && (
                    // Not offered at all once the notice has passed: no dialog for an
                    // action the server would refuse anyway.
                    <Dialog open={cancelling} onOpenChange={setCancelling}>
                        <DialogContent className="sm:max-w-md">
                            <DialogHeader>
                                <DialogTitle>{__('Cancel this booking?')}</DialogTitle>
                                <DialogDescription>{__('The slot will be released and made available again.')}</DialogDescription>
                            </DialogHeader>
                            <DialogFooter>
                                <Button variant="outline" onClick={() => setCancelling(false)}>
                                    {__('Keep it')}
                                </Button>
                                <Button variant="destructive" onClick={() => router.post(booking.urls.cancel, {}, { preserveScroll: true, onSuccess: () => setCancelling(false) })}>
                                    {__('Cancel booking')}
                                </Button>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>
                )}
            </main>
        </>
    );
}
