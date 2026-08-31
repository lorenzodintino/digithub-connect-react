---
paths:
  - 'resources/views/pages/portal/**'
---

# Portal

## Le azioni pubbliche vanno limitate a mano
Sulle pagine pubbliche non c'è né login né CSRF-per-utente da cui dipendere: qualunque metodo che **scrive** va limitato esplicitamente con `RateLimiter`.

- `portal.service::book()` conta due bucket per IP e servizio: 5 al minuto e 20 all'ora. Superato il limite aggiunge un errore su `selectedSlot`, non un 429: il visitatore resta sulla pagina.
- Annullamento e spostamento partono dalla pagina con token non indovinabile, quindi non hanno limite.
- Portale sospeso: la pagina pubblica fa 404, ma il link di gestione **resta aperto** — il cliente deve poter annullare. Lì spostare è vietato (`startReschedule` e `selectSlot` abortiscono 403) e un avviso lo spiega.

## The public calendar covers the drawn grid, not the month
`Week::monthMatrix()` always paints a few days of the neighbouring months. Slots must be fetched for the whole grid (first cell → last cell), otherwise those days look bookable and never are — on the last day of a month the visitor sees an empty calendar. See `drawnRange()` in ⚡service.blade.php.
