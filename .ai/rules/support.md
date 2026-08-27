---
paths:
  - 'app/Support/**'
---

# Support

## Liste condivise in app/Support, mai inline
Le liste riusate da più schermate vivono in `app/Support`, non copiate nei Blade:

- `App\Support\Timezones::options()` — l'unico elenco di fusi orari. Usalo in ogni `<flux:select>` e nelle regole con `Rule::in(Timezones::options())`. Era duplicato in 3 pagine, ora centralizzato.
- `App\Support\Week` — giorni della settimana in ordine Lun→Dom (`ordered()`, `labels()`, `label()`) e formattazione `H:i` (`time()`). Il numero del giorno segue `dayOfWeek` di Carbon: domenica = 0.

L'elenco dei fusi è volutamente curato e non l'intero database IANA: la select di Flux free non ha campo di ricerca.
