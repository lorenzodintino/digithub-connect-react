---
paths:
  - 'app/Models/**'
  - app/Models/Customer.php
  - app/Models/PortalClosure.php
  - app/Models/ServiceQuestion.php
  - app/Models/Plan.php
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

## Risorse fisiche e requisiti alternativi dei servizi
Ogni risorsa fisica appartiene a un portale ed è un record distinto. Un servizio ha più resourceRequirements: tutti sono necessari, mentre dentro ciascuno le resources sono alternative (se ne assegna una). La stessa risorsa non può comparire in più requisiti del servizio. Nei PHPDoc usare \App\Models\Resource qualificato: Pint trasforma Resource non qualificato nel tipo PHP resource e rompe PHPStan. I periodi resource_blocks si inseriscono nel fuso del portale e si salvano in UTC; eliminare una risorsa collegata a servizi è vietato, disattivarla conserva i collegamenti.

## Allocazioni delle risorse: snapshot e sessioni
resource_allocations salva nomi, requisito, preparazione/riassetto e finestra UTC. Ogni allocazione appartiene a una prenotazione individuale oppure a una sessione di gruppo; ogni posto di gruppo conserva il riferimento alle allocazioni condivise in booking_resource_allocation, anche dopo annullamento e riapertura della sessione. blocking richiede released_at null e un proprietario con prenotazioni bloccanti. L'ultimo annullamento libera la sessione; la riapertura ricontrolla persone/risorse e crea nuovi snapshot. Gli spostamenti rilasciano e riassegnano atomicamente. Catalogo e requisiti non riscrivono lo storico; alle prenotazioni precedenti non si assegnano risorse retroattivamente. Disattivazione, rimozione e manutenzione rifiutano occupazioni future, incluso preparazione/riassetto.

## Snapshot dei pacchetti e guardie sui componenti
booking_phases conserva nome del servizio, prezzo, durata, buffer, ordine, pausa e orari UTC. Le resource_allocations della prenotazione puntano alla fase; la cancellazione rilascia tutte le risorse insieme e conserva gli snapshot. Le domande dei componenti sono aggregate con etichetta contestuale insieme a quelle del pacchetto. Un componente usato in un pacchetto o in fasi di prenotazioni future deve restare individuale; eliminazione e cambi di tipo passano dalle guardie sotto AgendaLock. Prezzo nelle pagine di gestione e nelle email viene da Booking, non dal catalogo corrente.

## Testo libero sui clienti cifrato: non filtrarlo né ordinarlo in SQL
bookings.customer_notes, customers.notes e booking_answers.value usano il cast encrypted (dal 2026-10-04, migrazione encrypt_customer_free_text). Il database contiene solo il testo cifrato: niente where/orderBy/Search::contains su queste colonne e niente DB::table()->insert/update in chiaro, altrimenti la lettura dal modello lancia DecryptException. Un nuovo campo di testo libero scritto dal cliente o sul cliente va cifrato allo stesso modo. Dipendono da APP_KEY: per ruotarla usare APP_PREVIOUS_KEYS, mai rigenerarla e basta. Un database copiato da prima del 2026-10-04 va migrato prima di aprirlo.

## tenants.license_id: l'identificativo del portale per il License Manager è un UUID immutabile
All'esterno (License Manager, `externalId`) un portale si chiama `tenants.license_id`, un UUID minuscolo generato da `Tenant::booted()` alla creazione e **mai** `tenants.id`: l'id numerico dipende dal database e riparte da 1 se lo si ricrea o si ripristina un backup vecchio. Non è `fillable` e un portale esistente non può cambiarlo (`LogicException` in `updating`): una volta registrato nel License Manager, cambiarlo orfanerebbe la licenza. L'unicità la garantisce l'indice unico; `Tenant::newLicenseId()` controlla su indice e ritenta al massimo 3 volte. La colonna è nullable solo perché SQLite non la rende obbligatoria senza ricostruire `tenants`: il modello la riempie sempre. `tenants.id` resta la chiave primaria e interna (chiavi esterne, log, chiavi di cache).

## Il JSON per il License Manager esce solo da LicenseManagerExport e solo con dati completi
Il file di registrazione di un portale per il License Manager (docs/struttura-json-registrazione-cliente.md) lo costruisce solo `App\Support\LicenseManagerExport::payload()`: `schemaVersion`, `exportedAt`, `software` (da `LICENSE_SOFTWARE_CODE`, mai nel codice), `tenant.externalId` = `tenants.license_id`, e `customer` con i 17 campi sempre presenti (`null`, mai testo vuoto). Si scarica da `admin.portals.license-json` solo se `BillingData::gaps()` è vuoto e il codice software è impostato, solo l'admin, e ogni download scrive `portal.license_json_exported` a livello piattaforma senza valori. Un campo nuovo in `customer` va cambiato qui, in `LicenseJsonExportTest` e nel documento, tutti insieme: la stessa struttura sarà il corpo della registrazione automatica.

## Piani: i limiti nuovi si bloccano, quello che esiste continua a funzionare
Regola del 2026-11-17 per ogni caratteristica di piano. (1) Un piano che non include una funzione non spegne ciò che c'è già sotto prenotazioni prese: impedisce solo di creare o cambiare (servizi e operatori oltre il limite, funzioni con `planAllows()` falso: risorse, lista d'attesa, pacchetti, servizi di gruppo). Le azioni che RESTRINGONO l'agenda (segnare una risorsa non disponibile, eliminarla) restano permesse: `ResourcePolicy::create/update` guardano il piano, `delete/block` no. (2) Non copiare l'SMTP: lì il declassamento ricade sulla piattaforma ed è innocuo; con le risorse ignorarle farebbe vendere due volte la stessa cabina. (3) Niente piano = niente limite (`Tenant::planAllows()`). (4) Il controllo è sempre sul server; l'interfaccia mostra la voce bloccata con un lucchetto (`auth.user.tenant.features`), non la nasconde. (5) I piani li definiamo qui; quale è attivo e fino a quando lo dirà il License Manager (il piano si riconosce dal `code` — base, pro, studio — mai dal nome, che è libero di cambiare: vedi `LicenseService::planFor`). 
Come si descrive un piano (decisione del 2026-11-18): limiti numerici (`max_services`, `max_operators`, `max_bookings_per_month`, `max_waiting_list` per servizio, `max_packages`; null = illimitato) e un unico insieme di funzioni sì/no in `plans.features` (`Plan::FEATURES`: resources, waiting_list, packages, group_services, calendar_buttons, embed, custom_smtp). Una funzione non elencata vale «non inclusa». È la forma che il License Manager consegnerà (docs/prompt-3-catalogo-piani.md): `code` è la chiave stabile, il nome può cambiare. Base: nessuna funzione; Pro: tutto tranne i servizi di gruppo, lista d'attesa fino a 5 persone per servizio, 3 pacchetti; Studio: tutto, senza limiti. Gli stessi blocchi: servizi di gruppo e pacchetti solo alla creazione (`SaveService::checkPlanFeatures`, chi li ha già continua a modificarli); lista d'attesa e posti (`WaitingListController::store`, 404 senza funzione, errore se piena); pulsanti calendario (`calendar_links` nullo e route .ics 404); incorporamento (`PortalPage::embedded()` ignora `?embed=1`). Le esportazioni dei dati sono sempre presenti (GDPR) e non dipendono dal piano. «Powered by» non dipende dal piano.
