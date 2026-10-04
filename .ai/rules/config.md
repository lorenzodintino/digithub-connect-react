---
paths:
  - 'config/**'
---

# Config

## SQLite resta il database di produzione, a condizioni precise
Deciso il 2026-10-04: in produzione si resta su SQLite. Condizioni: un solo server con disco locale persistente (niente più macchine, dischi di rete o filesystem effimeri); connessione sqlite con transaction_mode IMMEDIATE, busy_timeout 5000 e journal_mode wal; SESSION_DRIVER e CACHE_STORE su file, così le pagine non scrivono nel database; backup con VACUUM INTO o Litestream, mai copiando il file a caldo. IMMEDIATE è ciò che impedisce le doppie prenotazioni, perché su SQLite lockForUpdate non ha effetto: con DEFERRED le richieste simultanee falliscono con "database is locked". tests/Feature/BookingConcurrencyTest.php lo verifica con processi paralleli veri. Passare a MySQL/Postgres solo se serviranno più server.
