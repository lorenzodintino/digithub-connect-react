---
paths:
  - 'app/Support/**'
  - app/Support/Activity.php
---

# Support

## Liste condivise in app/Support, mai inline
Le liste riusate da più schermate vivono in `app/Support`, non copiate nei Blade:

- `App\Support\Timezones::options()` — l'unico elenco di fusi orari. Usalo in ogni `<flux:select>` e nelle regole con `Rule::in(Timezones::options())`. Era duplicato in 3 pagine, ora centralizzato.
- `App\Support\Week` — giorni della settimana in ordine Lun→Dom (`ordered()`, `labels()`, `label()`) e formattazione `H:i` (`time()`). Il numero del giorno segue `dayOfWeek` di Carbon: domenica = 0.

L'elenco dei fusi è volutamente curato e non l'intero database IANA: la select di Flux free non ha campo di ricerca.

## Ricerche testuali: App\Support\Search::contains, mai LIKE a mano
`Search::contains($query, ['col_a', 'col_b'], $term)` è l'unico modo di filtrare per testo digitato dall'utente. Costruire `'%'.$term.'%'` a mano lascia `%` e `_` come jolly: cercare `%` restituiva tutto.

- L'escape usa `!` come carattere di escape (`ESCAPE '!'`), non il backslash: MySQL lo mangia dentro il letterale stringa, SQLite no.
- I nomi di colonna devono essere letterali nel codice (`@param array<int, literal-string>`), mai valori che arrivano dalla richiesta.
- Lo usano oggi: agenda prenotazioni, elenco account admin, elenco portali admin.

## Registro: si scrive dalle azioni, e non diventa un archivio di dati personali
`App\Support\Activity::log()` è l'unico punto che scrive in `activity_logs`. Va chiamato dalle **azioni di dominio** (e dai Form object per servizi/piani), non dai componenti: la stessa prenotazione si annulla dall'agenda, dal link del cliente e da un comando, e il registro deve leggersi uguale nei tre casi.

- Nel `context` vanno **riferimenti e numeri**, mai nomi, email o testo libero dei clienti: il registro non deve diventare una seconda copia dei dati personali che la retention (5.1) cancella altrove. L'unica eccezione voluta è `auth.failed`, che tiene l'email digitata perché senza non è azionabile.
- `subject_label` è la fotografia leggibile del soggetto: per una prenotazione è lo `uid`, non il cliente.
- `actor_name` e `actor_role` sono copiati: un account eliminato non deve trasformare la sua storia in righe vuote (`user_id` è `nullOnDelete`).
- Le azioni che girano anche senza sessione (link pubblico, comandi) passano l'attore esplicito: `Activity::log(..., actor: $actor)`.
- I listener in `app/Listeners` sono **auto-registrati** da Laravel: registrarli anche a mano in un provider raddoppia le righe.
