import { useCallback, useEffect, useState } from 'react';

export type Appearance = 'light' | 'dark' | 'system';

/** The key Flux used: a choice saved before the move to React still counts. */
const STORAGE_KEY = 'flux.appearance';

function prefersDark(): boolean {
    return window.matchMedia('(prefers-color-scheme: dark)').matches;
}

function apply(appearance: Appearance): void {
    document.documentElement.classList.toggle('dark', appearance === 'dark' || (appearance === 'system' && prefersDark()));
}

function stored(): Appearance {
    const value = window.localStorage.getItem(STORAGE_KEY);

    return value === 'light' || value === 'dark' ? value : 'system';
}

/**
 * Light, dark or the system's choice, kept in this browser. "System" is stored
 * as no value at all, as Flux does.
 */
export function useAppearance(): { appearance: Appearance; updateAppearance: (appearance: Appearance) => void } {
    const [appearance, setAppearance] = useState<Appearance>(stored);

    const updateAppearance = useCallback((next: Appearance) => {
        if (next === 'system') {
            window.localStorage.removeItem(STORAGE_KEY);
        } else {
            window.localStorage.setItem(STORAGE_KEY, next);
        }

        setAppearance(next);
        apply(next);
    }, []);

    useEffect(() => {
        if (appearance !== 'system') {
            return;
        }

        const query = window.matchMedia('(prefers-color-scheme: dark)');
        const follow = () => apply('system');
        query.addEventListener('change', follow);

        return () => query.removeEventListener('change', follow);
    }, [appearance]);

    return { appearance, updateAppearance };
}
