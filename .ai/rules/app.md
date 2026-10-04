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
I limiti si leggono da App\Services\PlanUsage ($tenant->planUsage()), mai confrontando a mano plan->max_*. Servizi e operatori (account role default, manager escluso) sono limiti duri: ServiceForm::store (dentro AgendaLock) e StaffForm::store lanciano ValidationException su form.name. Le prenotazioni mensili (created_at dal primo del mese nel fuso del portale, annullate incluse) sono solo una soglia: avvisi all'80% e al 100%, la pagina pubblica non blocca mai (decisione dell'utente del 2026-10-04). SMTP proprio: Tenant::hasCustomMailer() include planAllowsCustomMailer(). Nessun piano = nessun limite. Scadenza: tenants.paid_until si scrive solo con Actions\Tenants\SetPaidUntil (non fillable). Il comando portals:check-subscriptions manda promemoria a 14 e 3 giorni, uno all'inizio della tolleranza (Tenant::SUBSCRIPTION_GRACE_DAYS = 7) e poi sospende con subscription_suspended_at; SetPaidUntil riattiva solo i portali sospesi per mancato pagamento.
