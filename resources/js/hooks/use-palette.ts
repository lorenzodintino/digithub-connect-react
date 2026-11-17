import { useCallback, useSyncExternalStore } from 'react';
import { announceStyleChange, switchWithReveal } from '@/hooks/use-appearance';

export type Palette = 'basic' | 'oltremare' | 'lampone';

export const palettes: readonly Palette[] = ['basic', 'oltremare', 'lampone'];

/**
 * Where the visitor's own choice lives on the home page, which lets anyone try the palettes. Every other page wears
 * the palette of its business, which the server sends (`palette` shared prop) and `app.tsx` applies with `adoptPalette`.
 */
const STORAGE_KEY = 'atrio.palette';

export function isPalette(value: unknown): value is Palette {
    return palettes.includes(value as Palette);
}

/** The palette the visitor tried last on the home page, Basic when none. */
export function storedPalette(): Palette {
    try {
        const value = window.localStorage.getItem(STORAGE_KEY);

        return isPalette(value) ? value : 'basic';
    } catch {
        return 'basic';
    }
}

const listeners = new Set<() => void>();
let current: Palette | null = null;

function read(): Palette {
    if (current === null) {
        const fromPage = document.documentElement.dataset.palette;

        current = isPalette(fromPage) ? fromPage : 'basic';
    }

    return current;
}

function subscribe(listener: () => void): () => void {
    listeners.add(listener);

    return () => listeners.delete(listener);
}

/** Put a palette on the page: the attribute the stylesheets read, and whoever shows it. */
function apply(next: Palette): void {
    current = next;
    document.documentElement.dataset.palette = next;
    listeners.forEach((listener) => listener());
}

/** The palette a page has by right (its business's, or the visitor's on the home page): no animation, nothing saved. */
export function adoptPalette(next: Palette): void {
    if (next === read() && document.documentElement.dataset.palette === next) {
        return;
    }

    apply(next);
    announceStyleChange({ palette: true, theme: false });
}

/**
 * The colour palette, one value for the whole page like `useAppearance`. `update` redraws with the circle that grows
 * from the control the person used; `remember` keeps the choice in this browser (the home page) or not (a business's
 * settings, where the server is the one that keeps it).
 */
export function usePalette(): { palette: Palette; update: (palette: Palette, origin?: { x: number; y: number }, remember?: boolean) => void } {
    const palette = useSyncExternalStore(subscribe, read, () => 'basic' as const);

    const update = useCallback((next: Palette, origin?: { x: number; y: number }, remember = true) => {
        if (next === read()) {
            return;
        }

        switchWithReveal(() => {
            if (remember) {
                try {
                    window.localStorage.setItem(STORAGE_KEY, next);
                } catch {
                    /* no storage (private window): the choice holds for this visit */
                }
            }

            apply(next);
            announceStyleChange({ palette: true, theme: false });
        }, origin ?? { x: window.innerWidth / 2, y: window.innerHeight / 3 });
    }, []);

    return { palette, update };
}
