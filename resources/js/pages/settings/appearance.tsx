import { Head, useForm } from '@inertiajs/react';
import { Monitor, Moon, Sun } from 'lucide-react';
import { PalettePicker } from '@/components/palette-picker';
import { Button } from '@/components/ui/button';
import { type Appearance, useAppearance } from '@/hooks/use-appearance';
import { usePalette } from '@/hooks/use-palette';
import SettingsLayout from '@/layouts/settings-layout';
import { __ } from '@/lib/i18n';
import { update } from '@/routes/appearance';
import { cn } from '@/lib/utils';

interface Props {
    /** The business's palette (its manager picks it for everyone) or, for the platform administrator, their own. */
    paletteChoice: { scope: 'business' | 'personal'; current: string; label: string; editable: boolean; options: { value: string; label: string }[] } | null;
}

export default function AppearanceSettings({ paletteChoice: palette }: Props) {
    const { appearance, updateAppearance } = useAppearance();
    const { update: previewPalette } = usePalette();
    const form = useForm({ palette: palette?.current ?? 'basic' });

    const options: { value: Appearance; label: string; icon: typeof Sun }[] = [
        { value: 'light', label: __('Light'), icon: Sun },
        { value: 'dark', label: __('Dark'), icon: Moon },
        { value: 'system', label: __('System'), icon: Monitor },
    ];

    return (
        <>
            <Head title={__('Appearance settings')} />

            <SettingsLayout heading={__('Appearance')} subheading={__('Update the appearance settings for your account')}>
                <div className="space-y-10">
                    <section className="space-y-3">
                        <h3 className="font-semibold">{__('Theme')}</h3>

                        <div role="radiogroup" aria-label={__('Theme')} className="inline-flex gap-1 rounded-lg bg-muted p-1">
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
                    </section>

                    {palette && (
                        <section className="space-y-3">
                            <h3 className="font-semibold">{__('Palette')}</h3>

                            {palette.editable ? (
                                <form
                                    className="space-y-4"
                                    onSubmit={(event) => {
                                        event.preventDefault();
                                        form.put(update.url(), { preserveScroll: true });
                                    }}
                                >
                                    <p className="text-sm text-muted-foreground">
                                        {palette.scope === 'personal'
                                            ? __('The colours you see across the platform. Only you see them: light or dark is up to you too.')
                                            : __('The colours of your booking page and of your back office, combined with your brand color. Light or dark follows each person.')}
                                    </p>

                                    <PalettePicker
                                        value={form.data.palette}
                                        options={palette.options}
                                        onChange={(next, origin) => {
                                            form.setData('palette', next);
                                            previewPalette(next, origin, false);
                                        }}
                                    />

                                    <Button type="submit" disabled={form.processing || form.data.palette === palette.current}>
                                        {__('Save changes')}
                                    </Button>
                                </form>
                            ) : (
                                <p className="text-sm text-muted-foreground">{__('Your business uses the :palette palette. Its manager can change it in Settings › Appearance.', { palette: palette.label })}</p>
                            )}
                        </section>
                    )}
                </div>
            </SettingsLayout>
        </>
    );
}
