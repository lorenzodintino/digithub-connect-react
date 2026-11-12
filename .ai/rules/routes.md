---
paths:
  - 'routes/**'
---

# Routes

## Route file split and public portal URLs
`routes/web.php` only holds `/` and `/dashboard`, then requires: `admin.php` (platform admin, `role:admin`), `app.php` (portal back-office, `role:manager` / `role:manager,default`), `booking.php` (public), `settings.php`.

Public URLs are path-based: `/p/{tenant:slug}` and `/p/{tenant}/{service}` (with `->scopeBindings()`), names `portal.show` / `portal.service`. `?embed=1` drops the header/footer for iframe embedding. Subdomains are deliberately not used.

Public self-registration is disabled (`Features::registration()` commented out in `config/fortify.php`); accounts are created by the admin (managers) and by managers (operators). Guard any `route('register')` link with `@if (Route::has('register'))`.

## Servizi nel back-office: {ownService}, mai {service}
Lo slug dei servizi è unico per portale, non sulla piattaforma: il binding implicito `{service}` prende la prima riga con quello slug in tutta la tabella e può consegnare a un manager il servizio di un altro portale (403 o, peggio, dati altrui). Le rotte del manager usano `{ownService}`, definito in `AppServiceProvider::bindOwnService()`: cerca solo nel portale di chi è collegato e risponde 404 altrimenti. `{service}` resta solo sulle rotte pubbliche `/p/{tenant}/{service}` con `scopeBindings()`. Test: `ServiceManagementTest` «two portals may share a service slug».
