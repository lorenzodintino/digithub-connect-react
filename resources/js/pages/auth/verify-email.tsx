import { Head, router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { __ } from '@/lib/i18n';
import { logout } from '@/routes';
import { send } from '@/routes/verification';

export default function VerifyEmail({ status }: { status: string | null }) {
    return (
        <>
            <Head title={__('Email verification')} />

            <p className="mt-4 text-center text-sm text-muted-foreground">{__('Please verify your email address by clicking on the link we just emailed to you.')}</p>

            {status === 'verification-link-sent' && (
                <p className="text-center text-sm font-medium text-green-600 dark:text-green-400">
                    {__('A new verification link has been sent to the email address you provided during registration.')}
                </p>
            )}

            <div className="flex flex-col items-center gap-3">
                <Button className="w-full" onClick={() => router.post(send.url())}>
                    {__('Resend verification email')}
                </Button>

                <Button variant="ghost" size="sm" onClick={() => router.post(logout.url())} data-test="logout-button">
                    {__('Log out')}
                </Button>
            </div>
        </>
    );
}
