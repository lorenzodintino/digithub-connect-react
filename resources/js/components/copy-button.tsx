import { Check, Copy } from 'lucide-react';
import { type ReactNode, useState } from 'react';
import { Button } from '@/components/ui/button';
import { __ } from '@/lib/i18n';

/** A button that puts a text, line breaks included, on the clipboard. */
export function CopyButton({ text, children }: { text: string; children: ReactNode }) {
    const [copied, setCopied] = useState(false);

    async function copy(): Promise<void> {
        await navigator.clipboard.writeText(text);
        setCopied(true);
        window.setTimeout(() => setCopied(false), 2000);
    }

    return (
        <Button type="button" size="sm" variant="secondary" onClick={copy}>
            {copied ? <Check /> : <Copy />}
            {copied ? __('Copied') : children}
        </Button>
    );
}
