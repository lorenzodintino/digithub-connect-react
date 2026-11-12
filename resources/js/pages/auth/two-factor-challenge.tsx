import { Head, useForm } from '@inertiajs/react';
import { type FormEvent, useState } from 'react';
import { AuthHeader } from '@/components/auth-header';
import { InputError } from '@/components/form-field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { __ } from '@/lib/i18n';
import { store } from '@/routes/two-factor/login';

/** The second step of a sign-in: the code of the app, or one of the recovery codes. */
export default function TwoFactorChallenge() {
    const form = useForm({ code: '', recovery_code: '' });
    const [recovery, setRecovery] = useState(false);
    const usingRecovery = recovery || Boolean(form.errors.recovery_code);

    function toggle(): void {
        form.reset();
        form.clearErrors();
        setRecovery(!usingRecovery);
    }

    function submit(event: FormEvent): void {
        event.preventDefault();
        form.transform((data) => (usingRecovery ? { recovery_code: data.recovery_code } : { code: data.code }));
        form.post(store.url(), { onError: () => form.reset() });
    }

    return (
        <>
            <Head title={__('Two-factor authentication')} />

            {usingRecovery ? (
                <AuthHeader title={__('Recovery code')} description={__('Please confirm access to your account by entering one of your emergency recovery codes.')} />
            ) : (
                <AuthHeader title={__('Authentication code')} description={__('Enter the authentication code provided by your authenticator application.')} />
            )}

            <form onSubmit={submit} className="space-y-5 text-center">
                {usingRecovery ? (
                    <div className="space-y-2">
                        <Input
                            key="recovery"
                            name="recovery_code"
                            required
                            autoFocus
                            autoComplete="one-time-code"
                            aria-label={__('Recovery code')}
                            value={form.data.recovery_code}
                            onChange={(event) => form.setData('recovery_code', event.target.value)}
                        />
                        <InputError message={form.errors.recovery_code} />
                    </div>
                ) : (
                    <div className="space-y-2">
                        <Input
                            key="code"
                            name="code"
                            required
                            autoFocus
                            inputMode="numeric"
                            pattern="[0-9]*"
                            maxLength={6}
                            autoComplete="one-time-code"
                            aria-label={__('Authentication code')}
                            className="mx-auto max-w-44 text-center text-lg tracking-[0.5em] tabular-nums"
                            value={form.data.code}
                            onChange={(event) => form.setData('code', event.target.value.replace(/\D/g, ''))}
                        />
                        <InputError message={form.errors.code} />
                    </div>
                )}

                <Button type="submit" className="w-full" disabled={form.processing}>
                    {__('Continue')}
                </Button>

                <div className="text-sm">
                    <span className="text-muted-foreground">{__('or you can')} </span>
                    <button type="button" onClick={toggle} className="font-medium underline">
                        {usingRecovery ? __('login using an authentication code') : __('login using a recovery code')}
                    </button>
                </div>
            </form>
        </>
    );
}
