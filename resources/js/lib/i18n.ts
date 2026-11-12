/**
 * Laravel's __() and trans_choice() for the React pages.
 *
 * The keys are the English source text, exactly as in Blade, and the Italian
 * values come from the same lang/it.json: one file to keep up to date, and a
 * string moved from a Blade view to a component keeps its translation.
 */

type Replacements = Record<string, string | number>;

const catalogues = import.meta.glob<Record<string, string>>('../../../lang/*.json', {
    eager: true,
    import: 'default',
});

const translations: Record<string, Record<string, string>> = Object.fromEntries(
    Object.entries(catalogues).map(([path, lines]) => [path.replace(/^.*\/(\w+)\.json$/, '$1'), lines]),
);

let currentLocale = typeof document !== 'undefined' ? document.documentElement.lang || 'it' : 'it';

export function setLocale(locale: string): void {
    currentLocale = locale;
}

export function getLocale(): string {
    return currentLocale;
}

/**
 * Same rules as Laravel's makeReplacements(): `:name`, `:Name` and `:NAME`,
 * the longest placeholder first so `:count` never eats into `:counter`.
 */
function replace(line: string, replacements: Replacements): string {
    const pairs: Record<string, string> = {};

    for (const [key, raw] of Object.entries(replacements)) {
        const value = String(raw);

        pairs[`:${key.charAt(0).toUpperCase()}${key.slice(1)}`] = value.charAt(0).toUpperCase() + value.slice(1);
        pairs[`:${key.toUpperCase()}`] = value.toUpperCase();
        pairs[`:${key}`] = value;
    }

    const placeholders = Object.keys(pairs).sort((a, b) => b.length - a.length);

    if (placeholders.length === 0) {
        return line;
    }

    const pattern = new RegExp(placeholders.map((placeholder) => placeholder.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')).join('|'), 'g');

    return line.replace(pattern, (match) => pairs[match]);
}

function lookup(key: string): string {
    return translations[currentLocale]?.[key] ?? key;
}

export function __(key: string, replacements: Replacements = {}): string {
    return replace(lookup(key), replacements);
}

/**
 * Port of Laravel's MessageSelector: explicit `{n}` and `[from,to]` segments
 * first, then the plural rule of the locale. Italian and English share it.
 */
function choose(line: string, count: number): string {
    const segments = line.split('|');

    for (const segment of segments) {
        const match = segment.match(/^[{[]([^[\]{}]*)[}\]](.*)$/s);

        if (match === null) {
            continue;
        }

        const [, condition, value] = match;

        if (condition.includes(',')) {
            const [from, to] = condition.split(',', 2);

            if ((to === '*' && count >= Number(from)) || (from === '*' && count <= Number(to)) || (count >= Number(from) && count <= Number(to))) {
                return value.trim();
            }
        } else if (Number(condition) === count) {
            return value.trim();
        }
    }

    const stripped = segments.map((segment) => segment.replace(/^[{[]([^[\]{}]*)[}\]]/, ''));
    const index = count === 1 ? 0 : 1;

    return (stripped[index] ?? stripped[0]).trim();
}

export function trans_choice(key: string, count: number, replacements: Replacements = {}): string {
    return replace(choose(lookup(key), count), { count, ...replacements });
}
