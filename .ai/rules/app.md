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
