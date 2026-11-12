import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { AuthHeader } from '@/components/auth-header';
import { FormField } from '@/components/form-field';
import { PasswordInput } from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { __ } from '@/lib/i18n';
import { update } from '@/routes/password';

export default function ResetPassword({ token, email, passwordRules }: { token: string; email: string; passwordRules: string }) {
    const form = useForm({ token, email, password: '', password_confirmation: '' });
    // Password managers read the rules from this attribute when they suggest one.
    const rules = { passwordrules: passwordRules } as Record<string, string>;

    function submit(event: FormEvent): void {
        event.preventDefault();
        form.post(update.url(), { onFinish: () => form.reset('password', 'password_confirmation') });
    }

    return (
        <>
            <Head title={__('Reset password')} />

            <AuthHeader title={__('Reset password')} description={__('Please enter your new password below')} />

            <form onSubmit={submit} className="flex flex-col gap-6">
                <FormField id="email" label={__('Email')} error={form.errors.email}>
                    <Input id="email" type="email" required autoComplete="email" value={form.data.email} onChange={(event) => form.setData('email', event.target.value)} />
                </FormField>

                <FormField id="password" label={__('Password')} error={form.errors.password}>
                    <PasswordInput
                        id="password"
                        required
                        autoComplete="new-password"
                        placeholder={__('Password')}
                        value={form.data.password}
                        onChange={(event) => form.setData('password', event.target.value)}
                        {...rules}
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
                        {...rules}
                    />
                </FormField>

                <Button type="submit" className="w-full" disabled={form.processing} data-test="reset-password-button">
                    {__('Reset password')}
                </Button>
            </form>
        </>
    );
}
