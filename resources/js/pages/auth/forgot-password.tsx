import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { AuthHeader, AuthStatus } from '@/components/auth-header';
import { FormField } from '@/components/form-field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { __ } from '@/lib/i18n';
import { login } from '@/routes';
import { email } from '@/routes/password';

export default function ForgotPassword({ status }: { status: string | null }) {
    const form = useForm({ email: '' });

    function submit(event: FormEvent): void {
        event.preventDefault();
        form.post(email.url());
    }

    return (
        <>
            <Head title={__('Forgot password')} />

            <AuthHeader title={__('Forgot password')} description={__('Enter your email to receive a password reset link')} />

            <AuthStatus status={status} />

            <form onSubmit={submit} className="flex flex-col gap-6">
                <FormField id="email" label={__('Email address')} error={form.errors.email}>
                    <Input
                        id="email"
                        type="email"
                        required
                        autoFocus
                        placeholder="email@example.com"
                        value={form.data.email}
                        onChange={(event) => form.setData('email', event.target.value)}
                    />
                </FormField>

                <Button type="submit" className="w-full" disabled={form.processing} data-test="email-password-reset-link-button">
                    {__('Email password reset link')}
                </Button>
            </form>

            <div className="text-center text-sm text-muted-foreground">
                {__('Or, return to')}{' '}
                <Link href={login.url()} className="text-foreground underline-offset-4 hover:underline">
                    {__('log in')}
                </Link>
            </div>
        </>
    );
}
