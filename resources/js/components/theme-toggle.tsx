import { Moon, Sun } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useAppearance } from '@/hooks/use-appearance';
import { __ } from '@/lib/i18n';
import { cn } from '@/lib/utils';

/**
 * The quick light/dark switch: one click flips the theme with a circle that grows
 * from the button. The choice is the same one the Appearance settings show.
 */
export function ThemeToggle({ className }: { className?: string }) {
    const { dark, toggle } = useAppearance();
    const label = dark ? __('Switch to light theme') : __('Switch to dark theme');

    return (
        <Button
            variant="ghost"
            size="icon-sm"
            className={cn('relative shrink-0 overflow-hidden', className)}
            aria-label={label}
            title={label}
            data-test="theme-toggle"
            onClick={(event) => {
                const box = event.currentTarget.getBoundingClientRect();

                toggle({ x: box.left + box.width / 2, y: box.top + box.height / 2 });
            }}
        >
            <Sun className="size-4 rotate-0 scale-100 transition-all duration-500 dark:-rotate-90 dark:scale-0" />
            <Moon className="absolute size-4 rotate-90 scale-0 transition-all duration-500 dark:rotate-0 dark:scale-100" />
        </Button>
    );
}
