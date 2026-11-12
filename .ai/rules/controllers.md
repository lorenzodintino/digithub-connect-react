---
paths:
  - 'app/Http/Controllers/**'
---

# Controllers

## Pagine Inertia: controller, Form Request, toast e redirect
Una pagina = controller che fa `Inertia::render('area/pagina', [...])` con props già pronte (array, date formattate nel fuso di chi guarda, URL calcolati), più un Form Request per ogni scrittura. Dopo un salvataggio: `App\Support\Toast::success(__('...'))` (flash di Inertia) e `to_route(...)` o `back()`; gli errori di validazione tornano da soli in `errors`. Autorizzazione come prima (policy, `role:`), mai fidarsi delle props. Le rotte di Fortify e Passkeys (2FA, passkey, verifica email) si chiamano dal client: rispondono `back()` alle visite Inertia.

## Binding implicito solo sui parametri tipizzati del controller
Laravel risolve `{tenant}` in un model solo se il metodo del controller ha un parametro `Tenant $tenant`. `MailSettingsController` serve sia `app.portal.mail.*` (portale del manager, senza parametro) sia `admin.portals.mail.*` (`{tenant}` nell'URL) e non tipizza il Tenant: lì `$request->route('tenant')` è lo slug grezzo, e `MailSettingsRequest::tenant()` lo risolve da sé (con fallback al portale dell'utente). Trovato nella 9.5: la rotta dell'admin rispondeva 404. Se un controller condiviso legge un parametro di rotta da un Form Request, risolvilo lì o tipizzalo nel metodo.

Password generate (nuovo portale, nuovo operatore, «Issue new password»): `Inertia::flash('credentials', ['email' => ..., 'password' => ...])` e nella pagina `<CredentialsDialog title=... description=... />` da `@/components/credentials-dialog`, mai in props o in sessione persistente.
