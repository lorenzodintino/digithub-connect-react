// @ts-nocheck -- ported from the closed showcase draft (vanilla DOM + three.js); the typed boundary is `mountAtrio`.
import { __ } from '@/lib/i18n';
import { pointerDepth } from './site';
import { SyncDemo } from './ui';

/* Il portale col marchio: nome e colore cambiano il telefono, il link prende il nome. */
export function initBrand(phoneEl) {
    const portal = new SyncDemo({ phone: phoneEl });
    const slug = document.getElementById('slug');
    const nameInput = document.getElementById('b-name');
    const swatches = document.querySelectorAll('input[name="brand"]');
    /* finché il visitatore non sceglie un colore il telefono resta con quello di partenza (token --brand del CSS) */
    let color = null;

    const apply = () => {
        portal.setBrand({ name: nameInput.value.trim(), color });
        slug.textContent =
            nameInput.value
                .trim()
                .toLowerCase()
                .normalize('NFD')
                .replace(/[̀-ͯ]/g, '')
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/^-|-$/g, '') || __('your-business');
    };
    nameInput.addEventListener('input', apply);
    swatches.forEach((s) =>
        s.addEventListener('change', () => {
            color = s.value;
            apply();
        }),
    );
    pointerDepth(document.getElementById('portal-scene'));

    return portal;
}
