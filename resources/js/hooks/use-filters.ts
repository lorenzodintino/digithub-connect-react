import { router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

/**
 * Filters kept in the query string: the page reloads
 * its props 300 ms after the last change, the URL can be shared, and values
 * equal to their default stay out of it. Leaving `page` out of the visit is
 * what sends a new search back to the first page.
 *
 * `apply` changes several filters at once and visits straight away, for
 * clicks that should not wait (a view, a day).
 */
export function useFilters<T extends Record<string, string>>(
    url: string,
    current: T,
    defaults: T,
): [T, (key: keyof T, value: string) => void, (changes: Partial<T>) => void] {
    const [filters, setFilters] = useState<T>(current);
    const firstRender = useRef(true);
    const skipNext = useRef(false);

    function visit(values: T): void {
        const query = Object.fromEntries(Object.entries(values).filter(([key, value]) => value !== defaults[key]));

        router.get(url, query, { preserveState: true, preserveScroll: true, replace: true });
    }

    useEffect(() => {
        if (firstRender.current || skipNext.current) {
            firstRender.current = false;
            skipNext.current = false;

            return;
        }

        const timer = window.setTimeout(() => visit(filters), 300);

        return () => window.clearTimeout(timer);
        // `defaults` and `url` are constants of the page.
    }, [filters]);

    function set(key: keyof T, value: string): void {
        setFilters((previous) => ({ ...previous, [key]: value }));
    }

    function apply(changes: Partial<T>): void {
        const next = { ...filters, ...changes };
        const changed = Object.keys(next).some((key) => next[key] !== filters[key]);

        // The state change below must not trigger the debounced visit too; an
        // identical state triggers nothing, so the flag would stay set.
        skipNext.current = changed;
        setFilters(next);
        visit(next);
    }

    return [filters, set, apply];
}
