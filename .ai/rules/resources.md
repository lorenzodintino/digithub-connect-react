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
