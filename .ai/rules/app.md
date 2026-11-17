---
paths:
  - 'app/**'
---

# App

## Roles and portal (tenant) scoping
Single-database multi-tenancy. `App\Enums\UserRole`: Admin (platform, `tenant_id` null), Manager (exactly one per tenant), Default (operator). Every domain row carries `tenant_id`.

- Route guards use the `role` middleware alias: `role:admin`, `role:manager`, `role:manager,default`.
- Authorization inside components goes through Tenant/Service/User policies; `User::canAccessTenant()` is the single scoping check (admin always true).
- A Manager may be bookable (`is_bookable`), so "bookable staff" is `tenant->users()->bookable()`, never `operators()`.
- `EnsureUserIsActive` (web group) logs out disabled accounts and members of suspended portals.

## Password reset requests do not disclose account existence
Fortify success and failure reset-link responses use PasswordResetLinkRequestedResponse for identical web status and JSON 200/message across existing, unknown and recently contacted emails. Keep broker token generation, email delivery, validation and token checks intact. Do not reintroduce passwords.user/passwords.throttled differences at this public endpoint; PasswordResetTest covers all three broker outcomes and both response formats.

## Limiti dei piani e scadenza: un solo punto di lettura
I limiti si leggono da App\Services\PlanUsage ($tenant->planUsage()), mai confrontando a mano plan->max_*. Servizi e operatori (account role default, manager escluso) sono limiti duri: ActionsServicesSaveService (dentro AgendaLock) e ActionsStaffSaveOperator lanciano ValidationException su name. Le prenotazioni mensili (created_at dal primo del mese nel fuso del portale, annullate incluse) sono solo una soglia: avvisi all'80% e al 100%, la pagina pubblica non blocca mai (decisione dell'utente del 2026-10-04). SMTP proprio: Tenant::hasCustomMailer() include planAllows('custom_smtp'). Nessun piano = nessun limite. Scadenza e pagamenti non esistono più qui (colonne paid_until e subscription_* tolte il 2026-11-18): date, tolleranza e pagamenti sono del License Manager. L'unico potere manuale dell'admin su un portale è la sospensione (`tenants.is_active`, pulsante nel dettaglio portale): blocca tutto — pagine pubbliche 404, utenti fuori — anche con una licenza valida, e una risposta del License Manager non la riapre.

## Pint riscrive `@var Resource` in `@var resource`
Il fixer `phpdoc_types` di Pint scambia il model `App\Models\Resource` per il tipo primitivo PHP `resource` e lo scrive minuscolo: il PHPDoc diventa falso senza errori visibili. Per un parametro di rotta usa `instanceof Resource` invece di `/** @var Resource $x */`; nei tipi di firma (non PHPDoc) il problema non c'è.

## Raggruppare risultati Eloquent con toBase()
`->get()->groupBy(...)` su Eloquent dà a Larastan una `Eloquent\Collection` di `Eloquent\Collection`, tipo generico non valido: passarla a un metodo tipizzato rompe PHPStan (argument.type / generics.notSubtype). Usa `->get()->toBase()->groupBy(...)` e tipizza il parametro `Collection<array-key, Collection<int, Model>>` (Support). Visto in `App\Support\Agenda::calendar()`.
