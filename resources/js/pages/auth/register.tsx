import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { AuthHeader } from '@/components/auth-header';
import { FormField } from '@/components/form-field';
import { PasswordInput } from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { __ } from '@/lib/i18n';
import { login } from '@/routes';

/**
 * Self-registration, off on this platform (`Features::registration()` is
 * commented out): accounts come from the administrator and the managers.
 * Kept so the feature still works if it is ever switched back on.
 */
export default function Register() {
    const form = useForm({ name: '', email: '', password: '', password_confirmation: '' });

    function submit(event: FormEvent): void {
        event.preventDefault();
        form.post('/register', { onFinish: () => form.reset('password', 'password_confirmation') });
    }

    return (
        <>
            <Head title={__('Register')} />

            <AuthHeader title={__('Create an account')} description={__('Enter your details below to create your account')} />

            <form onSubmit={submit} className="flex flex-col gap-6">
                <FormField id="name" label={__('Name')} error={form.errors.name}>
                    <Input
                        id="name"
                        required
                        autoFocus
                        autoComplete="name"
                        placeholder={__('Full name')}
                        value={form.data.name}
                        onChange={(event) => form.setData('name', event.target.value)}
                    />
                </FormField>

                <FormField id="email" label={__('Email address')} error={form.errors.email}>
                    <Input
                        id="email"
                        type="email"
                        required
                        autoComplete="email"
                        placeholder="email@example.com"
                        value={form.data.email}
                        onChange={(event) => form.setData('email', event.target.value)}
                    />
                </FormField>

                <FormField id="password" label={__('Password')} error={form.errors.password}>
                    <PasswordInput
                        id="password"
                        required
                        autoComplete="new-password"
                        placeholder={__('Password')}
                        value={form.data.password}
                        onChange={(event) => form.setData('password', event.target.value)}
                    />
                </FormField>

                <FormField id="password_confirmation" label={__('Confirm password')} error={form.errors.password_confirmation}>
                    <PasswordInput
                        id="password_confirmation"
                        required
                        autoComplete="new-password"
                        placeholder={__('Confirm password')}
                        value={form.data.password_confirmation}
                        onChange={(event) => form.setData('password_confirmation', event.target.value)}
                    />
                </FormField>

                <Button type="submit" className="w-full" disabled={form.processing} data-test="register-user-button">
                    {__('Create account')}
                </Button>
            </form>

            <div className="text-center text-sm text-muted-foreground">
                {__('Already have an account?')}{' '}
                <Link href={login.url()} className="text-foreground underline-offset-4 hover:underline">
                    {__('Log in')}
                </Link>
            </div>
        </>
    );
}
