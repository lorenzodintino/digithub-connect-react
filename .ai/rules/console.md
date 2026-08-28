---
paths:
  - 'app/Console/**'
---

# Console

## Gli appuntamenti confermati si chiudono da soli dopo 24 ore
`bookings:complete-past` (orario, `routes/console.php`) porta a `Completed` le prenotazioni **solo `Confirmed`** finite da più di `CompletePastBookings::GRACE_HOURS` (24). Le `Pending` restano tali — non sono mai state accettate — e `Cancelled` / `Completed` / `NoShow` hanno già il loro esito.

- La finestra di grazia serve a lasciare tempo per marcare *assente* a mano prima che l'automatismo parli; dopo, i due esiti restano intercambiabili dall'agenda (`acceptsOutcome()` guarda solo "finito e non annullato").
- Aggiornamento in blocco, niente eventi e niente email: lo slot è nel passato e il cliente non ha più nulla da sapere.
- Come per i promemoria, il comando chiede *tutto ciò che è già dovuto*: una esecuzione saltata ritarda il cambio, non lo perde.
