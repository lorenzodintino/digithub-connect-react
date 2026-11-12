import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { AuthHeader, AuthStatus } from '@/components/auth-header';
import { FormField } from '@/components/form-field';
import { PasskeyVerify } from '@/components/passkey-verify';
import { PasswordInput } from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { __ } from '@/lib/i18n';
import * as passkey from '@/routes/passkey';
import { store } from '@/routes/password/confirm';

export default function ConfirmPassword({ status }: { status: string | null }) {
    const form = useForm({ password: '' });

    function submit(event: FormEvent): void {
        event.preventDefault();
        form.post(store.url(), { onFinish: () => form.reset('password') });
    }

    return (
        <>
            <Head title={__('Confirm password')} />

            <AuthHeader title={__('Confirm password')} description={__('This is a secure area of the application. Please confirm your password before continuing.')} />

            <AuthStatus status={status} />

            <PasskeyVerify
                options={passkey.confirmOptions.url()}
                submit={passkey.confirm.url()}
                label={__('Confirm with passkey')}
                loadingLabel={__('Confirming...')}
                separator={__('Or confirm with password')}
            />

            <form onSubmit={submit} className="flex flex-col gap-6">
                <FormField id="password" label={__('Password')} error={form.errors.password}>
                    <PasswordInput
                        id="password"
                        required
                        autoFocus
                        autoComplete="current-password"
                        placeholder={__('Password')}
                        value={form.data.password}
                        onChange={(event) => form.setData('password', event.target.value)}
                    />
                </FormField>

                <Button type="submit" className="w-full" disabled={form.processing} data-test="confirm-password-button">
                    {__('Confirm')}
                </Button>
            </form>
        </>
    );
}
