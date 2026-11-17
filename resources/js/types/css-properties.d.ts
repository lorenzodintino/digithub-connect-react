import 'react';

declare module 'react' {
    /** CSS custom properties passed through `style`, as in `style={{ '--i': 2 }}`. */
    interface CSSProperties {
        [property: `--${string}`]: string | number | undefined;
    }
}
