---
paths:
  - 'app/Models/**'
  - app/Models/Customer.php
  - app/Models/PortalClosure.php
  - app/Models/ServiceQuestion.php
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

## Anagrafica clienti: chiave email per portale, la prenotazione resta una fotografia
`customers` è l'anagrafica **per portale**: chiave `unique(tenant_id, email)` con l'email normalizzata da `Customer::normaliseEmail()` (trim + minuscolo). Due portali non condividono mai un cliente: sono titolari del trattamento distinti.

- Le prenotazioni **continuano a salvare** `customer_name`, `customer_email`, `customer_phone`: sono ciò che il cliente ha detto quel giorno, e non devono cambiare quando l'anagrafica si aggiorna. `customer_id` è solo il collegamento.
- `CreateBooking::resolveCustomer()` fa `updateOrCreate` sull'email: nome, telefono, fuso e lingua seguono l'ultima prenotazione; `notes` è del portale e non si tocca mai da lì.
- La cancellazione (`AnonymiseCustomer`) **elimina la riga anagrafica** oltre a svuotare le prenotazioni: tenere nome e recapiti di chi ha chiesto l'oblio vanificherebbe tutto. `bookings.customer_id` è `nullOnDelete`.
- La pagina Clienti è **solo del manager** (`CustomerPolicy`): l'operatore vede i clienti solo attraverso la propria agenda.

## Chiusure del portale: giornate intere, valgono per tutti
`portal_closures` toglie **giornate intere a tutto il portale**, sopra agli orari dei singoli. Le date si leggono nel fuso del portale (`starts_on`/`ends_on`, estremi compresi), e `SlotFinder::closedDates()` salta quei giorni prima ancora di guardare gli orari dell'operatore.

Distinzione da tenere ferma:
- `availability_exceptions` = **una persona**, un giorno, orari sostituiti;
- `time_blocks` = **una persona**, una fascia oraria sottratta;
- `portal_closures` = **tutta l'attività**, giornate intere.

`Actions\Availability\CloseThePortal` rifiuta la chiusura se dentro il periodo c'è una prenotazione bloccante (`PeriodHasBookingsException`, come `BlockTime`): prima si annullano, poi si chiude. Niente `AgendaLock` qui — non si sottrae uno slot a un operatore, si spengono giorni interi, e in caso di corsa la chiusura fallisce e si riprova.

## Domande personalizzate: la risposta porta con sé la domanda
`service_questions` sono le domande extra di un servizio (max `ServiceQuestion::LIMIT` = 10). Le risposte finiscono in `booking_answers` **con l'etichetta copiata**: riformulare o cancellare una domanda non deve cambiare ciò che una prenotazione vecchia dice di aver chiesto. `service_question_id` è `nullOnDelete`, la risposta sopravvive.

- Le opzioni si scrivono una per riga nel form e si salvano come array JSON; solo i tipi `single_choice` e `multiple_choice` le usano.
- La scelta multipla si salva come **una riga leggibile** (`"Tasse, Altro"`): serve a essere letta in agenda e nelle email, non a essere interrogata.
- Una risposta vuota non crea riga: la prenotazione porta ciò che è stato detto, non il silenzio.
- Le risposte sono dati del cliente: `AnonymiseCustomer` le cancella insieme al resto.
- Livewire **non** chiama metodi di un Form object dal markup: `addQuestion()` / `removeQuestion()` esistono anche sulla pagina, che li inoltra al form.

## Risorse fisiche e requisiti alternativi dei servizi
Ogni risorsa fisica appartiene a un portale ed è un record distinto. Un servizio ha più resourceRequirements: tutti sono necessari, mentre dentro ciascuno le resources sono alternative (se ne assegna una). La stessa risorsa non può comparire in più requisiti del servizio. Nei PHPDoc usare \App\Models\Resource qualificato: Pint trasforma Resource non qualificato nel tipo PHP resource e rompe PHPStan. I periodi resource_blocks si inseriscono nel fuso del portale e si salvano in UTC; eliminare una risorsa collegata a servizi è vietato, disattivarla conserva i collegamenti.

## Allocazioni delle risorse: snapshot e sessioni
resource_allocations salva nomi, requisito, preparazione/riassetto e finestra UTC. Ogni allocazione appartiene a una prenotazione individuale oppure a una sessione di gruppo; ogni posto di gruppo conserva il riferimento alle allocazioni condivise in booking_resource_allocation, anche dopo annullamento e riapertura della sessione. blocking richiede released_at null e un proprietario con prenotazioni bloccanti. L'ultimo annullamento libera la sessione; la riapertura ricontrolla persone/risorse e crea nuovi snapshot. Gli spostamenti rilasciano e riassegnano atomicamente. Catalogo e requisiti non riscrivono lo storico; alle prenotazioni precedenti non si assegnano risorse retroattivamente. Disattivazione, rimozione e manutenzione rifiutano occupazioni future, incluso preparazione/riassetto.

## Snapshot dei pacchetti e guardie sui componenti
booking_phases conserva nome del servizio, prezzo, durata, buffer, ordine, pausa e orari UTC. Le resource_allocations della prenotazione puntano alla fase; la cancellazione rilascia tutte le risorse insieme e conserva gli snapshot. Le domande dei componenti sono aggregate con etichetta contestuale insieme a quelle del pacchetto. Un componente usato in un pacchetto o in fasi di prenotazioni future deve restare individuale; eliminazione e cambi di tipo passano dalle guardie sotto AgendaLock. Prezzo nelle pagine di gestione e nelle email viene da Booking, non dal catalogo corrente.
