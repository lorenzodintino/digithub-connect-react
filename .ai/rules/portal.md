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

## Il calendario pubblico deve dire perché un giorno non si può scegliere
Un giorno grigio si legge uguale per tre cause diverse — oltre la finestra di prenotabilità, portale chiuso, nessuno disponibile — e il visitatore le legge tutte come «chiuso». `App\Support\CalendarNotes::forService()` è l'unico posto dove si scrivono quelle righe; la usano sia la pagina del servizio sia quella di spostamento, tramite `@include('partials.booking-calendar', ['notes' => ...])`.

- La riga della finestra si stampa **solo** se la griglia disegna giorni oltre `max_advance_days`: su un mese tutto dentro sarebbe rumore.
- Delle chiusure escono **solo le date**, mai `PortalClosure::reason`: quel testo lo scrive il manager per la propria agenda.
- Il fuso del visitatore diverso da quello del portale va detto (`The portal works on :zone time.`), e nel modulo si stampano i due orari affiancati invece di una frase: nessuna traduzione da mantenere e gli scostamenti di mezz'ora tornano da soli.

Scarsità di posti (servizi di gruppo): si segnala sulla **fascia oraria**, dove il dato è esatto; un giorno si marca solo se **tutte** le sue fasce sono corte (`nearlyFullDays`). «Pochi posti» = `Service::seatsRunningLow()`, un quarto della sala, minimo uno. Il colore del servizio non è un segnale di stato: un `color` caldo su una card è stato letto come allarme il 2026-09-09.

## Waiting list email identity ignores casing
Normalise incoming waiting-list email with Customer::normaliseEmail before validation; match legacy casing only within tenant/service and reuse its record. NotifyWaitingList groups legacy duplicates by the same identity, honours a recent notification for the whole group, and sends once without deleting old unsubscribe tokens.
