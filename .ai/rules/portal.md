---
paths:
  - 'resources/js/pages/portal/**'
  - 'app/Http/Controllers/Portal/**'
  - 'app/Support/SlotCalendar.php'
  - 'app/Support/PortalPage.php'
---

# Portal

## Pagine pubbliche: stato nell'URL, brand e embed da PortalPage
Le pagine `/p/…` sono React (`resources/js/pages/portal/*`, layout `PortalLayout` scelto in `app.tsx` per i nomi `portal/…`) con i controller in `app/Http/Controllers/Portal/`. Lo stato del calendario sta nella query string — `month` (Y-m), `operator`, `d` (giorno), `tz` (fuso del visitatore), `reschedule=1` sulla pagina della prenotazione — così un link si condivide e il tasto indietro funziona; la scelta dell'orario e il modulo sono stato del client. Ogni controller chiama `PortalPage::speak()` (lingua del portale se il visitatore non ne ha scelta una) e passa `PortalPage::layout()` come prop `portal`. Con `?embed=1` la pagina vive in un iframe: ogni URL generato dal server per link e azioni passa da `PortalPage::keepEmbed()`, altrimenti il primo click riporta header e footer dentro il sito del cliente.

## Le azioni pubbliche vanno limitate a mano
Sulle pagine pubbliche non c'è login da cui dipendere: qualunque azione che **scrive** va limitata esplicitamente.

- Prenotazione e iscrizione alla lista d'attesa passano da `PortalPage::throttle()`: due bucket per IP e servizio, 5 al minuto e 20 all'ora. Superato il limite è un errore di validazione (`slot` per la prenotazione, `email` per la lista), non un 429: il visitatore resta sulla pagina.
- Annullamento e spostamento partono dalla pagina con token non indovinabile, quindi non hanno limite.
- Portale sospeso: le pagine pubbliche fanno 404, ma il link di gestione **resta aperto** — il cliente deve poter annullare. Lì spostare è vietato (`BookingPageController::reschedule` risponde 403, `?reschedule=1` non apre il calendario) e un avviso lo spiega. Un posto in una classe non si sposta (422).

## The public calendar covers the drawn grid, not the month
`Week::monthMatrix()` always paints a few days of the neighbouring months. Slots must be fetched for the whole grid (first cell → last cell), otherwise those days look bookable and never are — on the last day of a month the visitor sees an empty calendar. `SlotCalendar::range()` gives that range; both the service page and the reschedule use it.

## Il calendario pubblico deve dire perché un giorno non si può scegliere
Un giorno grigio si legge uguale per tre cause diverse — oltre la finestra di prenotabilità, portale chiuso, nessuno disponibile — e il visitatore le legge tutte come «chiuso». `App\Support\CalendarNotes::forService()` è l'unico posto dove si scrivono quelle righe; arrivano nella prop `calendar.notes` di `SlotCalendar::month()`, sia sulla pagina del servizio sia su quella di spostamento.

- La riga della finestra si stampa **solo** se la griglia disegna giorni oltre `max_advance_days`: su un mese tutto dentro sarebbe rumore.
- Delle chiusure escono **solo le date**, mai `PortalClosure::reason`: quel testo lo scrive il manager per la propria agenda.
- Il fuso del visitatore diverso da quello del portale va detto (`The portal works on :zone time.`), e nel modulo si stampano i due orari affiancati (`portal_hours` di ogni orario) invece di una frase: nessuna traduzione da mantenere e gli scostamenti di mezz'ora tornano da soli.

Scarsità di posti (servizi di gruppo): si segnala sulla **fascia oraria**, dove il dato è esatto; un giorno si marca solo se **tutte** le sue fasce sono corte (`nearlyFullDays` in `ServicePageController`). «Pochi posti» = `Service::seatsRunningLow()`, un quarto della sala, minimo uno. Il colore del servizio non è un segnale di stato: un `color` caldo su una card è stato letto come allarme il 2026-09-09.

## Waiting list email identity ignores casing
Normalise incoming waiting-list email with Customer::normaliseEmail before validation (`JoinWaitingListRequest::prepareForValidation`); match legacy casing only within tenant/service and reuse its record. NotifyWaitingList groups legacy duplicates by the same identity, honours a recent notification for the whole group, and sends once without deleting old unsubscribe tokens.
