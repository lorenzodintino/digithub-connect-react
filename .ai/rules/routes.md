---
paths:
  - 'routes/**'
---

# Routes

## Route file split and public portal URLs
`routes/web.php` only holds `/` and `/dashboard`, then requires: `admin.php` (platform admin, `role:admin`), `app.php` (portal back-office, `role:manager` / `role:manager,default`), `booking.php` (public), `settings.php`.

Public URLs are path-based: `/p/{tenant:slug}` and `/p/{tenant}/{service}` (with `->scopeBindings()`), names `portal.show` / `portal.service`. `?embed=1` drops the header/footer for iframe embedding. Subdomains are deliberately not used.

Public self-registration is disabled (`Features::registration()` commented out in `config/fortify.php`); accounts are created by the admin (managers) and by managers (operators). Guard any `route('register')` link with `@if (Route::has('register'))`.
