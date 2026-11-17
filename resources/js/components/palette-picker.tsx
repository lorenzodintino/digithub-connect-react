import { Check } from 'lucide-react';
import { isPalette, type Palette } from '@/hooks/use-palette';
import { __ } from '@/lib/i18n';
import { cn } from '@/lib/utils';

const descriptions: Record<Palette, () => string> = {
    basic: () => __('Clean and neutral, it lets your logo and your colour do the talking.'),
    oltremare: () => __('Fresh and reassuring, it inspires trust at first sight.'),
    lampone: () => __('Cheerful and refined, for a portal that puts people in a good mood.'),
};

/**
 * A small booking page in the colours of a palette. `data-palette-preview` makes palettes.css hand this element the
 * tokens of that palette in the theme being shown, whichever palette the page itself wears.
 */
function Preview({ palette }: { palette: Palette }) {
    return (
        <span data-palette-preview={palette} aria-hidden="true" className="block rounded-lg border bg-background p-2.5 text-foreground">
            <span className="mb-2 block h-1.5 w-1/3 rounded-full bg-foreground/80" />
            <span className="grid grid-cols-7 gap-1">
                {Array.from({ length: 14 }, (_, index) => (
                    <span
                        key={index}
                        className={cn('aspect-square rounded-[3px] border bg-card', index === 9 && 'border-primary bg-primary', [2, 5, 11].includes(index) && 'bg-muted')}
                    />
                ))}
            </span>
            <span className="mt-2 flex items-center justify-between">
                <span className="h-4 w-1/3 rounded-md bg-primary" />
                <span className="h-1.5 w-1/4 rounded-full bg-muted-foreground/50" />
            </span>
        </span>
    );
}

/**
 * The choice of the colour palette: three cards, each previewing its own colours. The radio buttons are real, so the
 * keyboard and screen readers work as for any group; `onChange` also gets where the person clicked, for the reveal.
 */
export function PalettePicker({
    value,
    options,
    onChange,
}: {
    value: string;
    options: { value: string; label: string }[];
    onChange: (palette: Palette, origin: { x: number; y: number }) => void;
}) {
    return (
        <div role="radiogroup" aria-label={__('Palette')} className="grid gap-3 sm:grid-cols-3">
            {options
                .filter((option): option is { value: Palette; label: string } => isPalette(option.value))
                .map((option) => {
                    const selected = value === option.value;

                    return (
                        <label
                            key={option.value}
                            className={cn(
                                'relative flex cursor-pointer flex-col gap-3 rounded-xl border p-3 transition-colors has-[:focus-visible]:ring-[3px] has-[:focus-visible]:ring-ring/50',
                                selected ? 'border-primary ring-1 ring-primary' : 'hover:border-foreground/30',
                            )}
                        >
                            <input
                                type="radio"
                                name="portal-palette"
                                value={option.value}
                                checked={selected}
                                className="sr-only"
                                onChange={(event) => {
                                    const box = event.currentTarget.closest('label')!.getBoundingClientRect();

                                    onChange(option.value, { x: box.left + box.width / 2, y: box.top + box.height / 2 });
                                }}
                            />
                            <Preview palette={option.value} />
                            <span className="flex items-center justify-between gap-2 font-semibold">
                                {option.label}
                                {selected && <Check className="size-4 text-primary" aria-hidden="true" />}
                            </span>
                            <span className="text-sm text-muted-foreground">{descriptions[option.value]()}</span>
                        </label>
                    );
                })}
        </div>
    );
}
