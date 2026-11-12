import { Head, Link, router } from '@inertiajs/react';
import { BellOff, CircleCheck } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { __ } from '@/lib/i18n';
import type { PortalLayoutProps } from '@/types/portal';

interface Props {
    portal: PortalLayoutProps;
    service: string;
    left: boolean;
    leaveUrl: string;
}

/**
 * The way off a waiting list, behind a button rather than on arrival: mail
 * scanners and link previews fetch every address in a message.
 */
export default function WaitingList({ portal, service, left, leaveUrl }: Props) {
    return (
        <>
            <Head title={`${__('Waiting list')} · ${portal.name}`} />

            <main className="mx-auto w-full max-w-xl px-4 py-16 sm:px-6">
                <div className="space-y-4 rounded-2xl border bg-background p-8 text-center">
                    {left ? (
                        <>
                            <CircleCheck className="mx-auto size-10 text-emerald-500" />
                            <h1 className="text-lg font-semibold">{__('You are off the waiting list')}</h1>
                            <p className="text-muted-foreground">{__('We will not write to you again about :service.', { service })}</p>
                            <Button asChild>
                                <Link href={portal.home_url}>{__('Back to the booking page')}</Link>
                            </Button>
                        </>
                    ) : (
                        <>
                            <BellOff className="mx-auto size-10 text-muted-foreground" />
                            <h1 className="text-lg font-semibold">{__('Leave this waiting list?')}</h1>
                            <p className="text-muted-foreground">{__('You will stop hearing about places that open up for :service.', { service })}</p>
                            <div className="flex flex-wrap justify-center gap-2">
                                <Button onClick={() => router.delete(leaveUrl)}>{__('Take me off the list')}</Button>
                                <Button variant="ghost" asChild>
                                    <Link href={portal.home_url}>{__('Keep me on it')}</Link>
                                </Button>
                            </div>
                        </>
                    )}
                </div>
            </main>
        </>
    );
}
