---
paths:
  - 'app/Services/**'
---

# Services

## SlotFinder: fuso del portale, UTC nel database
`App\Services\SlotFinder` è l'unica fonte di verità sugli slot prenotabili. Non duplicare la logica altrove: se serve sapere se un orario è libero, usa `canBookAt()`.

Regole del tempo (decise il 2026-08-26):
- Gli orari settimanali si interpretano **sempre nel fuso del portale** (`tenant->timezone`), mai in quello dell'operatore o del visitatore.
- Tutto ciò che va nel database è **UTC**: `bookings.starts_at`, `time_blocks.starts_at`, e i valori restituiti dal finder. La conversione al fuso del visitatore avviene solo nelle view.
- Ogni prenotazione salva anche `blocked_starts_at` / `blocked_ends_at`, cioè la finestra con i buffer del servizio applicati. Sono uno snapshot: cambiare i buffer di un servizio non sposta le prenotazioni già prese.
- Le prenotazioni che occupano uno slot sono quelle in `BookingStatus::blocking()`. Una annullata libera lo slot.
- Chi sposta una prenotazione deve usare `->ignoring($booking)`, altrimenti la prenotazione blocca sé stessa.

Trappola già incontrata: `availability_exceptions.date` è salvata come `Y-m-d H:i:s`, quindi va confrontata con `whereDate()` e mai con stringhe `Y-m-d` in `whereBetween`.

## Servizi di gruppo: la sessione è l'appuntamento
`services.capacity > 1` (`Service::isGroup()`) cambia il modello: l'appuntamento non è più fra un cliente e un operatore, è una **sessione** (`service_sessions`) con i suoi posti e il suo staff.

- `GroupSlotFinder` decide gli orari: una sessione viva con posti liberi si offre così com'è, una piena toglie del tutto quell'orario, e dove non c'è ancora nessuna sessione basta **un** operatore libero per aprirla.
- La sessione nasce alla prima prenotazione e prende **tutti** gli operatori liberi in quel momento; il manager può poi aggiungerne o toglierne dall'agenda, mai lasciandone zero.
- `SlotFinder::busyPeriods()` conta anche le sessioni dell'operatore: chi tiene una lezione è occupato per qualunque altro servizio.
- Concorrenza: niente `AgendaLock`, si blocca la **riga della sessione** (`lockForUpdate`) perché l'ultimo posto è una riga che esiste già; la prima prenotazione è serializzata dall'unico `unique(service_id, starts_at)`, e chi perde la corsa rilegge la sessione appena aperta.
- `bookings.user_id` resta valorizzato con l'operatore "primario" della sessione: agenda, email e `.ics` continuano a funzionare senza sapere nulla dei gruppi. Togliendolo dalla sessione, le sue prenotazioni passano a chi resta.
- Una prenotazione di gruppo **non si sposta**: si annulla e si riprenota (`RescheduleBooking` rifiuta con 422).

## Risorse condivise: disponibilità e ordine dei lock
SlotFinder verifica operatore e tutti i requisiti attraverso ResourceAvailability, con un preload per intervallo e confronti stretti: toccarsi non è sovrapporsi. Una risorsa si occupa durante l'appuntamento più la sua preparazione e il suo riassetto; i buffer del servizio restano dell'operatore. Ogni scrittura concorrente su agenda, risorse e requisiti usa AgendaLock: prima lock sul portale, poi su operatori e sessioni. forOperator prende anche il lock del portale; i gruppi usano forTenant anche per la prima sessione, quindi non dipendono più dalla sola violazione unique. Su SQLite servono transaction_mode IMMEDIATE e busy_timeout 5000; forUpdate non ha effetto. Le email restano fuori dalla transazione.

## Pacchetti: fasi, pause e disponibilità
Un pacchetto contiene almeno due servizi individuali distinti dello stesso portale e un unico operatore abilitato a tutti. PackagePlan combina durata/pause, prezzo somma o specifico e vincoli dei componenti; SlotFinder verifica la finestra di ogni fase. L'operatore resta occupato anche nelle pause. ResourceAvailability assegna risorse per fase, con preparazione/riassetto, ammettendo cambio cabina e riuso solo senza conflitti; prova le alternative compatibili invece di una scelta greedy. Gli spostamenti ricostruiscono ordine, durata, pause e prezzo dalle booking_phases, poi ricontrollano le risorse attuali.
