---
paths:
  - 'app/Services/**'
  - app/Services/LicenseService.php
---

# Services

## SlotFinder: fuso del portale, UTC nel database
`App\Services\SlotFinder` è l'unica fonte di verità sugli slot prenotabili. Non duplicare la logica altrove: se serve sapere se un orario è libero, usa `canBookAt()`.

Regole del tempo (decise il 2026-08-26):
- Gli orari settimanali si interpretano **sempre nel fuso del portale** (`tenant->timezone`), mai in quello dell'operatore o del visitatore.
- Tutto ciò che va nel database è **UTC**: `bookings.starts_at`, `time_blocks.starts_at`, e i valori restituiti dal finder. La conversione al fuso del visitatore avviene solo nelle view.
- Ogni prenotazione salva anche `blocked_starts_at` / `blocked_ends_at`, cioè la finestra con i buffer del servizio applicati. Sono uno snapshot: cambiare i buffer di un servizio non sposta le prenotazioni già prese.
- Le prenotazioni che occupano uno slot sono quelle in `BookingStatus::blocking()`. Una annullata libera lo slot.
- Chi sposta una prenotazione deve usare `->ignoring($booking)`, altrimenti la prenotazione blocca sé stessa.

Trappola già incontrata: `availability_exceptions.date` è salvata come `Y-m-d H:i:s`, quindi va confrontata con `whereDate()` e mai con stringhe `Y-m-d` in `whereBetween`.

## Servizi di gruppo: la sessione è l'appuntamento
`services.capacity > 1` (`Service::isGroup()`) cambia il modello: l'appuntamento non è più fra un cliente e un operatore, è una **sessione** (`service_sessions`) con i suoi posti e il suo staff.

- `GroupSlotFinder` decide gli orari: una sessione viva con posti liberi si offre così com'è, una piena toglie del tutto quell'orario, e dove non c'è ancora nessuna sessione basta **un** operatore libero per aprirla.
- La sessione nasce alla prima prenotazione e prende **tutti** gli operatori liberi in quel momento; il manager può poi aggiungerne o toglierne dall'agenda, mai lasciandone zero.
- `SlotFinder::busyPeriods()` conta anche le sessioni dell'operatore: chi tiene una lezione è occupato per qualunque altro servizio.
- Concorrenza: niente `AgendaLock`, si blocca la **riga della sessione** (`lockForUpdate`) perché l'ultimo posto è una riga che esiste già; la prima prenotazione è serializzata dall'unico `unique(service_id, starts_at)`, e chi perde la corsa rilegge la sessione appena aperta.
- `bookings.user_id` resta valorizzato con l'operatore "primario" della sessione: agenda, email e `.ics` continuano a funzionare senza sapere nulla dei gruppi. Togliendolo dalla sessione, le sue prenotazioni passano a chi resta.
- Una prenotazione di gruppo **non si sposta**: si annulla e si riprenota (`RescheduleBooking` rifiuta con 422).

## Risorse condivise: disponibilità e ordine dei lock
SlotFinder verifica operatore e tutti i requisiti attraverso ResourceAvailability, con un preload per intervallo e confronti stretti: toccarsi non è sovrapporsi. Una risorsa si occupa durante l'appuntamento più la sua preparazione e il suo riassetto; i buffer del servizio restano dell'operatore. Ogni scrittura concorrente su agenda, risorse e requisiti usa AgendaLock: prima lock sul portale, poi su operatori e sessioni. forOperator prende anche il lock del portale; i gruppi usano forTenant anche per la prima sessione, quindi non dipendono più dalla sola violazione unique. Su SQLite servono transaction_mode IMMEDIATE e busy_timeout 5000; forUpdate non ha effetto. Le email restano fuori dalla transazione.

## Pacchetti: fasi, pause e disponibilità
Un pacchetto contiene almeno due servizi individuali distinti dello stesso portale e un unico operatore abilitato a tutti. PackagePlan combina durata/pause, prezzo somma o specifico e vincoli dei componenti; SlotFinder verifica la finestra di ogni fase. L'operatore resta occupato anche nelle pause. ResourceAvailability assegna risorse per fase, con preparazione/riassetto, ammettendo cambio cabina e riuso solo senza conflitti; prova le alternative compatibili invece di una scelta greedy. Gli spostamenti ricostruiscono ordine, durata, pause e prezzo dalle booking_phases, poi ricontrollano le risorse attuali.

## Licenza: LicenseService è l'unica porta verso il License Manager
Nessun altro codice parla HTTP col License Manager: si chiede un `LicenseStatus` a `App\Services\LicenseService` (cache → copia in `tenant_licenses` → chiamata solo se non c'è copia utilizzabile). Si decide con `access.allowed`, mai ricalcolando scadenze. `LICENSE_ENFORCEMENT`: `off` (nessuna chiamata automatica, né comando né richieste; resta solo «Verifica ora» dell'admin), `observe` (controlla e logga una volta al giorno cosa bloccherebbe, non blocca), `on` (governa accesso e piano dei portali non `license_exempt`). La licenza non tocca mai `is_active` (sospensione manuale dell'admin = cosa separata) e non riscrive `plan_id`: il piano effettivo è `Tenant::effectivePlan()`. Errori e timeout tengono l'ultima risposta valida (anche oltre le 72 ore: allarme in admin, nessuno penalizzato); chi non ha mai avuto risposta resta fuori dal back-office («impossibile verificare») ma le pagine pubbliche restano aperte. Per un portale governato `CheckSubscriptions`, `paid_until` e i promemoria locali stanno fermi (le email le manda il License Manager). La chiave sta solo in config/.env: mai nei log, nel browser, nel repository.

## License Manager giù: si lavora con l'ultima risposta e si avvisano gli admin
Deciso il 2026-11-18. Licenza obbligatoria: senza licenza valida (assente, scaduta oltre la tolleranza, non valida) il portale è bloccato; un portale nuovo nasce bloccato finché il personale non emette la licenza e la associa al suo `license_id` (pagina license/blocked con l'id da copiare). Se l'API non risponde NON si blocca nessuno e non si ricalcolano le date: si usa l'ultima risposta salvata (`tenant_licenses`), così un'interruzione non ferma i clienti e non regala licenze (non si prolunga nulla). Gli admin (solo loro) vedono un avviso rosso nel layout (`license-outage`) e ricevono un'email subito e poi ogni 6 ore finché dura (`LicenseManagerDownMail`; errori di configurazione compresi, `configuration` = true), più una email quando torna (`LicenseManagerBackMail`). `rate_limited` e `no_license_id` non sono interruzioni. Gli stati stanno in cache (`license.outage`, `license.alerted`, `license.alert-sent`). I piani sono definiti nel License Manager e copiati in `plans` per `code` da `php artisan plans:sync` (`SyncPlanCatalogue`, ogni 5 minuti, ETag/304, solo con enforcement observe o on): tutto (vetrina, back-office, limiti) legge la tabella; un piano sparito dal catalogo si toglie dalla vendita, non si cancella. Limiti e funzioni di un portale governato arrivano da `license.planType.attributes` salvati nella copia della licenza (`tenant_licenses.plan_code` e `plan_attributes`), quindi valgono anche senza catalogo; un attributo mancante o sconosciuto è «non incluso» (numero 0, funzione no), `null` è illimitato. Le chiavi del License Manager sono quelle italiane e stanno SOLO in `App\Support\PlanAttributes` (max_servizi, max_operatori, max_prenotazioni_mese, max_lista_attesa, max_pacchetti, is_risorse, is_lista_attesa, is_pacchetti, is_servizi_gruppo, is_pulsanti_calendario, is_embed_link, is_custom_smtp): nel resto dell'app si parla di max_services, resources, ecc. Con enforcement on la pagina Piani dell'admin è in sola lettura.
