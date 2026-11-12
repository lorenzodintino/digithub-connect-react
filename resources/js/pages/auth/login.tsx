import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { AuthHeader, AuthStatus } from '@/components/auth-header';
import { FormField } from '@/components/form-field';
import { PasskeyVerify } from '@/components/passkey-verify';
import { PasswordInput } from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { __ } from '@/lib/i18n';
import { store } from '@/routes/login';
import * as passkey from '@/routes/passkey';
import { request } from '@/routes/password';

export default function Login({ status, canResetPassword, canRegister }: { status: string | null; canResetPassword: boolean; canRegister: boolean }) {
    const form = useForm({ email: '', password: '', remember: false });

    function submit(event: FormEvent): void {
        event.preventDefault();
        form.post(store.url(), { onFinish: () => form.reset('password') });
    }

    return (
        <>
            <Head title={__('Log in')} />

            <AuthHeader title={__('Log in to your account')} description={__('Enter your email and password below to log in')} />

            <AuthStatus status={status} />

            <PasskeyVerify
                options={passkey.loginOptions.url()}
                submit={passkey.login.url()}
                label={__('Sign in with a passkey')}
                loadingLabel={__('Authenticating...')}
                separator={__('Or continue with email')}
            />

            <form onSubmit={submit} className="flex flex-col gap-6">
                <FormField id="email" label={__('Email address')} error={form.errors.email}>
                    <Input
                        id="email"
                        type="email"
                        required
                        autoFocus
                        autoComplete="email"
                        placeholder="email@example.com"
                        value={form.data.email}
                        onChange={(event) => form.setData('email', event.target.value)}
                    />
                </FormField>

                <div className="relative">
                    <FormField id="password" label={__('Password')} error={form.errors.password}>
                        <PasswordInput
                            id="password"
                            required
                            autoComplete="current-password"
                            placeholder={__('Password')}
                            value={form.data.password}
                            onChange={(event) => form.setData('password', event.target.value)}
                        />
                    </FormField>

                    {canResetPassword && (
                        <Link href={request.url()} className="absolute end-0 top-0 text-sm underline-offset-4 hover:underline">
                            {__('Forgot your password?')}
                        </Link>
                    )}
                </div>

                <div className="flex items-center gap-2">
                    <Checkbox id="remember" checked={form.data.remember} onCheckedChange={(checked) => form.setData('remember', checked === true)} />
                    <Label htmlFor="remember">{__('Remember me')}</Label>
                </div>

                <Button type="submit" className="w-full" disabled={form.processing} data-test="login-button">
                    {__('Log in')}
                </Button>
            </form>

            {canRegister && (
                <div className="text-center text-sm text-muted-foreground">
                    {__("Don't have an account?")}{' '}
                    <Link href="/register" className="text-foreground underline-offset-4 hover:underline">
                        {__('Sign up')}
                    </Link>
                </div>
            )}
        </>
    );
}
