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
- The session copy outranks the account, so anything that writes `users.locale` must also update `session('locale')` or the change appears to be ignored. See `Settings\ProfileController::update()`.
- React pages read the same `lang/it.json` through `__()` / `trans_choice()` from `@/lib/i18n`.
- `phpunit.xml` pins `APP_LOCALE=en`, and the user/tenant factories default `locale` to `config('app.locale')`, so assertions stay in English.

## Colore del brand: variabili sulle pagine pubbliche, contrasto calcolato
`PortalLayout` dichiara sulla radice delle pagine pubbliche `--portal-brand`, `--portal-brand-contrast` e ridefinisce `--primary`, `--primary-foreground` e `--ring` di shadcn: ogni `Button` primario della pagina pubblica prende il colore del portale senza classi aggiuntive.

- Testo sopra il brand: **mai `text-white`**, usa `var(--portal-brand-contrast)` (`Tenant::brandForeground()`, bianco o quasi-nero secondo la luminanza WCAG). Un brand chiaro rendeva il testo invisibile.
- `portal-logo.tsx` non usa le variabili: legge `brand` e `brand_contrast` dalle props, così funziona anche fuori dalle pagine pubbliche.

## Conteggi con trans_choice, e nessun controllo senza nome
Due trappole trovate tre volte, sempre dal collaudo a mano e mai dai test.

- **Ogni numero a schermo passa da `trans_choice`**, con singolare e plurale scritti in inglese e in italiano: `trans_choice('one seat free|:count seats free', $n)` in PHP, la stessa funzione da `@/lib/i18n` in React. Un `__(':count posti liberi')` produce «1 posti liberi». Vale per avvisi, badge, toast, email, guardie di eliminazione.
- **Ogni select, filtro o tendina ha un'etichetta** (`FormField`), anche in una barra compatta. Scelto un valore, il valore si nomina ma la dimensione no: «Europe/Rome» sopra una colonna di orari sembra un'impostazione che nessuno ha chiesto. Riusa le chiavi esistenti (`Timezone` = «Fuso orario») invece di inventare etichette nuove.
