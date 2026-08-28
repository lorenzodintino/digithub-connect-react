---
paths:
  - 'app/Models/**'
---

# Models

## services.color null = segue il brand del portale
`services.color` è nullable: **null significa "usa `tenant.brand_color`"**, non "colore mancante". Prima veniva copiato dal brand alla creazione, quindi cambiare il brand lasciava i servizi col vecchio colore.

- Nei template non leggere mai `$service->color`: usa `$service->displayColor()` (fallback sul brand).
- `displayColor()` tocca la relazione `tenant`, quindi ogni query che alimenta una lista di servizi o prenotazioni deve fare eager load: `->with('tenant')` / `->with('service.tenant')`.
- Il form servizi espone lo switch `use_brand_color`: attivo → salva `color = null`; spento → richiede un hex valido.

## Eliminare qualcosa cancella a cascata le prenotazioni: controlla prima
`bookings.tenant_id`, `service_id` e `user_id` sono tutti `cascadeOnDelete`. Eliminare un portale, un servizio o un account porta via anche le prenotazioni, in silenzio e senza avvisare i clienti.

- `Tenant`, `Service` e `User` espongono `upcomingBookingsCount()` (prenotazioni in `BookingStatus::blocking()` con `starts_at` futuro).
- Ogni flusso di eliminazione deve chiamarlo: servizi e account **rifiutano** finché il numero è > 0; il portale avvisa soltanto (l'admin lo elimina di proposito).
- Nel modale di conferma mostra il numero e disabilita il pulsante: il controllo nel metodo resta comunque, è quello che vale.
