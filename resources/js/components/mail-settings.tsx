import { useForm } from '@inertiajs/react';
import { CircleCheck, Info, Layers, Send, TriangleAlert } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { FormField, InputError } from '@/components/form-field';
import { PasswordInput } from '@/components/password-input';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Switch } from '@/components/ui/switch';
import { UpgradeRequest } from '@/components/upgrade-request';
import { __ } from '@/lib/i18n';

/** App\Support\MailSettingsData */
export interface MailSettings {
    state: 'failing' | 'plan' | 'own' | 'configured' | 'platform';
    canConfigure: boolean;
    usesOwnMailer: boolean;
    hasMailerSettings: boolean;
    failedAt: string | null;
    verifiedAt: string | null;
    sendingAs: string | null;
    error: string | null;
    errorHint: string | null;
    platformFrom: string;
    portalName: string;
    contactEmail: string | null;
    testRecipient: string;
    form: {
        mail_host: string;
        mail_port: string;
        mail_username: string;
        mail_password: string;
        mail_encryption: string;
        mail_from_address: string;
        reply_to_email: string;
        notify_manager_on_booking: boolean;
        reminder_hours_before: string;
    };
}

function domainOf(address: string): string {
    return address.split('@')[1] ?? '';
}

function ServerError({ mail }: { mail: MailSettings }) {
    if (!mail.error) {
        return null;
    }

    return (
        <>
            {mail.errorHint && <p className="mt-1">{mail.errorHint}</p>}
            <p className="mt-1 text-xs opacity-75">{mail.error}</p>
        </>
    );
}

/** What the portal's emails are doing right now, in words a manager reads. */
function StateCallout({ mail }: { mail: MailSettings }) {
    switch (mail.state) {
        case 'failing':
            return (
                <Alert variant="destructive">
                    <TriangleAlert />
                    <AlertTitle>{__('An email did not go out')}</AlertTitle>
                    <AlertDescription>
                        <p>
                            {__('Failed on :date, so the emails went back to leaving through the platform. Nothing is lost: they are going out under our address instead of yours.', {
                                date: mail.failedAt ?? '',
                            })}
                        </p>
                        <ServerError mail={mail} />
                    </AlertDescription>
                </Alert>
            );
        case 'plan':
            return (
                <Alert>
                    <Layers />
                    <AlertDescription>
                        {__('The plan of this portal does not include its own email server: the emails leave through the platform. The settings are kept for when the plan changes.')}
                    </AlertDescription>
                    <div className="col-start-2 mt-3">
                        <UpgradeRequest reason="smtp" />
                    </div>
                </Alert>
            );
        case 'own':
            return (
                <Alert className="border-emerald-300 text-emerald-900 dark:border-emerald-800 dark:text-emerald-100">
                    <CircleCheck />
                    <AlertDescription>{__('Sending as :address since the test of :date.', { address: mail.sendingAs ?? '', date: mail.verifiedAt ?? '' })}</AlertDescription>
                </Alert>
            );
        case 'configured':
            return (
                <Alert className="border-amber-300 text-amber-900 dark:border-amber-800 dark:text-amber-100">
                    <Send />
                    <AlertTitle>{__('Configured, not proved yet')}</AlertTitle>
                    <AlertDescription>
                        <p>{__('The emails keep leaving through the platform until a test message goes through this server.')}</p>
                        <ServerError mail={mail} />
                    </AlertDescription>
                </Alert>
            );
        default:
            return (
                <Alert>
                    <Info />
                    <AlertDescription>{__('Emails currently leave through the platform: your customers see your name, but the address is ours.')}</AlertDescription>
                </Alert>
            );
    }
}

function ReplyTo({ value, onChange, error, placeholder, className }: { value: string; onChange: (value: string) => void; error?: string; placeholder: string | null; className?: string }) {
    return (
        <FormField id="mail-reply-to" label={__('Reply address')} description={__('Where an answer from the customer lands. Empty uses the contact email.')} error={error} className={className}>
            <Input id="mail-reply-to" type="email" value={value} placeholder={placeholder ?? undefined} onChange={(event) => onChange(event.target.value)} />
        </FormField>
    );
}

/**
 * The "Email delivery" box: the manager keeps reply address, copies and
 * reminders; the server itself belongs to the platform administrator.
 */
export function MailSettingsPanel({ mail, updateUrl, testUrl }: { mail: MailSettings; updateUrl: string; testUrl: string }) {
    const form = useForm(mail.form);
    const test = useForm({ test_recipient: mail.testRecipient });
    const data = form.data;
    const testErrors = test.errors as Record<string, string | undefined>;

    function save(event: FormEvent): void {
        event.preventDefault();
        form.put(updateUrl, { preserveScroll: true, onSuccess: () => form.setData('mail_password', '') });
    }

    function sendTest(event: FormEvent): void {
        event.preventDefault();
        test.post(testUrl, { preserveScroll: true });
    }

    // How an email will look once sent: four fields decide two headers, and
    // showing the result explains that better than any wording.
    const own = data.mail_host !== '' && data.mail_username !== '' ? data.mail_from_address || data.mail_username : null;
    const sending = own !== null && mail.usesOwnMailer;
    const from = sending ? own : mail.platformFrom;
    const afterTest = sending ? null : own;
    const replyTo = data.reply_to_email || mail.contactEmail || null;
    const senderLooksForeign = data.mail_from_address !== '' && data.mail_username !== '' && domainOf(data.mail_from_address) !== domainOf(data.mail_username);

    let intro: ReactNode = __('Your booking emails are sent by the platform, with your name on them. Ask us if you want them to leave from your own address.');

    if (mail.canConfigure) {
        intro = __('Emails leave through the platform. A portal can send from its own server instead, once that server has proved it works.');
    }

    return (
        <section className="space-y-6 rounded-xl border p-5">
            <div>
                <h2 className="text-lg font-semibold">{__('Email delivery')}</h2>
                <p className="mt-1 text-muted-foreground">{intro}</p>
            </div>

            <StateCallout mail={mail} />

            <form onSubmit={save} className="space-y-4">
                {mail.canConfigure ? (
                    <>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <FormField id="mail-host" label={__('SMTP server')} description={__('The outgoing server of your provider. Not your email address.')} error={form.errors.mail_host ?? testErrors.mail_host}>
                                <Input id="mail-host" value={data.mail_host} placeholder="smtp.example.com" onChange={(event) => form.setData('mail_host', event.target.value)} />
                            </FormField>
                            <FormField id="mail-port" label={__('Port')} error={form.errors.mail_port}>
                                <Input id="mail-port" type="number" value={data.mail_port} placeholder="587" onChange={(event) => form.setData('mail_port', event.target.value)} />
                            </FormField>
                            <FormField id="mail-username" label={__('Username')} description={__('The full email address, not just the part before the @.')} error={form.errors.mail_username}>
                                <Input id="mail-username" autoComplete="off" value={data.mail_username} onChange={(event) => form.setData('mail_username', event.target.value)} />
                            </FormField>
                            <FormField
                                id="mail-password"
                                label={__('Password')}
                                description={mail.hasMailerSettings ? __('Leave empty to keep the current one.') : undefined}
                                error={form.errors.mail_password}
                            >
                                <PasswordInput
                                    id="mail-password"
                                    autoComplete="new-password"
                                    value={data.mail_password}
                                    placeholder={mail.hasMailerSettings ? __('Unchanged') : ''}
                                    onChange={(event) => form.setData('mail_password', event.target.value)}
                                />
                            </FormField>
                            <FormField id="mail-encryption" label={__('Encryption')} description={__('Port 465 always uses SSL, whatever is chosen here.')} error={form.errors.mail_encryption}>
                                <Select value={data.mail_encryption} onValueChange={(value) => form.setData('mail_encryption', value)}>
                                    <SelectTrigger id="mail-encryption" className="w-full">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="tls">STARTTLS</SelectItem>
                                        <SelectItem value="ssl">SSL</SelectItem>
                                        <SelectItem value="none">{__('None')}</SelectItem>
                                    </SelectContent>
                                </Select>
                            </FormField>
                            <FormField
                                id="mail-from"
                                label={__('Sender address')}
                                description={__('Who the customer sees. It must be an address the SMTP account is allowed to send from — usually the username itself.')}
                                error={form.errors.mail_from_address}
                            >
                                <Input id="mail-from" type="email" value={data.mail_from_address} placeholder={data.mail_username} onChange={(event) => form.setData('mail_from_address', event.target.value)} />
                            </FormField>
                            <ReplyTo value={data.reply_to_email} onChange={(value) => form.setData('reply_to_email', value)} error={form.errors.reply_to_email} placeholder={mail.contactEmail} />
                        </div>

                        {senderLooksForeign && (
                            <Alert className="border-amber-300 text-amber-900 dark:border-amber-800 dark:text-amber-100">
                                <TriangleAlert />
                                <AlertTitle>{__('The sender belongs to another domain')}</AlertTitle>
                                <AlertDescription>
                                    {__('You authenticate as :username but send as :sender. Most servers refuse that, and the error they return explains little. Use the same address, or leave the field empty.', {
                                        username: data.mail_username,
                                        sender: data.mail_from_address,
                                    })}
                                </AlertDescription>
                            </Alert>
                        )}
                    </>
                ) : (
                    <>
                        {mail.hasMailerSettings && (
                            <>
                                <dl className="grid gap-x-4 gap-y-2 text-sm sm:grid-cols-2">
                                    <div>
                                        <dt className="text-muted-foreground">{__('SMTP server')}</dt>
                                        <dd className="truncate font-mono">
                                            {data.mail_host}:{data.mail_port || 587}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="text-muted-foreground">{__('Username')}</dt>
                                        <dd className="truncate font-mono">{data.mail_username}</dd>
                                    </div>
                                </dl>
                                <p className="text-sm text-muted-foreground">{__('Set up by us. Write to us to have it changed.')}</p>
                            </>
                        )}
                        <ReplyTo
                            value={data.reply_to_email}
                            onChange={(value) => form.setData('reply_to_email', value)}
                            error={form.errors.reply_to_email}
                            placeholder={mail.contactEmail}
                            className="sm:max-w-96"
                        />
                    </>
                )}

                <div className="rounded-xl bg-muted p-4">
                    <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">{__('Your emails will look like this')}</p>
                    <dl className="mt-2 space-y-1 font-mono text-sm">
                        <div className="flex gap-2">
                            <dt className="text-muted-foreground">{__('From:')}</dt>
                            <dd className="min-w-0 truncate">
                                {mail.portalName} &lt;{from}&gt;
                            </dd>
                        </div>
                        {afterTest !== null && (
                            <div className="flex gap-2">
                                <dt className="text-muted-foreground">{__('After the test:')}</dt>
                                <dd className="min-w-0 truncate">
                                    {mail.portalName} &lt;{afterTest}&gt;
                                </dd>
                            </div>
                        )}
                        <div className="flex gap-2">
                            <dt className="text-muted-foreground">{__('Reply-To:')}</dt>
                            <dd className="min-w-0 truncate">{replyTo ?? __('none — answers go back to the sender')}</dd>
                        </div>
                    </dl>
                </div>

                <Separator />

                <div className="flex items-start gap-3">
                    <Switch
                        id="mail-notify"
                        checked={data.notify_manager_on_booking}
                        onCheckedChange={(checked) => form.setData('notify_manager_on_booking', checked)}
                        className="mt-0.5"
                    />
                    <div className="grid gap-1">
                        <Label htmlFor="mail-notify">{__('Send me a copy of every new booking')}</Label>
                        <p className="text-sm text-muted-foreground">{__('Only when the booking goes to one of your operators.')}</p>
                    </div>
                </div>

                <FormField
                    id="mail-reminder"
                    label={__('Remind the customer this many hours before')}
                    description={__('Leave empty to send no reminders. Only confirmed bookings are reminded.')}
                    error={form.errors.reminder_hours_before}
                    className="sm:max-w-64"
                >
                    <Input id="mail-reminder" type="number" min={1} max={336} value={data.reminder_hours_before} onChange={(event) => form.setData('reminder_hours_before', event.target.value)} />
                </FormField>

                <div className="flex justify-end">
                    <Button type="submit" disabled={form.processing}>
                        {__('Save settings')}
                    </Button>
                </div>
            </form>

            {mail.hasMailerSettings && (
                <>
                    <div className="flex items-center gap-3">
                        <Separator className="flex-1" />
                        <span className="text-sm text-muted-foreground">{__('Test it')}</span>
                        <Separator className="flex-1" />
                    </div>

                    <form onSubmit={sendTest} className="space-y-2">
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-end">
                            <FormField id="mail-test" label={__('Send a test email to')} error={test.errors.test_recipient} className="flex-1">
                                <Input id="mail-test" type="email" value={test.data.test_recipient} onChange={(event) => test.setData('test_recipient', event.target.value)} />
                            </FormField>
                            <Button type="submit" variant="secondary" disabled={test.processing}>
                                <Send />
                                {__('Send test')}
                            </Button>
                        </div>
                        {!mail.canConfigure && <InputError message={testErrors.mail_host} />}
                    </form>
                </>
            )}
        </section>
    );
}
