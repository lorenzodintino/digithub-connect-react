import { Passkeys, UserCancelledError } from '@laravel/passkeys';
import { Fingerprint } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';

/**
 * Sign in, or confirm the password, with a passkey. Nothing is drawn where
 * the browser cannot do it.
 */
export function PasskeyVerify({
    options,
    submit,
    label,
    loadingLabel,
    separator,
}: {
    options: string;
    submit: string;
    label: string;
    loadingLabel: string;
    separator: string;
}) {
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    if (!Passkeys.isSupported()) {
        return null;
    }

    async function verify(): Promise<void> {
        setLoading(true);
        setError(null);

        try {
            const response = await Passkeys.verify({ routes: { options, submit } });

            // A full visit: the session changed hands, and the page we land on
            // reads it from scratch.
            window.location.assign(response.redirect || '/dashboard');
        } catch (exception) {
            if (!(exception instanceof UserCancelledError)) {
                setError(exception instanceof Error ? exception.message : String(exception));
            }
        } finally {
            setLoading(false);
        }
    }

    return (
        <div>
            <div className="grid gap-2">
                <Button variant="outline" className="w-full" onClick={verify} disabled={loading}>
                    <Fingerprint />
                    {loading ? loadingLabel : label}
                </Button>
                {error && <p className="text-center text-sm text-destructive">{error}</p>}
            </div>

            <div className="relative my-6">
                <div className="absolute inset-0 flex items-center">
                    <div className="w-full border-t" />
                </div>
                <div className="relative flex justify-center text-xs uppercase">
                    <span className="bg-background px-2 text-muted-foreground">{separator}</span>
                </div>
            </div>
        </div>
    );
}
