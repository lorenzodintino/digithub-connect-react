---
paths:
  - 'resources/js/**'
---

# Js

## Pagine React: testi, link e date
Testi: `__()` e `trans_choice()` da `@/lib/i18n`, chiave = testo inglese, valori italiani nello stesso `lang/it.json` dei Blade (aggiungi la chiave in ordine). Conteggi sempre con trans_choice. Link interni: `<Link>` di Inertia con le rotte Wayfinder (`@/routes/...`). `<a>` semplice solo per download, ancore `#`, link esterni e cambio lingua (ricarica con `?lang=`). Un errore durante una visita Inertia non apre la finestra di errore di Inertia: `AppServiceProvider::handleInertiaErrors()` manda le pagine (GET) alla pagina di errore Blade con una visita completa e rimanda le azioni (POST/PUT/DELETE) indietro con un toast. Date e orari arrivano già formattati dal server nel fuso di chi guarda (`translatedFormat`), non formattarli in JS. Pagine in `resources/js/pages`, layout di default `AppLayout` da `app.tsx`.

## Filtri nell'URL e dettagli aperti da query string
Filtri di lista: `useFilters(url, props.filters, defaults)` da `@/hooks/use-filters` (visita 300 ms dopo l'ultimo tasto, valori di default fuori dall'URL; il server li ripulisce, vedi `Controller::filter()` o `App\Support\Agenda::fromRequest()`). Per click che non devono aspettare (vista, giorno, frecce del calendario) usa il terzo valore `apply({...})`, che visita subito senza far partire anche il debounce. Una finestra di dettaglio che serve dati in più (es. agenda `?booking=uid`) si apre con `router.get(url, {...query, booking}, { only: ['details'], preserveState: true, replace: true })`: il controller autorizza e popola la prop solo se il parametro c'è, e i `back()` delle azioni la ricaricano aggiornata.
