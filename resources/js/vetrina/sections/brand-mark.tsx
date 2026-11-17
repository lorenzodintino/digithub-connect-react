/** The Atrio mark: tile, glyph and dot take their colours from the palette tokens (see `.brand__*` in vetrina.css). */
export function BrandMark() {
    return (
        <svg className="brand__mark" viewBox="0 0 100 100" width={32} height={32} aria-hidden="true" focusable="false">
            <path
                className="brand__tile"
                d="M0 0L100 0L100 100L0 100ZM19.81 84.32L35.41 84.32L43.653 60.92L75.51 60.92L59.59 15.68L43.99 15.68ZM82.53 74.18C82.53 68.58 77.99 64.04 72.39 64.04C66.79 64.04 62.25 68.58 62.25 74.18C62.25 79.78 66.79 84.32 72.39 84.32C77.99 84.32 82.53 79.78 82.53 74.18ZM46.401 53.12L51.786 37.834L57.165 53.12Z"
            />
            <path
                className="brand__glyph"
                d="M19.81 84.32L43.99 15.68L59.59 15.68L75.51 60.92L43.653 60.92L35.41 84.32ZM51.786 37.834L46.401 53.12L57.165 53.12L51.786 37.834Z"
            />
            <circle className="brand__dot" cx="72.39" cy="74.18" r="10.14" />
        </svg>
    );
}
