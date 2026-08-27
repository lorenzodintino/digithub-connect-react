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
