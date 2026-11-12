import { ChevronsUpDown } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { __, getLocale } from '@/lib/i18n';
import { cn } from '@/lib/utils';

const locales = {
    it: { label: 'Italiano', flag: '🇮🇹' },
    en: { label: 'English', flag: '🇬🇧' },
} as const;

type Locale = keyof typeof locales;

/**
 * A full reload with ?lang=: SetLocale stores the
 * choice in the session and on the account, and both stacks read it from there.
 * The URL comes from the address bar, the only place that knows the filters
 * the page has pushed into the query since it loaded.
 */
function switchTo(locale: Locale): void {
    const target = new URL(window.location.href);
    target.searchParams.set('lang', locale);
    window.location.href = target.toString();
}

export function LocaleSwitcher({ className, side = 'top', align = 'start' }: { className?: string; side?: 'top' | 'bottom'; align?: 'start' | 'end' }) {
    const current = locales[getLocale() as Locale] ?? locales.it;

    return (
        <div className={className}>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button variant="ghost" size="sm" className="w-full justify-start" aria-label={__('Language')}>
                        <span>{current.flag}</span>
                        {current.label}
                        <ChevronsUpDown className="ms-auto" />
                    </Button>
                </DropdownMenuTrigger>

                <DropdownMenuContent side={side} align={align}>
                    {(Object.keys(locales) as Locale[]).map((code) => (
                        <DropdownMenuItem key={code} className={cn(getLocale() === code && 'font-semibold')} onSelect={() => switchTo(code)}>
                            <span>{locales[code].flag}</span>
                            {locales[code].label}
                        </DropdownMenuItem>
                    ))}
                </DropdownMenuContent>
            </DropdownMenu>
        </div>
    );
}
