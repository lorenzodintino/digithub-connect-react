import { CircleHelp } from 'lucide-react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { __ } from '@/lib/i18n';

function Section({ title, children }: { title: string; children: ReactNode }) {
    return (
        <section className="space-y-2">
            <h3 className="font-medium">{title}</h3>
            {children}
        </section>
    );
}

function Example({ children }: { children: ReactNode }) {
    return (
        <div className="rounded-lg bg-muted p-3">
            <p className="font-medium">{__('Example')}</p>
            <p>{children}</p>
        </div>
    );
}

function Aside({ children }: { children: ReactNode }) {
    return <p className="text-muted-foreground">{children}</p>;
}

/** Plain-language help for the service form, opened from the "Help" buttons. */
export function ServiceHelp({ size = 'default' }: { size?: 'default' | 'sm' }) {
    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button type="button" variant="ghost" size={size} title={__('How a service works')}>
                    <CircleHelp />
                    {__('Help')}
                </Button>
            </DialogTrigger>

            <DialogContent className="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>{__('How a service works')}</DialogTitle>
                    <DialogDescription>{__('These settings decide which times your customers can pick.')}</DialogDescription>
                </DialogHeader>

                <div className="max-h-[65vh] space-y-6 overflow-y-auto pe-1 text-sm">
                    <Section title={__('Duration and slot interval')}>
                        <p>
                            {__('The duration is how long the appointment lasts for the customer. The interval is how often a new starting time is offered inside your working hours.')}
                        </p>
                        <Example>
                            {__('Working hours 09:00–12:00, duration 30 minutes, interval 15 minutes: the customer is offered 09:00, 09:15, 09:30 and so on up to 11:30. The last one starts at 11:30 because the appointment has to fit entirely inside the working hours.')}
                        </Example>
                        <Aside>
                            {__('An interval shorter than the duration offers overlapping times on purpose: as soon as somebody books 09:00, the 09:15 slot disappears by itself.')}
                        </Aside>
                    </Section>

                    <Section title={__('Buffer before and buffer after')}>
                        <p>{__('Time for you, invisible to the customer. It does not make the appointment longer, it makes the agenda busier around it.')}</p>
                        <Example>
                            {__('A 10:00–10:30 appointment with 15 minutes before and 10 after takes 09:45–10:40 out of your agenda. The customer still reads 10:00–10:30.')}
                        </Example>
                        <Aside>
                            {__('Buffers only protect you from other bookings, not from your opening time: a 09:00 slot stays bookable even if you open at 09:00. They are saved with each booking, so changing them later never moves the appointments already taken.')}
                        </Aside>
                    </Section>

                    <Section title={__('Minimum notice')}>
                        <p>
                            {__('How much warning you want before an appointment. With 120 minutes, at 09:00 the first time on offer is 11:00. With 0 a customer can book ten minutes from now.')}
                        </p>
                    </Section>

                    <Section title={__('Bookable up to')}>
                        <p>{__('How far ahead the calendar goes. With 60 days the public page stops two months from today.')}</p>
                    </Section>

                    <Section title={__('Cancellation notice')}>
                        <p>
                            {__('How late a customer may call the appointment off, or move it, from their own link. With 48 hours, the buttons disappear two days before and the page tells them to get in touch instead. With 0 they can do it up to the last minute.')}
                        </p>
                        <Aside>{__('You are never bound by it: from the agenda you can cancel or move anything, whenever a customer calls.')}</Aside>
                    </Section>

                    <Section title={__('Bookings need manual approval')}>
                        <p>
                            {__('The booking arrives as "Pending" instead of "Confirmed", but the slot is already taken so nobody else can grab it. The customer gets a "request received" email, you get a notification, and no reminder goes out until you approve it. Confirming from the agenda sends the confirmation with the calendar file.')}
                        </p>
                    </Section>

                    <Section title={__('Published on the public page')}>
                        <p>{__('Turn it off and the service disappears from your page and stops offering slots. Bookings already taken stay valid.')}</p>
                    </Section>

                    <Section title={__('Operators')}>
                        <p>
                            {__('Who can deliver the service. With more than one, the customer either picks a person or leaves it open, and the appointment goes to whoever has the fewest upcoming bookings. A service with nobody bookable does not show up on the public page.')}
                        </p>
                    </Section>

                    <Section title={__('Price, place and colour')}>
                        <p>
                            {__('An empty price means the service is free. The place and its details reach the customer in the confirmation and in the calendar file. The colour is the stripe on the card: leave the switch on and it follows the portal brand.')}
                        </p>
                    </Section>

                    <Section title={__('Putting it together')}>
                        <p>{__('First consultation, 45 minutes, interval 15, buffer after 15, minimum notice 1440 minutes, bookable up to 30 days.')}</p>
                        <Aside>
                            {__('Customers start at 09:00, 09:15, 09:30 and so on, always at least a day ahead and never further than a month. Each booking holds 45 + 15 minutes, so the next customer never lands right on top of you.')}
                        </Aside>
                    </Section>
                </div>

                <DialogFooter>
                    <DialogClose asChild>
                        <Button type="button" variant="outline">
                            {__('Close')}
                        </Button>
                    </DialogClose>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
