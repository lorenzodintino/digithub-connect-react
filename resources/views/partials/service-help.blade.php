{{-- Plain-language help for the service form, opened from the "?" buttons. --}}
<flux:modal name="service-help" class="w-full max-w-2xl">
    <div class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('How a service works') }}</flux:heading>
            <flux:subheading>{{ __('These settings decide which times your customers can pick.') }}</flux:subheading>
        </div>

        <div class="max-h-[65vh] space-y-6 overflow-y-auto pe-1 text-sm">
            <section class="space-y-2">
                <flux:heading size="sm">{{ __('Duration and slot interval') }}</flux:heading>

                <p>{{ __('The duration is how long the appointment lasts for the customer. The interval is how often a new starting time is offered inside your working hours.') }}</p>

                <div class="rounded-lg bg-zinc-50 p-3 dark:bg-zinc-800/60">
                    <p class="font-medium">{{ __('Example') }}</p>
                    <p>{{ __('Working hours 09:00–12:00, duration 30 minutes, interval 15 minutes: the customer is offered 09:00, 09:15, 09:30 and so on up to 11:30. The last one starts at 11:30 because the appointment has to fit entirely inside the working hours.') }}</p>
                </div>

                <p class="text-zinc-500 dark:text-zinc-400">{{ __('An interval shorter than the duration offers overlapping times on purpose: as soon as somebody books 09:00, the 09:15 slot disappears by itself.') }}</p>
            </section>

            <section class="space-y-2">
                <flux:heading size="sm">{{ __('Buffer before and buffer after') }}</flux:heading>

                <p>{{ __('Time for you, invisible to the customer. It does not make the appointment longer, it makes the agenda busier around it.') }}</p>

                <div class="rounded-lg bg-zinc-50 p-3 dark:bg-zinc-800/60">
                    <p class="font-medium">{{ __('Example') }}</p>
                    <p>{{ __('A 10:00–10:30 appointment with 15 minutes before and 10 after takes 09:45–10:40 out of your agenda. The customer still reads 10:00–10:30.') }}</p>
                </div>

                <p class="text-zinc-500 dark:text-zinc-400">{{ __('Buffers only protect you from other bookings, not from your opening time: a 09:00 slot stays bookable even if you open at 09:00. They are saved with each booking, so changing them later never moves the appointments already taken.') }}</p>
            </section>

            <section class="space-y-2">
                <flux:heading size="sm">{{ __('Minimum notice') }}</flux:heading>

                <p>{{ __('How much warning you want before an appointment. With 120 minutes, at 09:00 the first time on offer is 11:00. With 0 a customer can book ten minutes from now.') }}</p>
            </section>

            <section class="space-y-2">
                <flux:heading size="sm">{{ __('Bookable up to') }}</flux:heading>

                <p>{{ __('How far ahead the calendar goes. With 60 days the public page stops two months from today.') }}</p>
            </section>

            <section class="space-y-2">
                <flux:heading size="sm">{{ __('Cancellation notice') }}</flux:heading>

                <p>{{ __('How late a customer may call the appointment off, or move it, from their own link. With 48 hours, the buttons disappear two days before and the page tells them to get in touch instead. With 0 they can do it up to the last minute.') }}</p>

                <p class="text-zinc-500 dark:text-zinc-400">{{ __('You are never bound by it: from the agenda you can cancel or move anything, whenever a customer calls.') }}</p>
            </section>

            <section class="space-y-2">
                <flux:heading size="sm">{{ __('Bookings need manual approval') }}</flux:heading>

                <p>{{ __('The booking arrives as "Pending" instead of "Confirmed", but the slot is already taken so nobody else can grab it. The customer gets a "request received" email, you get a notification, and no reminder goes out until you approve it. Confirming from the agenda sends the confirmation with the calendar file.') }}</p>
            </section>

            <section class="space-y-2">
                <flux:heading size="sm">{{ __('Published on the public page') }}</flux:heading>

                <p>{{ __('Turn it off and the service disappears from your page and stops offering slots. Bookings already taken stay valid.') }}</p>
            </section>

            <section class="space-y-2">
                <flux:heading size="sm">{{ __('Operators') }}</flux:heading>

                <p>{{ __('Who can deliver the service. With more than one, the customer either picks a person or leaves it open, and the appointment goes to whoever has the fewest upcoming bookings. A service with nobody bookable does not show up on the public page.') }}</p>
            </section>

            <section class="space-y-2">
                <flux:heading size="sm">{{ __('Price, place and colour') }}</flux:heading>

                <p>{{ __('An empty price means the service is free. The place and its details reach the customer in the confirmation and in the calendar file. The colour is the stripe on the card: leave the switch on and it follows the portal brand.') }}</p>
            </section>

            <section class="space-y-2">
                <flux:heading size="sm">{{ __('Putting it together') }}</flux:heading>

                <p>{{ __('First consultation, 45 minutes, interval 15, buffer after 15, minimum notice 1440 minutes, bookable up to 30 days.') }}</p>

                <p class="text-zinc-500 dark:text-zinc-400">{{ __('Customers start at 09:00, 09:15, 09:30 and so on, always at least a day ahead and never further than a month. Each booking holds 45 + 15 minutes, so the next customer never lands right on top of you.') }}</p>
            </section>
        </div>

        <div class="flex justify-end">
            <flux:modal.close>
                <flux:button variant="filled" type="button">{{ __('Close') }}</flux:button>
            </flux:modal.close>
        </div>
    </div>
</flux:modal>
