import { useCallback, useEffect, useSyncExternalStore } from 'react';

export type Appearance = 'light' | 'dark' | 'system';

/** The key Flux used: a choice saved before the move to React still counts. */
const STORAGE_KEY = 'flux.appearance';

function prefersDark(): boolean {
    return window.matchMedia('(prefers-color-scheme: dark)').matches;
}

/**
 * Tells whoever draws with colours the CSS cannot reach (the 3D calendar on the home page) to read the tokens
 * again. It fires inside the view transition callback, so the new picture is made with the new colours.
 */
export function announceStyleChange(change: { palette: boolean; theme: boolean }): void {
    window.dispatchEvent(new CustomEvent('palettechange', { detail: change }));
}

function apply(appearance: Appearance): void {
    document.documentElement.classList.toggle('dark', appearance === 'dark' || (appearance === 'system' && prefersDark()));
    announceStyleChange({ palette: false, theme: true });
}

function stored(): Appearance {
    const value = window.localStorage.getItem(STORAGE_KEY);

    return value === 'light' || value === 'dark' ? value : 'system';
}

/**
 * One choice for the whole page: the quick toggle in the sidebar and the
 * Appearance settings read and write the same value, so neither goes stale.
 */
const listeners = new Set<() => void>();
let current: Appearance | null = null;

function read(): Appearance {
    return (current ??= stored());
}

function subscribe(listener: () => void): () => void {
    listeners.add(listener);

    return () => listeners.delete(listener);
}

function emit(): void {
    listeners.forEach((listener) => listener());
}

function set(next: Appearance): void {
    if (next === 'system') {
        window.localStorage.removeItem(STORAGE_KEY);
    } else {
        window.localStorage.setItem(STORAGE_KEY, next);
    }

    current = next;
    apply(next);
    emit();
}

function isDark(): boolean {
    return document.documentElement.classList.contains('dark');
}

/**
 * Changes the theme with a circle that grows from where the person clicked: the
 * browser keeps a picture of the old page and the new one is revealed on top of
 * it. Without the View Transitions API, or with reduced motion asked for, the
 * theme simply changes.
 */
export function switchWithReveal(change: () => void, origin: { x: number; y: number }): void {
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    if (reduced || typeof document.startViewTransition !== 'function') {
        change();

        return;
    }

    const radius = Math.hypot(Math.max(origin.x, window.innerWidth - origin.x), Math.max(origin.y, window.innerHeight - origin.y));
    const transition = document.startViewTransition(change);

    transition.ready
        .then(() => {
            document.documentElement.animate(
                { clipPath: [`circle(0px at ${origin.x}px ${origin.y}px)`, `circle(${radius}px at ${origin.x}px ${origin.y}px)`] },
                { duration: 650, easing: 'cubic-bezier(0.22, 1, 0.36, 1)', pseudoElement: '::view-transition-new(root)' },
            );
        })
        .catch(() => undefined);
}

/**
 * Light, dark or the system's choice, kept in this browser. "System" is stored
 * as no value at all, as Flux does. `toggle` flips between light and dark from
 * whatever is showing now, with the reveal animation.
 */
export function useAppearance(): {
    appearance: Appearance;
    dark: boolean;
    updateAppearance: (appearance: Appearance, origin?: { x: number; y: number }) => void;
    toggle: (origin?: { x: number; y: number }) => void;
} {
    const appearance = useSyncExternalStore(subscribe, read, () => 'system' as const);

    const updateAppearance = useCallback((next: Appearance, origin?: { x: number; y: number }) => (origin ? switchWithReveal(() => set(next), origin) : set(next)), []);

    const toggle = useCallback((origin?: { x: number; y: number }) => {
        const next: Appearance = isDark() ? 'light' : 'dark';

        switchWithReveal(() => set(next), origin ?? { x: window.innerWidth / 2, y: window.innerHeight / 2 });
    }, []);

    useEffect(() => {
        if (appearance !== 'system') {
            return;
        }

        const query = window.matchMedia('(prefers-color-scheme: dark)');
        const follow = () => {
            apply('system');
            emit();
        };
        query.addEventListener('change', follow);

        return () => query.removeEventListener('change', follow);
    }, [appearance]);

    // `appearance` is read above so a change re-renders; what is showing is the page's own class.
    return { appearance, dark: typeof document !== 'undefined' && isDark(), updateAppearance, toggle };
}
