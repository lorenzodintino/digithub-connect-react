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
