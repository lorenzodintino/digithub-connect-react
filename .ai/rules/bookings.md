---
paths:
  - 'app/Actions/Bookings/**'
---

# Bookings

## Scrivere prenotazioni: lock sull'operatore
Ogni scrittura che occupa uno slot deve, **dentro la transazione**, prendere il lock sulla riga dell'operatore prima di ricontrollare la disponibilità:

```php
User::query()->whereKey($operator->getKey())->lockForUpdate()->first();
```

Senza, due richieste possono entrambe leggere lo slot come libero e inserire: non esiste una riga su cui il database possa andare in conflitto, quindi la sola transazione non basta (è un phantom read, permesso anche a REPEATABLE READ). Su SQLite non si nota perché le scritture sono già serializzate; su MySQL/Postgres sì.

`CreateBooking` e `RescheduleBooking` lo fanno entrambe. Qualunque nuova azione che crea o sposta prenotazioni deve fare lo stesso, altrimenti corre contro quelle esistenti.

Non usare un indice unico su `(user_id, starts_at)`: bloccherebbe la riprenotazione di uno slot dopo un annullamento.

## Consenso privacy e cancellazione dei dati del cliente
Ogni prenotazione pubblica richiede la spunta del consenso (`privacyAccepted`, regola `accepted`) e salva **la prova**: `privacy_accepted_at` più `privacy_accepted_url`, cioè il documento in vigore in quel momento. Un portale che cambia informativa non riscrive i consensi vecchi.

- La cancellazione passa sempre da `Actions\Bookings\AnonymiseCustomer`: svuota nome, email, telefono e note e marca `anonymised_at`. **La riga della prenotazione resta**, altrimenti storico e conteggi mentirebbero.
- Nei template non stampare `customer_name` a crudo: usa `displayCustomerName()`, che mostra «Cliente cancellato» quando i dati sono stati rimossi.
- Un cliente con prenotazioni future bloccanti **non** si cancella: prima si annullano, altrimenti resta un appuntamento senza recapiti.
- La retention è per portale (`tenants.data_retention_days`, null = nessuna scadenza) e la applica `bookings:anonymise-old`, pianificato ogni notte.

## Conferma e annullamento con risorse condivise
Conferma e annullamento usano ora AgendaLock::forTenant e rileggono la prenotazione dentro la transazione prima di validarne lo stato. La vecchia eccezione che permetteva queste scritture senza lock non vale più: una conferma basata su un modello obsoleto non deve riattivare una prenotazione annullata. Creazione e spostamento ricontrollano la disponibilità e salvano le allocazioni nella stessa transazione. Una prenotazione con service_session_id non si sposta, anche se la capacità attuale del servizio è stata cambiata.
