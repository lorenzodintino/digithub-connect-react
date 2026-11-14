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

## Il primo admin in produzione si crea con platform:install, mai con db:seed
`DatabaseSeeder` crea un admin con password di test e un portale demo: solo per sviluppo. Su un server vero si usa `php artisan platform:install` (piani via `PlanSeeder`, idempotente con `firstOrCreate` sul nome; password sempre chiesta e nascosta, mai opzione; rifiuta di girare se esiste già un admin). I piani li definisce solo `PlanSeeder`: `DatabaseSeeder` lo richiama, non ne ha una copia.
