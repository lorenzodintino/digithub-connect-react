---
paths:
  - 'app/Actions/Availability/**'
---

# Availability

## Togliere disponibilità: rifiuta, non lasciare incoerenze
Ci sono tre modi di togliere disponibilità a un operatore, e passano tutti da qui. **Due rifiutano, uno avvisa** — la differenza è deliberata.

| Azione | Cosa toglie | Controllo |
|---|---|---|
| `BlockTime` | una fascia di un giorno | rifiuta se **una prenotazione attiva si sovrappone** alla fascia |
| `SetDateOverride` con `is_unavailable` | l'intera giornata | rifiuta se **esiste una prenotazione** in quel giorno |
| `SetDateOverride` con orari custom | tutto ciò che sta fuori dai nuovi orari | rifiuta solo le prenotazioni che finirebbero **fuori** dalla nuova finestra |

Queste due girano dentro `AgendaLock::forOperator()` e lanciano `PeriodHasBookingsException` con il conteggio; le pagine Livewire lo trasformano in un errore di validazione sul campo data.

Dettagli che i test presidiano e che è facile rompere:
- il confronto usa **disuguaglianze strette**: toccarsi non è sovrapporsi (prenotazione 10:00–10:30, blocco dalle 10:30 = ok)
- per gli orari custom si confrontano `starts_at`/`ends_at` della prenotazione, **non** la finestra con i buffer: i buffer possono legittimamente sbordare fuori dall'orario, come quando lo slot è stato generato
- le prenotazioni annullate non ostacolano nulla (`scopeBlocking`)

## Orari settimanali: salva e avvisa, non rifiutare

`SaveWeeklySchedule` è il terzo modo, e si comporta **diversamente di proposito**. Gli orari settimanali sono una configurazione permanente, non una decisione puntuale: rifiutare l'intero salvataggio perché una prenotazione fra tre settimane cade fuori renderebbe la schermata inusabile.

Quindi salva, poi **restituisce** le prenotazioni future rimaste scoperte. La pagina le mostra in un avviso persistente (`uncoveredBookings`, computed), non in un toast che sparisce: il problema resta visibile finché non lo si risolve.

`uncoveredBookings()` è pubblico e riusabile: serve sia dopo il salvataggio sia al caricamento della pagina, così emergono anche gli scoperti creati in passato.

La copertura si valuta con `WorkingHours::cover()`, quindi un giorno con la propria eccezione **non** è influenzato dagli orari settimanali.

## Dove vivono gli orari

`App\Services\WorkingHours` risponde a una sola domanda: quando è aperto questo operatore in un dato giorno? Regole settimanali come base, eccezione di data che le sostituisce per quel singolo giorno. Finestre in ingresso nel fuso del portale, in uscita UTC.

La usano `SlotFinder` (per generare gli slot) e `SaveWeeklySchedule` (per valutare la copertura). Non duplicare quella logica altrove: era già dentro `SlotFinder` ed è stata estratta proprio perché serviva in due posti.
