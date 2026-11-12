import { Head, router, useForm } from '@inertiajs/react';
import { Passkeys } from '@laravel/passkeys';
import { Check, Copy, Eye, EyeOff, KeyRound, LoaderCircle, Lock, Plus, QrCode, RefreshCw, Trash2 } from 'lucide-react';
import { type FormEvent, type ReactNode, useEffect, useRef, useState } from 'react';
import { FormField, InputError } from '@/components/form-field';
import { PasswordInput } from '@/components/password-input';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import SettingsLayout from '@/layouts/settings-layout';
import { __ } from '@/lib/i18n';
import { destroy as destroyPasskey } from '@/routes/passkey';
import * as twoFactorRoutes from '@/routes/two-factor';
import { update as updatePassword } from '@/routes/user-password';

interface Passkey {
    id: number;
    name: string;
    authenticator: string | null;
    created_at_diff: string;
    last_used_at_diff: string | null;
}

interface Props {
    twoFactor: { enabled: boolean; requiresConfirmation: boolean } | null;
    passkeys: Passkey[] | null;
    passwordRules: string;
}

/** Fortify's JSON endpoints: QR code, setup key and recovery codes. */
async function fetchJson<T>(url: string): Promise<T> {
    const response = await fetch(url, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });

    if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
    }

    return response.json() as Promise<T>;
}

function Section({ heading, subheading, children }: { heading: string; subheading: string; children: ReactNode }) {
    return (
        <section className="mt-12">
            <h3 className="font-medium">{heading}</h3>
            <p className="text-sm text-muted-foreground">{subheading}</p>
            <div className="mt-6 space-y-6 text-sm">{children}</div>
        </section>
    );
}

function PasswordForm({ passwordRules }: { passwordRules: string }) {
    const form = useForm({ current_password: '', password: '', password_confirmation: '' });

    function save(event: FormEvent): void {
        event.preventDefault();

        form.put(updatePassword.url(), {
            preserveScroll: true,
            onFinish: () => form.reset(),
        });
    }

    return (
        <form onSubmit={save} className="mt-6 space-y-6">
            <FormField id="current_password" label={__('Current password')} error={form.errors.current_password}>
                <PasswordInput
                    id="current_password"
                    value={form.data.current_password}
                    onChange={(event) => form.setData('current_password', event.target.value)}
                    required
                    autoComplete="current-password"
                />
            </FormField>

            <FormField id="password" label={__('New password')} error={form.errors.password}>
                <PasswordInput
                    id="password"
                    value={form.data.password}
                    onChange={(event) => form.setData('password', event.target.value)}
                    required
                    autoComplete="new-password"
                    {...({ passwordrules: passwordRules } as Record<string, string>)}
                />
            </FormField>

            <FormField id="password_confirmation" label={__('Confirm password')} error={form.errors.password_confirmation}>
                <PasswordInput
                    id="password_confirmation"
                    value={form.data.password_confirmation}
                    onChange={(event) => form.setData('password_confirmation', event.target.value)}
                    required
                    autoComplete="new-password"
                    {...({ passwordrules: passwordRules } as Record<string, string>)}
                />
            </FormField>

            <Button type="submit" disabled={form.processing} data-test="update-password-button">
                {__('Save')}
            </Button>
        </form>
    );
}

function RecoveryCodes() {
    const [visible, setVisible] = useState(false);
    const [codes, setCodes] = useState<string[]>([]);
    const [failed, setFailed] = useState(false);

    async function load(): Promise<void> {
        try {
            setCodes(await fetchJson<string[]>(twoFactorRoutes.recoveryCodes.url()));
            setFailed(false);
        } catch {
            setFailed(true);
        }
    }

    function toggle(): void {
        if (!visible && codes.length === 0) {
            void load();
        }

        setVisible(!visible);
    }

    function regenerate(): void {
        router.post(twoFactorRoutes.regenerateRecoveryCodes.url(), {}, { preserveScroll: true, onSuccess: () => void load() });
    }

    return (
        <div className="space-y-6 rounded-xl border py-6 shadow-xs">
            <div className="space-y-2 px-6">
                <div className="flex items-center gap-2">
                    <Lock className="size-4" />
                    <h4 className="text-lg font-semibold">{__('2FA recovery codes')}</h4>
                </div>
                <p className="text-muted-foreground">
                    {__('Recovery codes let you regain access if you lose your 2FA device. Store them in a secure password manager.')}
                </p>
            </div>

            <div className="px-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <Button onClick={toggle} aria-expanded={visible} aria-controls="recovery-codes-section">
                        {visible ? <EyeOff /> : <Eye />}
                        {visible ? __('Hide recovery codes') : __('View recovery codes')}
                    </Button>

                    {visible && codes.length > 0 && (
                        <Button variant="secondary" onClick={regenerate}>
                            <RefreshCw />
                            {__('Regenerate codes')}
                        </Button>
                    )}
                </div>

                {visible && (
                    <div id="recovery-codes-section" className="mt-3 space-y-3">
                        {failed && <InputError message={__('Failed to load recovery codes')} />}

                        {codes.length > 0 && (
                            <>
                                <div role="list" aria-label={__('Recovery codes')} className="grid gap-1 rounded-lg bg-muted p-4 font-mono text-sm">
                                    {codes.map((code) => (
                                        <div key={code} role="listitem" className="select-text">
                                            {code}
                                        </div>
                                    ))}
                                </div>
                                <p className="text-xs text-muted-foreground">
                                    {__('Each recovery code can be used once to access your account and will be removed after use. If you need more, click Regenerate codes above.')}
                                </p>
                            </>
                        )}
                    </div>
                )}
            </div>
        </div>
    );
}

/**
 * Fortify enables two-factor first and confirms it with a code afterwards:
 * the dialog shows the QR code, then asks for the code.
 */
function TwoFactorSetup({ open, onClose, requiresConfirmation }: { open: boolean; onClose: () => void; requiresConfirmation: boolean }) {
    const [qrCodeSvg, setQrCodeSvg] = useState('');
    const [setupKey, setSetupKey] = useState('');
    const [failed, setFailed] = useState(false);
    const [verifying, setVerifying] = useState(false);
    const [copied, setCopied] = useState(false);
    const form = useForm({ code: '' });

    useEffect(() => {
        if (!open) {
            return;
        }

        Promise.all([
            fetchJson<{ svg: string }>(twoFactorRoutes.qrCode.url()),
            fetchJson<{ secretKey: string }>(twoFactorRoutes.secretKey.url()),
        ])
            .then(([qr, secret]) => {
                setQrCodeSvg(qr.svg);
                setSetupKey(secret.secretKey);
            })
            .catch(() => setFailed(true));
    }, [open]);

    function close(): void {
        setQrCodeSvg('');
        setSetupKey('');
        setFailed(false);
        setVerifying(false);
        form.reset();
        form.clearErrors();
        onClose();
    }

    function next(): void {
        if (requiresConfirmation) {
            setVerifying(true);

            return;
        }

        router.reload({ only: ['twoFactor'] });
        close();
    }

    function confirm(event: FormEvent): void {
        event.preventDefault();

        form.post(twoFactorRoutes.confirm.url(), {
            errorBag: 'confirmTwoFactorAuthentication',
            preserveScroll: true,
            onSuccess: () => close(),
        });
    }

    async function copy(): Promise<void> {
        await navigator.clipboard.writeText(setupKey);
        setCopied(true);
        window.setTimeout(() => setCopied(false), 1500);
    }

    return (
        <Dialog open={open} onOpenChange={(isOpen) => !isOpen && close()}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader className="items-center text-center">
                    <div className="mb-2 rounded-full border bg-muted p-3">
                        <QrCode className="size-6" />
                    </div>
                    <DialogTitle>{verifying ? __('Verify authentication code') : __('Enable two-factor authentication')}</DialogTitle>
                    <DialogDescription className="text-center">
                        {verifying
                            ? __('Enter the 6-digit code from your authenticator app.')
                            : __('To finish enabling two-factor authentication, scan the QR code or enter the setup key in your authenticator app.')}
                    </DialogDescription>
                </DialogHeader>

                {verifying ? (
                    <form onSubmit={confirm} className="space-y-6">
                        <div className="space-y-2">
                            <Input
                                value={form.data.code}
                                onChange={(event) => form.setData('code', event.target.value.replace(/\D/g, '').slice(0, 6))}
                                inputMode="numeric"
                                autoComplete="one-time-code"
                                maxLength={6}
                                autoFocus
                                aria-label={__('Verify authentication code')}
                                className="text-center font-mono text-lg tracking-[0.5em]"
                            />
                            <InputError message={form.errors.code} className="text-center" />
                        </div>

                        <div className="flex gap-3">
                            <Button type="button" variant="outline" className="flex-1" onClick={() => setVerifying(false)}>
                                {__('Back')}
                            </Button>
                            <Button type="submit" className="flex-1" disabled={form.processing || form.data.code.length < 6}>
                                {__('Confirm')}
                            </Button>
                        </div>
                    </form>
                ) : (
                    <div className="space-y-6">
                        {failed && <InputError message={__('Failed to fetch setup data.')} className="text-center" />}

                        <div className="flex justify-center">
                            <div className="relative flex aspect-square w-64 items-center justify-center overflow-hidden rounded-lg border">
                                {qrCodeSvg ? (
                                    <div className="rounded bg-white p-3 dark:invert" dangerouslySetInnerHTML={{ __html: qrCodeSvg }} />
                                ) : (
                                    <LoaderCircle className="size-6 animate-spin text-muted-foreground" />
                                )}
                            </div>
                        </div>

                        <Button className="w-full" disabled={failed || !qrCodeSvg} onClick={next}>
                            {__('Continue')}
                        </Button>

                        <div className="space-y-4">
                            <div className="relative flex items-center justify-center">
                                <div className="absolute inset-x-0 top-1/2 h-px bg-border" />
                                <span className="relative bg-background px-2 text-sm text-muted-foreground">{__('or, enter the code manually')}</span>
                            </div>

                            <div className="flex items-stretch rounded-xl border">
                                {setupKey ? (
                                    <>
                                        <input readOnly value={setupKey} className="w-full bg-transparent p-3 outline-none" aria-label={__('or, enter the code manually')} />
                                        <button
                                            type="button"
                                            onClick={copy}
                                            className="border-s px-3"
                                            aria-label={copied ? __('Copied') : __('Copy to clipboard')}
                                        >
                                            {copied ? <Check className="size-4 text-green-500" /> : <Copy className="size-4" />}
                                        </button>
                                    </>
                                ) : (
                                    <div className="flex w-full justify-center p-3">
                                        <LoaderCircle className="size-4 animate-spin text-muted-foreground" />
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>
                )}
            </DialogContent>
        </Dialog>
    );
}

function TwoFactorSection({ enabled, requiresConfirmation }: { enabled: boolean; requiresConfirmation: boolean }) {
    const [settingUp, setSettingUp] = useState(false);
    const [processing, setProcessing] = useState(false);

    function enable(): void {
        router.post(twoFactorRoutes.enable.url(), {}, {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onSuccess: () => setSettingUp(true),
        });
    }

    function disable(): void {
        router.delete(twoFactorRoutes.disable.url(), { preserveScroll: true });
    }

    return (
        <Section heading={__('Two-factor authentication')} subheading={__('Manage your two-factor authentication settings')}>
            {enabled ? (
                <div className="space-y-4">
                    <p>
                        {__('You will be prompted for a secure, random pin during login, which you can retrieve from the TOTP-supported application on your phone.')}
                    </p>

                    <Button variant="destructive" onClick={disable}>
                        {__('Disable 2FA')}
                    </Button>

                    <RecoveryCodes />
                </div>
            ) : (
                <div className="space-y-4">
                    <p className="text-muted-foreground">
                        {__('When you enable two-factor authentication, you will be prompted for a secure pin during login. This pin can be retrieved from a TOTP-supported application on your phone.')}
                    </p>

                    <Button onClick={enable} disabled={processing}>
                        {__('Enable 2FA')}
                    </Button>
                </div>
            )}

            <TwoFactorSetup open={settingUp} onClose={() => setSettingUp(false)} requiresConfirmation={requiresConfirmation} />
        </Section>
    );
}

function defaultPasskeyName(): string {
    const agent = navigator.userAgent;

    const browser = [
        { pattern: /Edg|Edge/, name: 'Edge' },
        { pattern: /OPR|Opera|OPiOS/, name: 'Opera' },
        { pattern: /Firefox|FxiOS/, name: 'Firefox' },
        { pattern: /Chrome|CriOS/, name: 'Chrome' },
        { pattern: /Safari/, name: 'Safari' },
    ].find(({ pattern }) => pattern.test(agent))?.name;

    const system = [
        { pattern: /iPhone/, name: 'iPhone' },
        { pattern: /iPad|Macintosh(?=.*Mobile)/, name: 'iPad' },
        { pattern: /Android/, name: 'Android' },
        { pattern: /Mac/, name: 'Mac' },
        { pattern: /Windows/, name: 'Windows' },
    ].find(({ pattern }) => pattern.test(agent))?.name;

    return [browser, system].filter(Boolean).join(' on ');
}

function RegisterPasskey() {
    const [adding, setAdding] = useState(false);
    const [name, setName] = useState(defaultPasskeyName);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const input = useRef<HTMLInputElement>(null);

    useEffect(() => {
        if (adding) {
            input.current?.focus();
        }
    }, [adding]);

    if (!Passkeys.isSupported()) {
        return <p>{__('Passkeys are not supported in this browser.')}</p>;
    }

    async function register(): Promise<void> {
        if (!name.trim()) {
            return;
        }

        setLoading(true);
        setError(null);

        try {
            await Passkeys.register({ name });
            setName('');
            setAdding(false);
            router.reload({ only: ['passkeys'] });
        } catch (exception) {
            if ((exception as Error).constructor?.name !== 'UserCancelledError') {
                setError((exception as Error).message);
            }
        } finally {
            setLoading(false);
        }
    }

    function cancel(): void {
        setAdding(false);
        setName(defaultPasskeyName());
        setError(null);
    }

    if (!adding) {
        return (
            <Button onClick={() => setAdding(true)}>
                <Plus />
                {__('Add passkey')}
            </Button>
        );
    }

    return (
        <div className="space-y-4 rounded-lg border bg-muted/50 p-4">
            <FormField id="passkey-name" label={__('Passkey name')} description={__('Give this passkey a name to help you identify it later.')}>
                <Input
                    id="passkey-name"
                    ref={input}
                    value={name}
                    onChange={(event) => setName(event.target.value)}
                    placeholder={__('e.g., MacBook Pro, iPhone')}
                    onKeyDown={(event) => {
                        if (event.key === 'Enter') {
                            event.preventDefault();
                            void register();
                        }
                    }}
                />
            </FormField>

            <InputError message={error ?? undefined} />

            <div className="flex gap-2">
                <Button onClick={() => void register()} disabled={loading || !name.trim()}>
                    {loading ? __('Registering...') : __('Register passkey')}
                </Button>
                <Button variant="ghost" onClick={cancel}>
                    {__('Cancel')}
                </Button>
            </div>
        </div>
    );
}

function PasskeysSection({ passkeys }: { passkeys: Passkey[] }) {
    const [deleting, setDeleting] = useState<Passkey | null>(null);

    function remove(): void {
        if (deleting === null) {
            return;
        }

        router.delete(destroyPasskey.url(deleting.id), {
            preserveScroll: true,
            onFinish: () => setDeleting(null),
        });
    }

    return (
        <Section heading={__('Passkeys')} subheading={__('Manage your passkeys for passwordless sign-in')}>
            <div className="overflow-hidden rounded-lg border">
                {passkeys.length === 0 ? (
                    <div className="p-8 text-center">
                        <div className="mx-auto mb-4 flex size-14 items-center justify-center rounded-2xl bg-muted">
                            <KeyRound className="size-7 text-muted-foreground" />
                        </div>
                        <p className="font-medium">{__('No passkeys yet')}</p>
                        <p className="mt-1 text-muted-foreground">{__('Add a passkey to sign in without a password')}</p>
                    </div>
                ) : (
                    <ul className="divide-y">
                        {passkeys.map((passkey) => (
                            <li key={passkey.id} className="flex items-center justify-between p-4">
                                <div className="flex items-center gap-4">
                                    <div className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-muted">
                                        <KeyRound className="size-5 text-muted-foreground" />
                                    </div>
                                    <div className="space-y-1">
                                        <div className="flex items-center gap-2.5">
                                            <p className="font-medium tracking-tight">{passkey.name}</p>
                                            {passkey.authenticator && <Badge variant="secondary">{passkey.authenticator}</Badge>}
                                        </div>
                                        <p className="text-xs text-muted-foreground">
                                            {__('Added :time', { time: passkey.created_at_diff })}
                                            {passkey.last_used_at_diff && (
                                                <>
                                                    <span className="mx-1 opacity-50">/</span>
                                                    {__('Last used :time', { time: passkey.last_used_at_diff })}
                                                </>
                                            )}
                                        </p>
                                    </div>
                                </div>

                                <Button
                                    variant="ghost"
                                    size="icon"
                                    className="text-destructive hover:bg-destructive/10 hover:text-destructive"
                                    aria-label={__('Remove passkey')}
                                    onClick={() => setDeleting(passkey)}
                                >
                                    <Trash2 />
                                </Button>
                            </li>
                        ))}
                    </ul>
                )}
            </div>

            <RegisterPasskey />

            <Dialog open={deleting !== null} onOpenChange={(open) => !open && setDeleting(null)}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>{__('Remove passkey')}</DialogTitle>
                        <DialogDescription>
                            {__('Are you sure you want to remove the passkey ":name"? You will no longer be able to use it to sign in.', { name: deleting?.name ?? '' })}
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setDeleting(null)}>
                            {__('Cancel')}
                        </Button>
                        <Button variant="destructive" onClick={remove}>
                            {__('Remove passkey')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </Section>
    );
}

export default function SecuritySettings({ twoFactor, passkeys, passwordRules }: Props) {
    return (
        <>
            <Head title={__('Security settings')} />

            <SettingsLayout heading={__('Update password')} subheading={__('Ensure your account is using a long, random password to stay secure')}>
                <PasswordForm passwordRules={passwordRules} />

                {twoFactor && <TwoFactorSection enabled={twoFactor.enabled} requiresConfirmation={twoFactor.requiresConfirmation} />}

                {passkeys && <PasskeysSection passkeys={passkeys} />}
            </SettingsLayout>
        </>
    );
}
