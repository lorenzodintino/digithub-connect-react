import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { CopyableInput } from '@/components/copyable-input';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { __ } from '@/lib/i18n';

/**
 * A generated password, shown once: the server flashes `credentials` after
 * creating an account or issuing a new password, and flash data never lands
 * in the browser history.
 */
export function CredentialsDialog({ title, description }: { title: string; description: string }) {
    const [credentials, setCredentials] = useState<{ email: string; password: string } | null>(null);

    useEffect(
        () =>
            router.on('flash', (event) => {
                if (event.detail.flash.credentials) {
                    setCredentials(event.detail.flash.credentials);
                }
            }),
        [],
    );

    return (
        <Dialog open={credentials !== null} onOpenChange={(open) => !open && setCredentials(null)}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>{description}</DialogDescription>
                </DialogHeader>

                {credentials && (
                    <div className="space-y-4">
                        <div className="grid gap-2">
                            <Label>{__('Email')}</Label>
                            <CopyableInput value={credentials.email} />
                        </div>
                        <div className="grid gap-2">
                            <Label>{__('Temporary password')}</Label>
                            <CopyableInput value={credentials.password} />
                        </div>
                    </div>
                )}

                <DialogFooter>
                    <Button onClick={() => setCredentials(null)}>{__('Done')}</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
