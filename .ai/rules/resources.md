---
paths:
  - 'resources/**'
---

# Resources

## Bilingual IT/EN: every string through __()
App default locale is `it`; `en` is the fallback. `config('app.supported_locales')` = ['it','en'].

- Every user-facing string must go through `__('English source text')`. English text IS the key.
- After adding a string, add its Italian value to `lang/it.json` (keep alphabetical). Missing keys silently render English.
- Locale resolution order (`App\Http\Middleware\SetLocale`): `?lang=` query → session → `user->locale` → config. Public portal pages fall back to `tenant->locale` only when neither session nor query is set.
- A valid `?lang=` hit is written to the session **and**, for an authenticated user, persisted to `users.locale` — so the choice follows the account across devices.
- The session copy outranks the account, so anything that writes `users.locale` must also update `session('locale')` or the change appears to be ignored. See `updateProfileInformation()` in `resources/views/pages/settings/⚡profile.blade.php`.
- `phpunit.xml` pins `APP_LOCALE=en`, and the user/tenant factories default `locale` to `config('app.locale')`, so assertions stay in English.

## Colore del brand: variabili sulle pagine pubbliche, contrasto calcolato
La radice di ogni pagina pubblica (`portal.show`, `portal.service`, `portal.booking`) dichiara cinque variabili: `--portal-brand`, `--portal-brand-contrast` e i tre `--color-accent*` di Flux. Impostando l'accent, ogni `flux:button variant="primary"` della pagina pubblica prende il colore del portale senza classi aggiuntive.

- Testo sopra il brand: **mai `text-white`**, usa `var(--portal-brand-contrast)` (`Tenant::brandForeground()`, bianco o quasi-nero secondo la luminanza WCAG). Un brand chiaro rendeva il testo invisibile.
- `x-portal-logo` non usa le variabili: compare anche nel back-office, dove non esistono. Legge `brand_color` e `brandForeground()` dal modello.
- Le tabelle Flux azzerano il padding esterno di prima e ultima colonna; una regola in `resources/css/app.css` su `[data-flux-table]` lo rimette per tutte. Non aggiungere `px-*` sulle singole celle.
