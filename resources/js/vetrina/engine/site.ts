// @ts-nocheck -- ported from the closed showcase draft (vanilla DOM + three.js); the typed boundary is `mountAtrio`.
/* Motion preferences and the pointer-depth tilt shared by the engine modules. */

export const reduced = matchMedia('(prefers-reduced-motion: reduce)').matches;
export const finePointer = matchMedia('(hover: hover) and (pointer: fine)').matches;

/* Inclinazione 3D che segue il puntatore: scrive --px e --py (da -1 a 1) sul bersaglio. */
export function pointerDepth(area, target = area) {
    if (reduced || !finePointer) return;
    let raf = 0;
    area.addEventListener('pointermove', (e) => {
        const r = area.getBoundingClientRect();
        const x = ((e.clientX - r.left) / r.width - 0.5) * 2;
        const y = ((e.clientY - r.top) / r.height - 0.5) * 2;
        cancelAnimationFrame(raf);
        raf = requestAnimationFrame(() => {
            target.style.setProperty('--px', x.toFixed(3));
            target.style.setProperty('--py', y.toFixed(3));
        });
    });
    area.addEventListener('pointerleave', () => {
        target.style.setProperty('--px', 0);
        target.style.setProperty('--py', 0);
    });
}
