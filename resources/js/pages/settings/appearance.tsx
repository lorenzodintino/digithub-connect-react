import { Head } from '@inertiajs/react';
import { Monitor, Moon, Sun } from 'lucide-react';
import { type Appearance, useAppearance } from '@/hooks/use-appearance';
import SettingsLayout from '@/layouts/settings-layout';
import { __ } from '@/lib/i18n';
import { cn } from '@/lib/utils';

export default function AppearanceSettings() {
    const { appearance, updateAppearance } = useAppearance();

    const options: { value: Appearance; label: string; icon: typeof Sun }[] = [
        { value: 'light', label: __('Light'), icon: Sun },
        { value: 'dark', label: __('Dark'), icon: Moon },
        { value: 'system', label: __('System'), icon: Monitor },
    ];

    return (
        <>
            <Head title={__('Appearance settings')} />

            <SettingsLayout heading={__('Appearance')} subheading={__('Update the appearance settings for your account')}>
                <div role="radiogroup" aria-label={__('Appearance')} className="inline-flex gap-1 rounded-lg bg-muted p-1">
                    {options.map(({ value, label, icon: Icon }) => (
                        <button
                            key={value}
                            type="button"
                            role="radio"
                            aria-checked={appearance === value}
                            onClick={() => updateAppearance(value)}
                            className={cn(
                                'flex items-center gap-2 rounded-md px-3.5 py-1.5 text-sm font-medium transition-colors',
                                appearance === value ? 'bg-background shadow-xs' : 'text-muted-foreground hover:text-foreground',
                            )}
                        >
                            <Icon className="size-4" />
                            {label}
                        </button>
                    ))}
                </div>
            </SettingsLayout>
        </>
    );
}
