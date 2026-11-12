import { Check, Copy } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { __ } from '@/lib/i18n';
import { cn } from '@/lib/utils';

/** A read-only field with a copy button. */
export function CopyableInput({ id, value, className }: { id?: string; value: string; className?: string }) {
    const [copied, setCopied] = useState(false);

    async function copy(): Promise<void> {
        await navigator.clipboard.writeText(value);
        setCopied(true);
        window.setTimeout(() => setCopied(false), 2000);
    }

    return (
        <div className={cn('relative', className)}>
            <Input id={id} readOnly value={value} className="pe-10" onFocus={(event) => event.currentTarget.select()} />

            <Button
                type="button"
                variant="ghost"
                size="icon"
                className="absolute inset-y-0 end-0 h-full"
                onClick={copy}
                aria-label={copied ? __('Copied') : __('Copy to clipboard')}
                title={copied ? __('Copied') : __('Copy to clipboard')}
            >
                {copied ? <Check /> : <Copy />}
            </Button>
        </div>
    );
}
