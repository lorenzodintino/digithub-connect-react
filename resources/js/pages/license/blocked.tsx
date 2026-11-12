import { Head, router } from '@inertiajs/react';
import { Download, LogOut, RefreshCw, ShieldAlert } from 'lucide-react';
import { CopyableInput } from '@/components/copyable-input';
import { FormField } from '@/components/form-field';
import { Button } from '@/components/ui/button';
import { __ } from '@/lib/i18n';
import { logout } from '@/routes';
import { recheck } from '@/routes/license';
import { exportMethod } from '@/routes/app/portal';

interface Props {
    portal: string;
    title: string;
    message: string;
    isManager: boolean;
    tenantId: string | null;
    supportEmail: string | null;
}

/**
 * Where a portal whose licence is refused ends up: why, what to pass on and
 * what can still be done. The manager keeps the export of the portal's data;
 * an operator is only told to ask the manager.
 */
export default function Blocked({ portal, title, message, isManager, tenantId, supportEmail }: Props) {
    return (
        <>
            <Head title={title} />

            <div className="flex flex-col gap-5 rounded-xl border p-6">
                <div className="flex flex-col items-center gap-2 text-center">
                    <span className="flex size-11 items-center justify-center rounded-full bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300">
                        <ShieldAlert className="size-6" />
                    </span>
                    <h1 className="text-xl font-semibold">{title}</h1>
                    <p className="text-sm text-muted-foreground">{portal}</p>
                </div>

                <p className="text-center">{message}</p>

                {isManager && tenantId && (
                    <FormField id="license-tenant-id" label={__('Portal id')} description={__('Give this id to the platform administrator: it is what the License Manager knows your portal by.')}>
                        <CopyableInput id="license-tenant-id" value={tenantId} className="[&_input]:font-mono [&_input]:font-semibold" />
                    </FormField>
                )}

                {isManager && supportEmail && (
                    <p className="text-center text-sm text-muted-foreground">
                        {__('Contact the platform at')} <a href={`mailto:${supportEmail}`} className="font-medium text-foreground underline">{supportEmail}</a>
                    </p>
                )}

                <div className="flex flex-col gap-2">
                    {isManager && (
                        <>
                            <Button onClick={() => router.post(recheck.url())}>
                                <RefreshCw />
                                {__('Check the license again')}
                            </Button>
                            <Button variant="secondary" asChild>
                                <a href={exportMethod.url()}>
                                    <Download />
                                    {__('Export my data')}
                                </a>
                            </Button>
                        </>
                    )}
                    <Button variant="ghost" onClick={() => router.post(logout.url())} data-test="logout-button">
                        <LogOut />
                        {__('Log out')}
                    </Button>
                </div>
            </div>
        </>
    );
}
