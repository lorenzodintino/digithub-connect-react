---
paths:
  - 'resources/js/pages/auth/**'
---

# Auth

## Pagine di accesso: view Fortify come pagine Inertia
Login, recupero e reimpostazione password, sfida 2FA, conferma password, verifica email (e registrazione, oggi spenta) sono pagine React `auth/*` restituite da `FortifyServiceProvider::configureViews()` con `Inertia::render`; layout `AuthLayout` scelto in `app.tsx` per i nomi `auth/…`. I form postano alle rotte di Fortify (Wayfinder: `@/routes/login`, `@/routes/password`, `@/routes/two-factor/login`, `@/routes/password/confirm`): errori e `session('status')` tornano come props, nessun controller nostro. Passkey: componente `passkey-verify.tsx` con `Passkeys.verify({ routes })` di `@laravel/passkeys`, poi visita completa a `response.redirect`. Un nuovo campo di stato della sessione va aggiunto alle props della view in configureViews, non letto dal client.
