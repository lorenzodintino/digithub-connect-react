---
paths:
  - 'resources/views/components/**'
---

# Components

## Dentro un update Livewire, request() è /livewire/update — non costruirci URL
Quando Livewire ri-renderizza un componente, la richiesta HTTP in corso è il suo endpoint interno (`POST /livewire-<hash>/update`), non la pagina. Qualsiasi `request()->fullUrl()`, `request()->url()` o `url()->current()` dentro un Blade renderizzato in quel momento produce link verso `/livewire-<hash>/update`: cliccandoli il browser fa GET e prende **405 Method Not Allowed**.

Successo davvero con `<x-locale-switcher>`: bastava una qualunque azione Livewire sulla pagina (cambio fuso, "Sposta") e poi il cambio lingua moriva.

- Usa `Livewire::isLivewireRequest() ? Livewire::originalUrl() : request()->fullUrl()`: lo snapshot conosce il path della pagina vera.
- `originalUrl()` restituisce il path senza query, quindi se i parametri contano (filtri `#[Url]`, `?d=`) ricostruisci l'URL lato client da `window.location` — è l'unico posto che conosce la query aggiornata da `wire:model.url`.
- Regressione coperta da `tests/Feature/LocaleSwitcherTest.php`, che fa un vero POST all'endpoint di update: `Livewire::test()` non basta, il suo path è finto (`/livewire-unit-test-endpoint/...`).
