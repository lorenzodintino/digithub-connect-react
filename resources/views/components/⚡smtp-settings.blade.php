<?php

use App\Actions\Tenants\TestMailer;
use App\Models\Tenant;
use App\Support\MailFailure;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Email delivery settings of a portal.
 *
 * Used both by the manager on their own portal and by the platform
 * administrator on any of them, so authorization goes through the policy
 * rather than through the role.
 */
new class extends Component {
    #[Locked]
    public Tenant $tenant;

    public string $mail_host = '';

    public string $mail_port = '587';

    public string $mail_username = '';

    /**
     * Left empty on purpose: a stored password is never rendered back, and an
     * empty value on save means "keep the one already there".
     */
    public string $mail_password = '';

    public string $mail_encryption = 'tls';

    public string $mail_from_address = '';

    public string $reply_to_email = '';

    public string $test_recipient = '';

    public bool $notify_manager_on_booking = false;

    public string $reminder_hours_before = '24';

    /**
     * Load the current settings.
     */
    public function mount(Tenant $tenant): void
    {
        $this->authorize('update', $tenant);

        $this->tenant = $tenant;

        $this->mail_host = $tenant->mail_host ?? '';
        $this->mail_port = (string) ($tenant->mail_port ?: 587);
        $this->mail_username = $tenant->mail_username ?? '';
        $this->mail_encryption = $tenant->mail_encryption ?? 'tls';
        $this->mail_from_address = $tenant->mail_from_address ?? '';
        $this->reply_to_email = $tenant->reply_to_email ?? '';
        $this->notify_manager_on_booking = (bool) $tenant->notify_manager_on_booking;
        $this->reminder_hours_before = (string) ($tenant->reminder_hours_before ?? '');
        $this->test_recipient = auth()->user()->email;
    }

    /**
     * How an email will actually look once sent.
     *
     * Four fields decide two headers, and no amount of field-by-field wording
     * explains that as well as showing the result.
     *
     * Settings that nobody has proved yet change nothing on their own, so the
     * preview keeps showing the platform sender and puts the portal's own
     * address on a second line, as what the test will unlock.
     *
     * @return array{from: string, afterTest: string|null, replyTo: string|null}
     */
    #[Computed]
    public function preview(): array
    {
        $own = $this->mail_host !== '' && $this->mail_username !== ''
            ? ($this->mail_from_address ?: $this->mail_username)
            : null;

        $sending = $own !== null && $this->usesOwnMailer;

        return [
            'from' => $sending ? $own : config('mail.from.address'),
            'afterTest' => $sending ? null : $own,
            'replyTo' => $this->reply_to_email ?: ($this->tenant->contact_email ?: null),
        ];
    }

    /**
     * Whether the sender address looks foreign to the SMTP account.
     *
     * A warning rather than a rule: some accounts are legitimately allowed to
     * send as an alias on another domain. Most are not, and the server refuses
     * with an error that says nothing useful.
     */
    #[Computed]
    public function senderLooksForeign(): bool
    {
        if ($this->mail_from_address === '' || $this->mail_username === '') {
            return false;
        }

        return Str::after($this->mail_from_address, '@') !== Str::after($this->mail_username, '@');
    }

    /**
     * The actionable half of the last transport error, when we recognise it.
     *
     * The server's own words stay on screen underneath: this says what to do,
     * that one is the evidence to forward to the provider.
     */
    #[Computed]
    public function mailErrorHint(): ?string
    {
        return MailFailure::explain($this->tenant->mail_error);
    }

    /**
     * Determine whether the portal is currently sending on its own.
     */
    #[Computed]
    public function usesOwnMailer(): bool
    {
        return $this->tenant->hasCustomMailer();
    }

    /**
     * Whether an SMTP server is configured, working or not.
     */
    #[Computed]
    public function hasMailerSettings(): bool
    {
        return $this->tenant->hasMailerSettings();
    }

    /**
     * Whether the person reading may change the SMTP server itself.
     *
     * The manager keeps everything they can act on — who gets a copy, when the
     * reminder goes out, where answers land — and sees the state of the server
     * without holding its credentials.
     */
    #[Computed]
    public function canConfigure(): bool
    {
        return Gate::allows('configureMailer', $this->tenant);
    }

    /**
     * Persist the settings.
     */
    public function save(): void
    {
        $this->authorize('update', $this->tenant);

        // Trim before validating, not after: these values are almost always
        // pasted, and Livewire property updates skip the TrimStrings middleware
        // that would clean a classic form submission. A stray space otherwise
        // either fails the email rule or survives into a DNS lookup.
        foreach (['mail_host', 'mail_username', 'mail_password', 'mail_from_address', 'reply_to_email'] as $field) {
            $this->$field = trim($this->$field);
        }

        $serverRules = $this->canConfigure ? [
            'mail_host' => ['nullable', 'string', 'max:255', $this->hostRule()],
            'mail_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'mail_username' => ['nullable', 'string', 'max:255'],
            'mail_password' => ['nullable', 'string', 'max:255'],
            'mail_encryption' => ['nullable', Rule::in(['tls', 'ssl', 'none'])],
            'mail_from_address' => ['nullable', 'email', 'max:255'],
        ] : [];

        $validated = $this->validate($serverRules + [
            'reply_to_email' => ['nullable', 'email', 'max:255'],
            'notify_manager_on_booking' => ['boolean'],
            'reminder_hours_before' => ['nullable', 'integer', 'min:1', 'max:336'],
        ], attributes: [
            'mail_host' => __('server'),
            'mail_port' => __('port'),
            'mail_username' => __('username'),
            'mail_password' => __('password'),
            'mail_from_address' => __('sender address'),
            'reminder_hours_before' => __('reminder lead time'),
            'reply_to_email' => __('reply address'),
        ]);

        $attributes = [
            'reply_to_email' => $validated['reply_to_email'] ?: null,
            'notify_manager_on_booking' => $validated['notify_manager_on_booking'],
            'reminder_hours_before' => $validated['reminder_hours_before'] ?: null,
        ];

        if ($this->canConfigure) {
            $attributes += [
                'mail_host' => $validated['mail_host'] ?: null,
                'mail_port' => $validated['mail_port'] ?: null,
                'mail_username' => $validated['mail_username'] ?: null,
                'mail_encryption' => ($validated['mail_encryption'] ?? 'tls') === 'none' ? null : $validated['mail_encryption'],
                'mail_from_address' => $validated['mail_from_address'] ?: null,
                // Changed settings are unproven settings: the portal keeps
                // sending through the platform until a test goes through.
                'mail_error' => null,
                'mail_failed_at' => null,
                'mail_verified_at' => null,
            ];

            // An empty field keeps the stored password; clearing the host clears it.
            if ($validated['mail_password'] !== '') {
                $attributes['mail_password'] = $validated['mail_password'];
            } elseif ($attributes['mail_host'] === null) {
                $attributes['mail_password'] = null;
            }
        }

        $this->tenant->update($attributes);

        $this->mail_password = '';

        unset($this->usesOwnMailer, $this->hasMailerSettings, $this->preview, $this->senderLooksForeign, $this->mailErrorHint);

        Flux::toast(variant: 'success', text: __('Email settings saved.'));
    }

    /**
     * Reject anything that is not the name of a mail server.
     *
     * The address of the mailbox in the server field is the mistake everybody
     * makes, and the transport reports it as an unresolvable host name — which
     * reads like a network problem rather than a filled-in-wrong field.
     */
    protected function hostRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_string($value) || $value === '') {
                return;
            }

            if (str_contains($value, '@')) {
                $fail(__('The SMTP server is the name of a machine, like smtp.example.com — not an email address. The address goes in the username field below.'));

                return;
            }

            if (preg_match('~[\s/:]~', $value) === 1) {
                $fail(__('Only the server name here: no http://, no port, no spaces.'));

                return;
            }

            if (filter_var($value, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false) {
                $fail(__('This does not look like a server name.'));
            }
        };
    }

    /**
     * Send a test message through the configured server.
     */
    public function sendTest(TestMailer $testMailer): void
    {
        $this->authorize('update', $this->tenant);

        // Only the recipient is validated below, so a failure reported on
        // mail_host by an earlier attempt would otherwise stay on screen.
        $this->resetValidation();

        $this->validate([
            'test_recipient' => ['required', 'email'],
        ], attributes: ['test_recipient' => __('recipient')]);

        if (! $this->tenant->fresh()->hasMailerSettings()) {
            $this->addError('mail_host', __('Fill in server, username and password first, then save.'));

            return;
        }

        ['sent' => $sent, 'error' => $error] = $testMailer->handle($this->tenant->fresh(), $this->test_recipient);

        $this->tenant->refresh();

        unset($this->usesOwnMailer, $this->hasMailerSettings, $this->preview, $this->mailErrorHint);

        if ($sent) {
            Flux::toast(variant: 'success', text: __('Test email sent to :address.', ['address' => $this->test_recipient]));

            return;
        }

        Flux::toast(variant: 'danger', text: __('The server refused the message.'));
        $this->addError('mail_host', MailFailure::explain($error) ?? $error);
    }
}; ?>

<div class="space-y-6 rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
    <div>
        <flux:heading size="lg">{{ __('Email delivery') }}</flux:heading>
        <flux:text class="mt-1">
            @if ($this->canConfigure)
                {{ __('Emails leave through the platform. A portal can send from its own server instead, once that server has proved it works.') }}
            @else
                {{ __('Your booking emails are sent by the platform, with your name on them. Ask us if you want them to leave from your own address.') }}
            @endif
        </flux:text>
    </div>

    @if ($tenant->mailDeliveryFailing())
        <flux:callout icon="exclamation-triangle" variant="danger">
            <flux:callout.heading>{{ __('An email did not go out') }}</flux:callout.heading>
            <flux:callout.text>
                {{ __('Failed on :date, so the emails went back to leaving through the platform. Nothing is lost: they are going out under our address instead of yours.', [
                    'date' => $tenant->mail_failed_at->setTimezone(auth()->user()->timezone)->translatedFormat('d M Y, H:i'),
                ]) }}
            </flux:callout.text>
            @if ($this->mailErrorHint)
                <flux:callout.text class="mt-1">{{ $this->mailErrorHint }}</flux:callout.text>
            @endif
            <flux:callout.text class="mt-1 text-xs opacity-75">{{ $tenant->mail_error }}</flux:callout.text>
        </flux:callout>
    @elseif ($this->hasMailerSettings && ! $tenant->planAllowsCustomMailer())
        <flux:callout icon="rectangle-stack" variant="warning">
            <flux:callout.text>
                {{ __('The plan of this portal does not include its own email server: the emails leave through the platform. The settings are kept for when the plan changes.') }}
            </flux:callout.text>
        </flux:callout>
    @elseif ($this->usesOwnMailer)
        <flux:callout icon="check-circle" variant="success">
            <flux:callout.text>
                {{ __('Sending as :address since the test of :date.', [
                    'address' => $tenant->mailFromAddress(),
                    'date' => $tenant->mail_verified_at->setTimezone(auth()->user()->timezone)->translatedFormat('d M Y, H:i'),
                ]) }}
            </flux:callout.text>
        </flux:callout>
    @elseif ($this->hasMailerSettings)
        <flux:callout icon="paper-airplane" variant="warning">
            <flux:callout.heading>{{ __('Configured, not proved yet') }}</flux:callout.heading>
            <flux:callout.text>
                {{ __('The emails keep leaving through the platform until a test message goes through this server.') }}
            </flux:callout.text>
            @if ($tenant->mail_error)
                @if ($this->mailErrorHint)
                    <flux:callout.text class="mt-1">{{ $this->mailErrorHint }}</flux:callout.text>
                @endif
                <flux:callout.text class="mt-1 text-xs opacity-75">{{ $tenant->mail_error }}</flux:callout.text>
            @endif
        </flux:callout>
    @else
        <flux:callout icon="information-circle" variant="secondary">
            {{ __('Emails currently leave through the platform: your customers see your name, but the address is ours.') }}
        </flux:callout>
    @endif

    <form wire:submit="save" class="space-y-4">
        @if ($this->canConfigure)
        <div class="grid gap-4 sm:grid-cols-2">
            <flux:input
                wire:model="mail_host"
                :label="__('SMTP server')"
                placeholder="smtp.example.com"
                :description="__('The outgoing server of your provider. Not your email address.')"
            />
            <flux:input wire:model="mail_port" type="number" :label="__('Port')" placeholder="587" />
            <flux:input
                wire:model.live.debounce.500ms="mail_username"
                :label="__('Username')"
                autocomplete="off"
                :description="__('The full email address, not just the part before the @.')"
            />

            <flux:input
                wire:model="mail_password"
                type="password"
                :label="__('Password')"
                autocomplete="new-password"
                viewable
                :placeholder="$this->hasMailerSettings ? __('Unchanged') : ''"
                :description="$this->hasMailerSettings ? __('Leave empty to keep the current one.') : null"
            />

            <flux:select
                wire:model="mail_encryption"
                :label="__('Encryption')"
                :description="__('Port 465 always uses SSL, whatever is chosen here.')"
            >
                <flux:select.option value="tls">STARTTLS</flux:select.option>
                <flux:select.option value="ssl">SSL</flux:select.option>
                <flux:select.option value="none">{{ __('None') }}</flux:select.option>
            </flux:select>

            <flux:input
                wire:model.live.debounce.500ms="mail_from_address"
                type="email"
                :label="__('Sender address')"
                :placeholder="$mail_username"
                :description="__('Who the customer sees. It must be an address the SMTP account is allowed to send from — usually the username itself.')"
            />

            <flux:input
                wire:model.live.debounce.500ms="reply_to_email"
                type="email"
                :label="__('Reply address')"
                :placeholder="$this->tenant->contact_email"
                :description="__('Where an answer from the customer lands. Empty uses the contact email.')"
            />
        </div>

        @if ($this->senderLooksForeign)
            <flux:callout icon="exclamation-triangle" variant="warning">
                <flux:callout.heading>{{ __('The sender belongs to another domain') }}</flux:callout.heading>
                <flux:callout.text>
                    {{ __('You authenticate as :username but send as :sender. Most servers refuse that, and the error they return explains little. Use the same address, or leave the field empty.', [
                        'username' => $mail_username,
                        'sender' => $mail_from_address,
                    ]) }}
                </flux:callout.text>
            </flux:callout>
        @endif
        @elseif ($this->hasMailerSettings)
            <dl class="grid gap-x-4 gap-y-2 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-zinc-500 dark:text-zinc-400">{{ __('SMTP server') }}</dt>
                    <dd class="truncate font-mono">{{ $tenant->mail_host }}:{{ $tenant->mail_port ?: 587 }}</dd>
                </div>

                <div>
                    <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Username') }}</dt>
                    <dd class="truncate font-mono">{{ $tenant->mail_username }}</dd>
                </div>
            </dl>

            <flux:text size="sm">
                {{ __('Set up by us. Write to us to have it changed.') }}
            </flux:text>

            <flux:input
                wire:model.live.debounce.500ms="reply_to_email"
                type="email"
                class="sm:max-w-96"
                :label="__('Reply address')"
                :placeholder="$this->tenant->contact_email"
                :description="__('Where an answer from the customer lands. Empty uses the contact email.')"
            />
        @else
            <flux:input
                wire:model.live.debounce.500ms="reply_to_email"
                type="email"
                class="sm:max-w-96"
                :label="__('Reply address')"
                :placeholder="$this->tenant->contact_email"
                :description="__('Where an answer from the customer lands. Empty uses the contact email.')"
            />
        @endif

        <div class="rounded-xl bg-zinc-50 p-4 dark:bg-zinc-800/60">
            <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                {{ __('Your emails will look like this') }}
            </p>

            <dl class="mt-2 space-y-1 font-mono text-sm">
                <div class="flex gap-2">
                    <dt class="text-zinc-500 dark:text-zinc-400">{{ __('From:') }}</dt>
                    <dd class="min-w-0 truncate">{{ $tenant->name }} &lt;{{ $this->preview['from'] }}&gt;</dd>
                </div>

                @if ($this->preview['afterTest'] !== null)
                    <div class="flex gap-2">
                        <dt class="text-zinc-500 dark:text-zinc-400">{{ __('After the test:') }}</dt>
                        <dd class="min-w-0 truncate">{{ $tenant->name }} &lt;{{ $this->preview['afterTest'] }}&gt;</dd>
                    </div>
                @endif

                <div class="flex gap-2">
                    <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Reply-To:') }}</dt>
                    <dd class="min-w-0 truncate">
                        {{ $this->preview['replyTo'] ?? __('none — answers go back to the sender') }}
                    </dd>
                </div>
            </dl>
        </div>

        <flux:separator />

        <flux:switch
            wire:model="notify_manager_on_booking"
            :label="__('Send me a copy of every new booking')"
            :description="__('Only when the booking goes to one of your operators.')"
        />

        <flux:input
            wire:model="reminder_hours_before"
            type="number"
            min="1"
            max="336"
            class="sm:max-w-64"
            :label="__('Remind the customer this many hours before')"
            :description="__('Leave empty to send no reminders. Only confirmed bookings are reminded.')"
        />

        <div class="flex justify-end">
            <flux:button variant="primary" type="submit">{{ __('Save settings') }}</flux:button>
        </div>
    </form>

    @if ($this->hasMailerSettings)
        <flux:separator :text="__('Test it')" />

        <form wire:submit="sendTest" class="flex flex-col gap-3 sm:flex-row sm:items-end">
            <flux:input
                class="flex-1"
                wire:model="test_recipient"
                type="email"
                :label="__('Send a test email to')"
            />

            <flux:button variant="filled" type="submit" icon="paper-airplane">
                {{ __('Send test') }}
            </flux:button>
        </form>
    @endif
</div>
