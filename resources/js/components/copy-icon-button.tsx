import { Check, Copy } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { __ } from '@/lib/i18n';

/** A small icon button that puts a text on the clipboard: for a value shown shortened. */
export function CopyIconButton({ text, label }: { text: string; label: string }) {
    const [copied, setCopied] = useState(false);

    async function copy(): Promise<void> {
        await navigator.clipboard.writeText(text);
        setCopied(true);
        window.setTimeout(() => setCopied(false), 2000);
    }

    return (
        <Button type="button" variant="ghost" size="icon" className="size-7" onClick={copy} aria-label={copied ? __('Copied') : label} title={copied ? __('Copied') : label}>
            {copied ? <Check /> : <Copy />}
        </Button>
    );
}
