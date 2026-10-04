---
paths:
  - 'app/Actions/**'
---

# Actions

## Agenda: ogni scrittura passa da AgendaLock
Qualsiasi scrittura che **occupa o sottrae** tempo nell'agenda di un operatore deve girare dentro `App\Services\AgendaLock::forOperator()`. Il helper apre la transazione e prende `lockForUpdate()` sulla riga dell'operatore, poi tu ricontrolli la disponibilità **dentro** il callback.

Lo usano oggi:
- `Actions\Bookings\CreateBooking` — ricontrolla con `SlotFinder::canBookAt()`
- `Actions\Bookings\RescheduleBooking` — stesso controllo, con `->ignoring($booking)`
- `Actions\Availability\BlockTime` — rifiuta con `PeriodHasBookingsException` se il periodo contiene prenotazioni attive

Perché il lock sta sulla riga dell'operatore e non sulle prenotazioni: il conflitto riguarda uno slot che **non esiste ancora**, quindi non c'è riga su cui il database possa collidere e la sola transazione lascia passare entrambi gli scrittori (phantom read). Su SQLite non si nota, su MySQL/Postgres sì.

Non serve il lock dove si **libera** tempo (`CancelBooking`) o dove non cambia l'occupazione (`ConfirmBooking`): al massimo un altro scrittore vede lo slot ancora occupato e riprova.

## Lock del portale anche per conferme e annullamenti
La precedente eccezione per CancelBooking e ConfirmBooking è superata dall'introduzione delle risorse condivise: entrambe passano da AgendaLock::forTenant e rileggono lo stato dentro la transazione. forOperator acquisisce prima il portale e poi l'operatore. Anche i requisiti dei servizi e le modifiche che possono rendere indisponibile una risorsa devono rispettare il lock del portale; le email si inviano dopo il commit.
