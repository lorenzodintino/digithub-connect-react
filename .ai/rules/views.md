---
paths:
  - 'resources/views/**'
---

# Views

## Blade resta per tre cose sole
Dal 2026-10-07 l'interfaccia è tutta React (Inertia). In `resources/views` restano:

- `app.blade.php`: la vista radice di Inertia (tema chiaro/scuro prima del disegno, `@vite`, `<x-inertia::app />`).
- `errors/`: pagine di errore **autosufficienti**, CSS inline, niente `@vite`: devono leggersi anche quando gli asset sono rotti. Una visita Inertia che finisce in errore arriva qui con una visita completa (vedi `AppServiceProvider::handleInertiaErrors()`).
- `mail/`: i corpi delle email, stili inline.

Una schermata nuova è una pagina React in `resources/js/pages`, non una vista Blade.