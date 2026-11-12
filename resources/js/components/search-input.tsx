import { Search, X } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { __ } from '@/lib/i18n';
import { cn } from '@/lib/utils';

/** The search box of a list: labelled, with a magnifying glass and a clear button. */
export function SearchInput({
    id = 'search',
    label,
    value,
    onChange,
    placeholder,
    className,
}: {
    id?: string;
    /** What the box searches by; plain "Search" when the list says it already. */
    label?: string;
    value: string;
    onChange: (value: string) => void;
    placeholder: string;
    className?: string;
}) {
    return (
        <div className={cn('grid gap-2', className)}>
            <Label htmlFor={id}>{label ?? __('Search')}</Label>
            <div className="relative">
                <Search className="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                <Input id={id} type="search" value={value} onChange={(event) => onChange(event.target.value)} placeholder={placeholder} className="ps-9 pe-9" />
                {value !== '' && (
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="absolute inset-y-0 end-0 h-full text-muted-foreground"
                        onClick={() => onChange('')}
                        aria-label={__('Clear input')}
                    >
                        <X />
                    </Button>
                )}
            </div>
        </div>
    );
}
