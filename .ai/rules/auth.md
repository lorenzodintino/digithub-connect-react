---
paths:
  - 'resources/js/pages/auth/**'
---

# Auth

## Pagine di accesso: view Fortify come pagine Inertia
Login, recupero e reimpostazione password, sfida 2FA, conferma password, verifica email (e registrazione, oggi spenta) sono pagine React `auth/*` restituite da `FortifyServiceProvider::configureViews()` con `Inertia::render`; layout `AuthLayout` scelto in `app.tsx` per i nomi `auth/…`. I form postano alle rotte di Fortify (Wayfinder: `@/routes/login`, `@/routes/password`, `@/routes/two-factor/login`, `@/routes/password/confirm`): errori e `session('status')` tornano come props, nessun controller nostro. Passkey: componente `passkey-verify.tsx` con `Passkeys.verify({ routes })` di `@laravel/passkeys`, poi visita completa a `response.redirect`. Un nuovo campo di stato della sessione va aggiunto alle props della view in configureViews, non letto dal client.

## Le passkey sono spente di default (`PASSKEYS_ENABLED`)
Deciso il 2026-11-13: l'accesso protetto è la 2FA con l'app; le passkey restano nel codice ma **nascoste del tutto** finché `PASSKEYS_ENABLED=true` nel `.env` (default `false`, in `config/fortify.php`; `phpunit.xml` lo fissa a `false`). Spente: Fortify non registra nessuna rotta `passkey.*`, il login e la conferma password non hanno il pulsante, la pagina Sicurezza non ha la sezione, `.well-known/passkey-endpoints` risponde 404.
- **Il frontend non importa mai `@/routes/passkey`**: quei file li genera Wayfinder dalle rotte, e senza le rotte spariscono (la build si romperebbe). Gli indirizzi arrivano dal server come props (`passkeys` in `auth/login` e `auth/confirm-password`, `url` per ogni passkey in `settings/security`) e sono `null` quando le rotte non ci sono: la condizione è `Route::has('passkey.…')`.
- Per riattivarle in produzione: https, `APP_URL` col dominio vero (il relying party id viene da lì) e una prova con un dispositivo reale; le passkey già registrate nel database restano.
