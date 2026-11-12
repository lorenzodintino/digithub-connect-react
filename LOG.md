# LOG.md — Diario di bordo

Registro cronologico del progetto **DigitHub Connect** (piattaforma di prenotazione multi-portale, stile Calendly / Cal.com).

---

## Come si usa questo file

**Regola d'oro: ogni sessione di lavoro chiude con una voce nel Diario.** Vale per le persone e per gli agenti AI.

Divisione delle responsabilità — non duplicare fra i due:

| File | Contenuto | Quando lo leggi |
|---|---|---|
| `.ai/rules/*.md` | **Normativo**: come si scrive il codice qui, trappole, vincoli permanenti | Sempre, prima di scrivere codice (`.ai/rules/index.md` mappa i glob) |
| `LOG.md` (questo) | **Cronologico**: cosa è stato fatto, quando, perché, cosa è andato storto e come si è risolto, cosa manca | All'inizio di ogni sessione, per capire dove siamo |

Se una lezione appresa è **permanente** (una convenzione, una trappola che ricapiterà) → va in `.ai/rules` con `record-rule`, e nel Diario resta solo il riferimento.
Se è **un fatto storico** (abbiamo scelto X il giorno Y perché Z) → resta qui.

### Formato di una voce del Diario

Voci in ordine **cronologico inverso** (la più recente in cima). Ogni voce:

```markdown
### AAAA-MM-GG — Titolo breve della sessione

**Obiettivo.** Cosa si voleva ottenere.

**Fatto.** Elenco puntato di cosa è stato prodotto, con i percorsi dei file che contano.

**Decisioni.** Scelte prese e il perché. Le alternative scartate valgono una riga.

**Problemi e soluzioni.** Ogni intoppo reale: sintomo → causa → fix. È la parte più preziosa del file.

**Verifica.** Comandi lanciati e loro esito reale (numeri, non impressioni).

**Aperto.** Cosa resta in sospeso, con rimando al Backlog.
```

### Regole di scrittura

- Riporta gli esiti fedelmente. Se un test fallisce o un passaggio è stato saltato, si scrive.
- Niente credenziali reali di produzione. Le utenze di seed sono finte e possono stare qui.
- Le date sono assolute (`2026-08-26`), mai "ieri" o "la settimana scorsa".
- Se una voce supera lo schermo, probabilmente andava spezzata in due sessioni.

---

## Stato del progetto

**Fase 3 completata il 2026-08-27** (email, promemoria, `.ics`), **Fase 5 il 2026-08-28** (privacy e consenso, anagrafica clienti, chiusure, preavviso, domande personalizzate) e **Fase 6 il 2026-08-30** (esportazioni e report, servizi di gruppo, riordino, avatar, lista d'attesa, impersonificazione, registro azioni, avvisi sui job falliti).

**Fase 7 completata il 2026-10-01:** catalogo delle risorse condivise, indisponibilità, requisiti dei servizi, occupazione automatica nelle prenotazioni e pacchetti prestabiliti con pause configurabili e cambio cabina. Eseguito il collaudo complessivo locale richiesto: piano, esiti, correzioni e prove residue in [COLLAUDO.md](COLLAUDO.md). Chiusi B01–B13; restano controlli umani/infrastrutturali e varianti browser, non dichiarato pronto per produzione.

**Fase 8 completata il 2026-10-04:** cifratura di note e risposte dei clienti, pulsante Microsoft 365, esportazione completa del portale, documenti legali della piattaforma con accettazione, limiti dei piani con incasso manuale e sospensione automatica, vista settimanale a fasce orarie.

**Licenza (2026-11-12):** il License Manager può governare accesso e piano dei portali (`LICENSE_ENFORCEMENT` = `off` | `observe` | `on`, default `off`: oggi non cambia nulla). Vedi il Diario.

**Fase 9 completata il 2026-10-07: interfaccia in Inertia + React.** Tutte le schermate (back-office, admin, pagine pubbliche, accesso, home) sono React; Livewire, Flux e Blaze sono stati rimossi. Blade resta solo per la vista radice, le pagine di errore e le email. Piano e stato in [Backlog → Fase 9](#fase-9--migrazione-dellinterfaccia-a-inertia--react-avviata-il-2026-10-05).

**Non ancora implementato (scelto di rimandarlo il 2026-10-04):** sincronizzazione calendari, link automatico per le videochiamate, pagamenti dei clienti finali, SMS. Vedi [Backlog](#backlog).

> ### ⚠️ Il collaudo manuale è in corso
>
> La suite contiene **797 test automatici** (795 passati, 2 saltati per registrazione pubblica disabilitata; ultima esecuzione il 2026-11-12, dopo i dati di fatturazione). Il nuovo [collaudo complessivo](COLLAUDO.md), aggiornato il 2026-10-02, registra 72 casi: 36 passati, 31 parziali e 5 con intervento utente. Sono stati eseguiti davvero nel browser manager, operatori, cliente e admin; le parti solo automatiche sono indicate. Il precedente collaudo, iniziato il 2026-08-31, resta come cronologia: Giri 1, 2, 3, 4 e 7 chiusi; Giro 5 e Giro 6 non si dichiarano integralmente chiusi sulla base dei nuovi test.
>
> Test verdi non significano prodotto giusto: dicono che il codice fa quello che ho scritto io, non che sia quello che serve.

> ⚠️ Nessun server SMTP reale configurato per la piattaforma. Da qui parte **tutta** la posta, salvo i portali che hanno un proprio server verificato: senza credenziali e record SPF/DKIM sul dominio mittente non parte niente. In sviluppo le email finiscono in `storage/logs/laravel.log`.

---

## Cosa fa oggi il prodotto

Fotografia iniziale al 2026-08-27, integrata con le risorse il 2026-10-01. Per la cronologia di come ci si è arrivati, vedi il [Diario](#diario).

### ADMIN — amministratore della piattaforma

Crea e gestisce i portali, uno per attività cliente.

- Crea un portale insieme al suo **unico account manager**, con password generata e mostrata una volta sola
- Modifica, sospende, riattiva ed elimina i portali
- Rigenera la password di un manager
- Configura l'**SMTP di qualsiasi portale** e lo collauda
- Vede tutti gli account della piattaforma, li filtra per ruolo e portale, li disabilita o elimina

### MANAGER — un solo account per attività

Configura il proprio portale e le persone che ci lavorano.

- **Servizi**: durata, buffer prima e dopo, intervallo fra slot, preavviso minimo, finestra di prenotabilità, prezzo, colore, tipo di luogo, conferma manuale, operatori assegnati
- **Risorse**: stanze, cabine, postazioni, macchinari e strumenti distinti, preparazione/riassetto, stato e indisponibilità. Nei servizi configura i requisiti e le alternative; le prenotazioni richiedono tutte le risorse libere e ne salvano l'assegnazione, visibile nel dettaglio dell'agenda
- **Pacchetti**: compone almeno due servizi individuali, sceglie ordine e pause, assegna operatori abilitati a tutte le fasi e usa la somma dei prezzi o un prezzo specifico. Una prenotazione unica conserva il programma; le cabine possono cambiare e le risorse sono occupate per fase
- **Staff**: crea operatori con credenziali generate, assegna i servizi, decide chi è prenotabile. Può rendere prenotabile anche sé stesso
- **Disponibilità**: orari settimanali con più fasce al giorno, eccezioni per data, blocchi orari ad hoc. Può curare anche l'agenda dei propri operatori
- **Agenda**: tutte le prenotazioni del portale, filtrabili; conferma le richieste, annulla con motivazione, segna gli appuntamenti passati come **completati** o **assenti**
- **Impostazioni**: branding con **logo**, contatti, link pubblico, snippet iframe, SMTP proprio, copia delle nuove prenotazioni

### DEFAULT — operatore

- Vede **solo la propria** agenda e la propria disponibilità
- Conferma e annulla **solo le proprie** prenotazioni, e ne segna l'esito quando sono passate
- Modifica il proprio profilo pubblico: ruolo mostrato ai clienti, biografia, telefono
- Sceglie il proprio fuso orario e la propria lingua

### CLIENTE — pagina pubblica, senza account

- `/p/{portale}` elenca i servizi prenotabili, con il logo e il colore dell'attività
- Calendario mensile con i giorni liberi, orari mostrati **nel proprio fuso**, rilevato dal browser
- Se il servizio ha più operatori sceglie la persona, oppure lascia *"operatore indifferente"* e la piattaforma assegna il meno carico
- Prenota lasciando nome, email, telefono e note
- Riceve un **link con token** per gestire la prenotazione, più **QR** scaricabile in PNG
- Da lì **sposta** o **annulla**: il link resta lo stesso anche dopo uno spostamento
- `?embed=1` per l'incorporamento in un sito esterno

### Email

Nove messaggi, ognuno scritto per chi lo legge. Al cliente: conferma, richiesta ricevuta, annullamento, spostamento, promemoria, posto liberato in lista d'attesa. All'operatore: nuova prenotazione, annullamento, spostamento. All'amministratore: riepilogo dei job falliti.

- **Manda la piattaforma**, con il nome del portale come mittente visualizzato e il suo indirizzo di risposta
- Un portale può inviare dal **proprio SMTP** — opzione offerta su richiesta, configurata dall'admin, in servizio **solo dopo una prova riuscita**; un invio fallito lo riporta sulla piattaforma
- Cliente e operatore ricevono messaggi **separati**, mai uno in copia all'altro
- Al cliente si scrive nella lingua in cui ha prenotato, all'operatore nella lingua del suo account

### Trasversale

- Interfaccia **bilingue IT/EN**, scelta salvata sull'account
- Tutti gli orari salvati in **UTC**; interpretati nel fuso del portale, mostrati in quello di chi guarda
- Ogni scrittura sull'agenda è **serializzata** e ricontrollata: due prenotazioni non possono sovrapporsi
- Togliere disponibilità **rifiuta** se ci sono prenotazioni dentro (blocchi ed eccezioni), oppure **avvisa** senza bloccare (orari settimanali)

---

## Stack

| Componente | Versione | Note |
|---|---|---|
| PHP | 8.5 | |
| Laravel | 13.x | attributi `#[Fillable]` / `#[Hidden]` sui model |
| Inertia | 3.x | `inertiajs/inertia-laravel` + `@inertiajs/react` + `@inertiajs/vite`, SSR spento |
| React | 19.x | TypeScript 7 (`npm run types`), componenti shadcn/ui (Radix) in `resources/js/components/ui` |
| Wayfinder | 0.1.x | rotte tipizzate in `resources/js/routes` e `actions`, generate da Vite (ignorate da git) |
| Fortify | 1.x | registrazione pubblica **disattivata** |
| Tailwind CSS | 4.x | |
| Pest | 5.x | |
| DB | SQLite (anche in produzione, WAL) | `database/database.sqlite`; test in memoria |

Build frontend: `npm run build` (o `npm run dev`). Tutto insieme: `composer run dev`.

Un foglio di stile e una entry: `resources/css/app.css` + `resources/js/app.tsx`, vista radice `resources/views/app.blade.php`.

---

## Mappa del codice

```
app/
  Actions/Availability/  BlockTime, SetDateOverride, SaveWeeklySchedule, CloseThePortal
  Actions/Bookings/      CreateBooking, CancelBooking, RescheduleBooking, ConfirmBooking,
                         RecordOutcome (presente/assente), AnonymiseCustomer (GDPR),
                         NotifyWaitingList
  Actions/Tenants/       TestMailer (prova SMTP, mette in servizio il server),
                         RecordMailFailure (invio fallito → sul portale), SaveMailSettings,
                         SavePortal, DeletePortal, SetPaidUntil, ExportPortal
  Actions/…              Services/, Staff/, Resources/, Plans/SavePlan, Users/DeleteAccount,
                         Legal/ — le scritture delle pagine React
  Console/Commands/      SendBookingReminders, CompletePastBookings, ReportFailedJobs,
                         PruneActivityLog, PruneWaitingList, AnonymiseOldBookings
  Enums/                 UserRole, LocationType, BookingStatus, QuestionType
  Exceptions/            SlotUnavailableException, PeriodHasBookingsException
  Http/Controllers/      un controller Inertia per pagina del back-office (Admin/ per il
                         pannello admin, Settings/ per l'account), BookingCalendarController
                         (.ics), StopImpersonating
  Http/Requests/         un Form Request per ogni modulo React
  Http/Middleware/       EnsureUserHasRole (alias `role`), EnsureUserIsActive, SetLocale,
                         StopExpiredImpersonation, HandleInertiaRequests (props condivise)
  Providers/             AppServiceProvider: {ownService}, errori delle visite Inertia
                         (pagina → errore Blade in visita completa, azione → indietro con
                         toast); FortifyServiceProvider: pagine di accesso React
  Listeners/             RecordAuthActivity, RecordFailedJob   (autodiscovery: non registrarli a mano)
  Mail/                  BookingMail (envelope e trasporto comuni), MailerTestMail,
                         Concerns/DeliveredByPortal (mailer del portale + failed()),
                         Customer/ (5), Staff/ (3), Platform/FailedJobsReportMail
  Models/                Tenant, User, Service, ServiceSession, ServiceQuestion,
                         AvailabilityRule, AvailabilityException, TimeBlock, PortalClosure,
                         Booking, BookingAnswer, Customer, WaitingListEntry,
                         ActivityLog, Plan
  Policies/              TenantPolicy (include `configureMailer`, solo admin), ServicePolicy,
                         UserPolicy, BookingPolicy, CustomerPolicy, PlanPolicy
  Services/              SlotFinder (slot per operatore), GroupSlotFinder (classi di gruppo),
                         WorkingHours (orari), AgendaLock (concorrenza),
                         BookingNotifier (chi riceve cosa), Impersonation
  Support/               Week (giorni e griglia del mese), Timezones, QrCode, IcsEvent,
                         Csv (esportazioni), Activity (registro), Search (escape LIKE),
                         LayoutNotices (avvisi sopra la pagina, per Blade e React),
                         Agenda (agenda prenotazioni), Toast, MailSettingsData, ServiceFormData,
                         SlotCalendar (calendario pubblico), PortalPage (brand, lingua, embed,
                         limite delle azioni pubbliche)

routes/
  web.php           `/` e `/dashboard`, poi require degli altri
  admin.php         /admin/*   role:admin
  app.php           /app/*     role:manager | role:manager,default
  booking.php       /p/*       pubblico
  settings.php      profilo, aspetto, sicurezza (starter kit)
  console.php       sei comandi pianificati (promemoria, completamento, job falliti,
                    potatura registro e lista d'attesa, anonimizzazione)

resources/js/                       tutta l'interfaccia (React + Inertia)
  app.tsx                           entry Inertia, layout per area, lingua di __()
  pages/welcome.tsx                 home pubblica della piattaforma (senza layout)
  pages/dashboard.tsx               dashboard consapevole del ruolo
  pages/settings/                   profilo, sicurezza, aspetto
  pages/app/                        bookings (agenda: tabella, mese, settimana, giorno), services,
                                    staff, resources, customers, waiting-list, portal, reports,
                                    availability
  pages/shared/activity.tsx         registro azioni, letto da admin e manager
  pages/admin/                      portals, portal (dettaglio), users, plans, legal, queue
  pages/portal/                     pagine pubbliche: show, service (calendario, orari, modulo,
                                    lista d'attesa), booking (gestione e spostamento), waiting-list
  layouts/portal-layout.tsx         guscio pubblico: colori del brand, header e footer, ?embed=1
  pages/auth/                       accesso (Fortify): login, forgot/reset password, sfida 2FA,
                                    conferma password, verifica email, registrazione (spenta)
  layouts/auth-layout.tsx           guscio delle pagine di accesso: logo e una colonna
  hooks/use-filters.ts              filtri nell'URL: debounce sui tasti, `apply` immediato
  layouts/app-layout.tsx            guscio back-office: sidebar, avvisi, pagina
  components/                       app-sidebar, locale-switcher, layout-notices, page-header,
                                    stat-card, copyable-input, app-logo, mail-settings (consegna
                                    email, manager e admin), credentials-dialog (password
                                    mostrata una volta), slot-picker (mese e orari pubblici),
                                    portal-logo, auth-header, passkey-verify (accesso e conferma
                                    con passkey); ui/ = shadcn
  lib/i18n.ts                       __() e trans_choice() su lang/it.json
  types/                            SharedData e tipi delle props condivise

resources/views/
  app.blade.php                     vista radice di Inertia
  errors/403|404|419|500            pagine di errore autosufficienti, CSS inline
  mail/                             layout, partials e i corpi delle email
resources/css/app.css               Tailwind 4 + token di shadcn/ui

lang/it.json                        traduzioni interfaccia (chiave = testo inglese)
lang/it/validation.php              messaggi di validazione italiani
```

---

## Convenzioni

Sono in `.ai/rules/`, lette automaticamente dagli agenti. Indice: `.ai/rules/index.md`.

| Area | File | In sintesi |
|---|---|---|
| `app/**` | `.ai/rules/app.md` | ruoli e scoping per portale |
| `resources/views/**` | `.ai/rules/views.md` | Blade solo per vista radice, errori ed email |
| `app/Http/Controllers/**` | `.ai/rules/controllers.md` | una pagina = controller + `Inertia::render`, Form Request, toast |
| `resources/js/pages/portal/**` | `.ai/rules/portal.md` | pagine pubbliche: stato nell'URL, brand, embed, limiti |
| `resources/js/pages/auth/**` | `.ai/rules/auth.md` | accesso: view Fortify come pagine Inertia |
| `tests/**` | `.ai/rules/tests.md` | pagine provate via HTTP + `assertInertia`, helper dei moduli |
| `resources/**` | `.ai/rules/resources.md` | bilingue IT/EN, ogni stringa passa da `__()` |
| `routes/**` | `.ai/rules/routes.md` | split dei file rotte, URL pubblici `/p/{slug}` |
| `app/Services/**` | `.ai/rules/services.md` | SlotFinder: fuso del portale, UTC nel database |
| `app/Support/**` | `.ai/rules/support.md` | liste condivise, mai inline nei Blade |
| `database/**` | `.ai/rules/database.md` | i default di colonna non tornano dopo `create()` |
| `app/Actions/**` | `.ai/rules/actions.md` | ogni scrittura sull'agenda passa da `AgendaLock` |
| `app/Actions/Availability/**` | `.ai/rules/availability.md` | togliere disponibilità rifiuta invece di creare incoerenze |
| `app/Mail/**` | `.ai/rules/mail.md` | mittente, destinatari separati, lingua, coda |
| `app/Actions/Bookings/**` | `.ai/rules/bookings.md` | lock sull'operatore prima di scrivere una prenotazione |
| `**` | `.ai/rules/general.md` | leggere e aggiornare questo diario |
| `resources/js/**` | `.ai/rules/js.md` | pagine React: `__()`, `<Link>` + Wayfinder, date dal server |
| `resources/js/components/ui/**` | `.ai/rules/ui.md` | CLI shadcn: import di `cn` e pacchetto spurio |

---

## Avviare il progetto in locale

Terminale aperto nella cartella `C:\ARCHIVIO\AI\digithub-connect`.

```bash
composer run dev
```

Avvia quattro processi insieme (server web, worker delle code, scheduler e Vite). Si ferma con **Ctrl+C** nella stessa finestra.

Se Ctrl+C lascia processi orfani, la porta 8000 resta occupata. Si liberano così (PowerShell):

```powershell
Get-NetTCPConnection -LocalPort 8000 -State Listen | ForEach-Object { Stop-Process -Id $_.OwningProcess -Force }
```

Alternativa più leggera, quando gli asset sono già compilati e non si tocca il frontend:

```bash
php artisan serve
```

| Comando | Quando |
|---|---|
| `npm run dev` | mentre si lavora al frontend: ricarica automatica |
| `npm run build` | una tantum, per compilare gli asset definitivi |
| `php artisan migrate:fresh --seed` | azzerare il database e ricaricare i dati demo (**cancella tutto**) |

### Prima di avviare, verifica sempre la versione di PHP

```powershell
php -v    # deve essere 8.5.0
```

Sulla macchina di sviluppo convivono due PHP:

| Percorso | Versione | Uso |
|---|---|---|
| `C:\Users\loren\.config\herd-lite\bin\php.exe` | 8.5.0 | **questo** — è nel PATH utente |
| `C:\xampp\php\php.exe` | 8.2.12 | XAMPP, non serve al progetto |

Se `php -v` risponde 8.2.12 il terminale ha un PATH obsoleto: **chiudilo e riaprine uno nuovo**.
Il progetto richiede PHP ≥ 8.4.1 e `vendor/composer/platform_check.php` blocca l'avvio con un fatal error se la versione è più bassa.

Il database è SQLite (`database/database.sqlite`): MySQL di XAMPP non serve.

## Comandi di verifica

Da lanciare prima di considerare chiuso un lavoro:

```bash
vendor/bin/pint --format agent                          # formattazione (corregge)
vendor/bin/phpstan analyse --no-progress --memory-limit=1G   # analisi statica, livello 7
php artisan test --compact                              # suite completa
npm run build                                           # build asset (rigenera anche Wayfinder)
npm run types                                           # controllo TypeScript delle pagine React
```

Riporta gli esiti nella voce di Diario.

Nota: in pratica si usa `vendor/bin/pint --dirty --format agent`, che formatta solo i file toccati.
`phpstan` senza `--memory-limit=1G` esaurisce i 128M di default e va in crash.

---

## Credenziali di seed

`php artisan migrate:fresh --seed` — password `password` per tutte.

| Ruolo | Email | Portale |
|---|---|---|
| ADMIN | admin@digithub.test | — |
| MANAGER | manager@digithub.test | Studio Rossi |
| DEFAULT | giulia@digithub.test | Studio Rossi |
| DEFAULT | luca@digithub.test | Studio Rossi |

Portale pubblico di prova: `/p/studio-rossi`

---

## Il ruolo della piattaforma e i suoi obblighi

Annotato il 2026-08-28, prima di avere clienti paganti. **Non è consulenza legale**: è l'inquadramento standard, da far confermare a un legale quando si scrive il contratto.

### Chi è chi

| Dati | Titolare | Responsabile |
|---|---|---|
| Clienti finali che prenotano (nome, email, telefono, note) | **L'attività** (il portale): decide perché li raccoglie, quanto li tiene, cosa ci fa | **Noi**: li trattiamo per suo conto, perché forniamo lo strumento |
| Account dei manager e degli operatori, fatturazione, log della piattaforma | **Noi** | — |

Conseguenza pratica già nel software: informativa, termini e durata di conservazione **li imposta il portale**, non noi. Noi diamo i campi, i link, il caricamento del PDF, la spunta di consenso, la prova del consenso e gli strumenti di cancellazione.

### Cosa serve prima del primo cliente pagante

- [ ] **DPA** (accordo di nomina a responsabile, art. 28 GDPR) allegato al contratto con ogni attività. Senza, il titolare è in violazione e noi con lui. È il documento da preparare per primo
- [ ] **Elenco dei sub-responsabili** (hosting, provider SMTP, storage, eventuale servizio SMS) con obbligo di informare il titolare quando cambia. Da qui in poi pesa di più: dal 2026-09-02 le email dei clienti finali passano dal **nostro** provider di posta salvo i portali con SMTP proprio verificato, quindi quel provider è un sub-responsabile per quasi tutti
- [ ] **Dove stanno i dati**: server in UE è la strada semplice; fuori UE servono garanzie aggiuntive
- [ ] **Misure di sicurezza** dichiarabili: cifratura in transito, backup, controllo degli accessi, credenziali mai in chiaro. Parte c'è già (password SMTP cifrate, token non indovinabili, ruoli e scoping per portale)
- [ ] **Procedura di violazione**: se succede, avvisiamo **il titolare** senza ingiustificato ritardo — l'autorità la avvisa lui. Serve un canale e una procedura scritta
- [ ] **Assistenza sui diritti degli interessati**: il titolare deve poter rispondere a una richiesta di accesso o cancellazione usando il software. La cancellazione del cliente e la conservazione automatica ([5.1](#51-privacy-termini-e-consenso--completata-il-2026-08-28)) servono a questo, ed è anche un argomento di vendita
- [ ] **Registro dei trattamenti come responsabile** (art. 30.2): categorie di trattamento svolte per conto dei titolari
- [x] **Fine del rapporto**: restituzione o cancellazione dei dati a scelta del titolare. Restituzione: Impostazioni portale → Scarica tutti i dati (8.4). Cancellazione: eliminazione del portale dall'admin
- [ ] **Informativa e termini della piattaforma** (i nostri, verso le attività che comprano) sul sito commerciale
- [x] (8.5, 2026-10-04: software pronto, i testi li scrive il legale) **Pagina *Documenti legali* nel pannello admin** dove tenere DPA, elenco sub-responsabili e informativa della piattaforma, con la data dell'ultima versione accettata da ogni portale

Cosa **non** è nostro: i testi legali di ogni attività, la base giuridica che sceglie, per quanto tiene i dati dei suoi clienti.

---
## Prima del deploy

> **Server di destinazione: VPS con Windows Server** (comunicato il 2026-10-04, deploy rimandato a fine Fase 8). Le righe Linux qui sotto vanno tradotte: cron → Utilità di pianificazione ogni minuto, Supervisor → servizio Windows (per esempio NSSM) per `queue:work`, `storage:link` richiede privilegi per i link simbolici. SQLite in WAL funziona su NTFS locale.

> **Proxy fidati.** Il registro delle azioni annota l'indirizzo IP di chi agisce. Dietro un reverse proxy, un load balancer o un CDN, senza `TrustProxies` configurato quell'indirizzo è sempre quello del proxy: da sistemare insieme al resto dell'infrastruttura.

Elenco delle cose che in sviluppo funzionano da sole e in produzione **no**. Da percorrere tutto prima di mettere online il primo cliente vero.

### 1. Il cron dello scheduler ⚠️

Senza questa riga i **promemoria non partono mai**. In sviluppo ci pensa `composer run dev`; in produzione niente lo sostituisce.

```
* * * * * cd /percorso/del/progetto && php artisan schedule:run >> /dev/null 2>&1
```

Ogni minuto, non ogni quarto d'ora: è lo scheduler di Laravel a decidere cosa eseguire davvero.

Verifica dopo il deploy: `php artisan schedule:list` deve mostrare `bookings:send-reminders` e `portals:check-subscriptions` (promemoria di scadenza e sospensione per mancato pagamento).

### 2. Il worker della coda ⚠️

Tutte le email passano dalla coda. Senza un worker attivo restano nella tabella `jobs` e **non parte niente**.

Serve un supervisore che lo tenga vivo e lo riavvii se muore — Supervisor su Linux, oppure il gestore di processi dell'hosting:

```
php artisan queue:work --tries=3 --max-time=3600
```

### 3. `APP_KEY` va conservata ⚠️

Con quella chiave sono cifrate le password SMTP dei portali e, dal 2026-10-04, note e risposte dei clienti. **Cambiarla le rende tutte illeggibili**: i portali smettono di inviare e le pagine con note vanno in errore. Per ruotarla si usa `APP_PREVIOUS_KEYS`, mai una rigenerazione secca.

Da salvare fuori dal server, e da non rigenerare mai su un ambiente con dati veri.

### 4. Database

**Deciso il 2026-10-04: resta SQLite anche in produzione.** Condizioni e motivazione in `.ai/rules/config.md`. Al deploy:

- **Un solo server con disco locale persistente.** Niente filesystem effimeri, dischi di rete o più macchine.
- Il file `database/database.sqlite` e i suoi compagni `-wal`/`-shm` devono stare su quel disco ed essere scrivibili dall'utente di PHP. Non vanno mai cancellati a mano mentre l'app gira.
- `SESSION_DRIVER=file` e `CACHE_STORE=file` nell'`.env` di produzione.
- **Backup** ⚠️: mai copiare il file a caldo. Usare `sqlite3 database.sqlite "VACUUM INTO '/backup/…'"` da cron, oppure Litestream verso uno storage S3-compatibile in UE. Provare anche il ripristino.

La concorrenza è provata con processi PHP paralleli veri in `BookingConcurrencyTest` e `ResourceAllocationTest`. Se un giorno serviranno più server, il passaggio a MySQL/Postgres resta possibile.

### 5. SMTP della piattaforma ⚠️

Non è più un ripiego: **da qui parte tutta la posta**, salvo i portali con un server proprio verificato. Servono credenziali di un provider transazionale e **SPF e DKIM** sul dominio mittente, altrimenti le email finiscono in spam — e finirebbero in spam per tutti i clienti insieme.

Prima del primo cliente pagante vale anche la pena guardare i rimbalzi: oggi non li leggiamo, perché richiedono l'API del provider (deciso il 2026-08-26, si aggiunge quando serve).

### 6. Impostazioni d'ambiente

```
APP_ENV=production
APP_DEBUG=false          ← altrimenti gli errori mostrano il codice ai visitatori
APP_URL=https://…        ← i link nelle email nascono da qui
```

### 7. Il link a `storage` ⚠️

```
php artisan storage:link
```

Crea `public/storage` → `storage/app/public`. Senza, **i loghi dei portali danno 404**: il file c'è ma nessuno può servirlo. Va rifatto a ogni deploy che ricrea `public/` da zero.

Da valutare per la produzione: passare a un disco S3 o compatibile. Con più di un server il disco locale non basta, perché il logo caricato su una macchina non esiste sull'altra.

### 8. Git ✅ fatto il 2026-08-27

Repository su GitHub: `lorenzodintino/digithub-connect`. Verificato che `.env`, il database SQLite, `vendor`, `node_modules` e i log **non** sono tracciati: 311 file, solo codice.

**Da rivedere quando il progetto smette di essere locale:** il token GitHub è dentro l'URL del remote in `.git/config`. Finché ci lavora una persona sola sulla propria macchina la scelta regge; nel momento in cui il codice gira su un server, viene clonato altrove o entra qualcun altro, va spostato in un gestore di credenziali e **rigenerato**, perché quello attuale è passato per una conversazione e per la configurazione locale.

---

### 9. La licenza (License Manager) ⚠️

- `.env` del server: `LICENSE_API_URL` (**https**: in produzione una URL http viene rifiutata prima di inviare la chiave), `LICENSE_API_KEY`, `LICENSE_SOFTWARE_CODE`, e opzionali `LICENSE_ENFORCEMENT` (parte `off`), `LICENSE_CACHE_TTL_SECONDS`, `LICENSE_OFFLINE_GRACE_HOURS`, `LICENSE_SUPPORT_EMAIL`. Software e chiave **diversi da quelli di sviluppo**: l'`externalId` è `tenants.license_id` (un UUID, vedi il Diario del 2026-11-12), quindi non si ripete nemmeno fra ambienti, ma tenerli separati resta la regola.
- Lo scheduler (punto 1) esegue anche `licenses:refresh` ogni 5 minuti.
- Per accendere: prima `php artisan licenses:suspended-portals` (portali spenti, da verificare a mano), poi segnare «Fuori dalla licenza» demo e portali di prova, passare a `observe`, leggere il log (`License would block the portal.`), infine `on`.

## Da collaudare a mano

**Stato: iniziata il 2026-08-27, non finita.** Chiusi il **Giro 7** (consegna delle email, il 2026-09-04, su server SMTP finti in locale), il **Giro 2** (l'operatore) e il **Giro 3** (il manager) il 2026-09-05, il **Giro 4** (l'admin) il 2026-09-07. Il **Giro 5** è cominciato il 2026-09-09 e ha già prodotto tre correzioni: è **fermo al passo 3 della Seduta A**. Resta il **Giro 6**, e il passo del Giro 1 sul telefono vero.

I test automatici dicono che il codice fa quello che è stato scritto. Questa procedura serve a scoprire se è quello che serve davvero, e le cose che i test non vedono: che una schermata sia comprensibile, che un messaggio sia scritto bene, che un flusso non sia scomodo.

Che serva è già dimostrato: le prime sessioni hanno prodotto **cinque difetti reali** che nessun test aveva colto — un sesto è saltato fuori da solo il 2026-08-27, i pulsanti del back-office che passavano un `id` dove il model binding voleva la route key, e altri due il 2026-09-04, entrambi sul mittente delle email.

### Preparazione

```powershell
cd C:\ARCHIVIO\AI\digithub-connect
php -v                    # deve dire 8.5.0
composer run dev          # server + coda + cron + vite: servono tutti e quattro
```

> ⚠️ **Senza il worker della coda le email non partono.** È l'inciampo più comune: `php artisan serve` da solo non basta.

Per ripartire da dati puliti in qualsiasi momento: `php artisan migrate:fresh --seed` — cancella tutto e ricarica il portale demo.

**Le tre cose che sembrano difetti e non lo sono**, incontrate già durante le prove:

| Cosa vedi | Perché |
|---|---|
| **403** su una pagina `/app/…` | sei collegato come admin: quell'area è di manager e operatori |
| Pagina di login cliccando dalla mail all'operatore | l'agenda è privata; dopo l'accesso ci arrivi |
| Nessuna email | worker della coda non attivo |

---

### Giro 1 — Il cliente (il più importante)

Senza login, in una **finestra anonima** del browser. È il flusso che vedranno le persone vere.

| # | Fai questo | Deve succedere |
|---|---|---|
| 1 | Apri `/p/studio-rossi` | Compaiono i servizi col colore del portale |
| 2 | Guarda la pagina su **telefono vero** | Tutto leggibile, niente scorrimento orizzontale |
| 3 | Clicca un servizio | Calendario del mese, giorni con slot segnati da un pallino |
| 4 | Clicca un giorno **senza** pallino | Non è cliccabile |
| 5 | Clicca un giorno **con** pallino | Compaiono gli orari a destra |
| 6 | Cambia il **fuso** nella tendina | Gli orari si spostano di conseguenza |
| 7 | Scegli un orario | Compare il modulo, con data e ora in cima |
| 8 | Premi **Cambia orario** | Torni agli slot senza perdere il giorno |
| 9 | Invia il modulo **vuoto** | Errori su nome ed email, niente prenotazione |
| 10 | Compila e conferma | Pagina di conferma verde |
| 11 | Sulla conferma: **copia il link** | Aprendolo in scheda nuova rivedi la prenotazione |
| 12 | **Scarica il QR** | Arriva un PNG leggibile, inquadrandolo si apre la stessa pagina |
| 13 | **Sposta** la prenotazione | Link e QR restano **identici**, la data cambia |
| 14 | Torna al servizio | Lo slot vecchio è di nuovo libero, il nuovo non c'è più |
| 15 | **Annulla** dal link | Pagina rossa, e lo slot torna libero |
| 16 | Apri `/p/studio-rossi?embed=1` | Spariscono intestazione e piè di pagina |
| 17 | Cambia lingua in inglese | Tutto in inglese, prenotazione compresa |

### Giro 2 — L'operatore

Accedi con **`giulia@digithub.test`** (operatore del portale demo, password `password`).

**Stato: ✅ completo.** Passi 1–10 il 2026-09-04, passi 11–16 il 2026-09-05.

| # | Fai questo | Deve succedere |
|---|---|---|
| ✅ 1 | Guarda la barra laterale | Solo Dashboard, Prenotazioni, Report, Disponibilità |
| ✅ 1b | Apri **Report** | Numeri delle **sue** prenotazioni, sottotitolo «Come sta andando il tuo lavoro» — non quelli del portale |
| ✅ 2 | Vai a mano su `/app/services` | 403 con spiegazione, non pagina bianca |
| ✅ 3 | Apri **Prenotazioni** | Solo le tue, non quelle dei colleghi |
| ✅ 4 | Annulla una tua prenotazione | Chiede il motivo, poi il cliente riceve l'email |
| ✅ 5 | **Impostazioni → Profilo** | Compila ruolo e biografia |
| ✅ 6 | Guarda la pagina pubblica del servizio | Ruolo e biografia sono lì |
| ✅ 7 | Cambia **fuso orario** nel profilo | Gli orari dell'agenda si spostano |
| ✅ 8 | Cambia **lingua** | L'interfaccia cambia e resta cambiata al riaccesso |
| ✅ 9 | **Disponibilità** → togli un giorno | Sulla pagina pubblica quel giorno sparisce |
| ✅ 10 | Aggiungi **due fasce** nello stesso giorno | Gli slot pubblici rispettano la pausa in mezzo |
| ✅ 11 | Prova a salvare fasce **sovrapposte** | Rifiutato con messaggio chiaro, che dice **quale giorno** |
| ✅ 11b | Correggile e salva | L'errore **sparisce** dallo schermo |
| ✅ 12 | Aggiungi un **blocco orario** su una fascia libera | Quegli slot spariscono dal pubblico |
| ✅ 13 | Aggiungi un blocco su una fascia **già prenotata** | Rifiutato, dice quante prenotazioni ci sono |
| ✅ 14 | Aggiungi una **eccezione** su un giorno prenotato | Rifiutato allo stesso modo. Due strade da provare entrambe: «chiuso tutto il giorno» e orari nuovi che lasciano la prenotazione fuori — il messaggio è diverso |
| ✅ 15 | Eccezione con orari che **coprono** la prenotazione | Accettata, e **sostituisce** la giornata: il resto degli orari settimanali sparisce |
| ✅ 16 | Riduci gli orari settimanali lasciando fuori una prenotazione | **Salva** e avvisa, non blocca. L'avviso elenca le prenotazioni scoperte |

Per riprendere dal passo 11 servono prenotazioni future sull'operatore: `scratchpad/seed-giro2.php` ne crea tre su giorni distinti (una da annullare, una da coprire con blocco ed eccezione, una da lasciare fuori dagli orari). Sulla pagina pubblica scegli **Giulia**, non «operatore indifferente»: *Prima consulenza* ha tre operatori e il calendario mostrerebbe anche la disponibilità degli altri.

### Giro 3 — Il manager

Accedi con **`manager@digithub.test`**.

**Stato: ✅ completo, percorso il 2026-09-05.** Il preavviso si scrive in **minuti**, non in ore. Per i servizi conviene un operatore pulito (Luca o uno nuovo): Giulia porta i segni del Giro 2 e il calendario pubblico non risponde solo alle tue modifiche.

| # | Fai questo | Deve succedere |
|---|---|---|
| ✅ 1 | **Servizi** → creane uno nuovo | Compare subito sulla pagina pubblica |
| ✅ 2 | Metti durata 45 e intervallo 15 | Gli slot pubblici vanno di quarto d'ora |
| ✅ 3 | Metti **preavviso** 48 ore (2880 minuti) | Sparisce tutto ciò che cade prima di quel momento |
| ✅ 4 | Metti **buffer** 15 prima e dopo | Intorno a una prenotazione sparisce un quarto d'ora per lato, oltre alla durata |
| ✅ 5 | Attiva **conferma manuale** | Prenotando esce "Richiesta ricevuta", non "Confermata", e **senza** file calendario |
| ✅ 6 | Conferma dall'agenda | Il cliente riceve la seconda email, quella con il `.ics` |
| ✅ 7 | Nascondi un servizio | Sparisce dal pubblico ma resta in elenco, prenotazioni comprese |
| ✅ 8 | **Staff** → crea un operatore | Credenziali mostrate una volta sola, e funzionano |
| ✅ 9 | Assegna e togli servizi a un operatore | La pagina pubblica si aggiorna |
| ✅ 10 | Attiva **"ricevo prenotazioni anch'io"** | Compari fra gli operatori prenotabili |
| ✅ 11 | Prova a eliminare un operatore con prenotazioni future | Rifiutato, dice quante, e il pulsante è disabilitato |
| ✅ 12 | **Prenotazioni** → prova tutti i filtri e l'esportazione | Si combinano, e il CSV esporta ciò che è a schermo |
| ✅ 13 | Annulla una prenotazione con motivo | Il cliente riceve l'email col motivo |
| ✅ 14 | **Disponibilità** → cambia operatore dalla tendina | Modifichi l'agenda di un altro, e i tuoi orari restano intatti |
| ✅ 15 | **Impostazioni portale** → cambia colore del brand | Cambia sulla pagina pubblica **e** dentro le email |
| ✅ 16 | Copia il **link pubblico** e lo **snippet iframe** | Entrambi funzionano; l'iframe non ha intestazione né piè di pagina |
| ✅ 17 | Attiva **"mandami una copia"** | Prenotando a un operatore arriva un **terzo** messaggio, separato |

Il vecchio passo 18 (mittente su un altro dominio) è passato al Giro 4: dal 2026-09-02 i campi del server sono solo dell'admin.

### Giro 4 — L'admin

Accedi con **`admin@digithub.test`**.

**Stato: ✅ completo.** Passi 1 e 2 il 2026-09-05, passi 3–11 il 2026-09-07. Questi ultimi percorsi **guidando l'applicazione via HTTP** (sessione con cookie, token CSRF, chiamate vere all'endpoint di Livewire) invece che con le mani sulla tastiera: passa da rotte, middleware, policy, componenti e mailer, ma **non giudica l'aspetto** delle schermate — quella parte resta al Giro 5.

| # | Fai questo | Deve succedere |
|---|---|---|
| ✅ 1 | **Portali** → creane uno nuovo | Credenziali del manager mostrate una volta sola |
| ✅ 2 | Accedi con quelle credenziali | Funzionano, e vedi un portale vuoto |
| ✅ 3 | Torna admin, apri il **dettaglio** del portale | Servizi, operatori, contatti |
| ✅ 4 | **Rigenera la password** del manager | Nuova password mostrata; **la vecchia smette di funzionare** |
| ✅ 5 | Configura l'**SMTP** di quel portale | Il riquadro completo, che il manager non vede |
| ✅ 5b | Metti un mittente su un **altro dominio** rispetto al nome utente | Compare l'avviso, ma salva comunque; la prova esce con quel mittente |
| ✅ 5c | Fai **rifiutare** la prova dal server (mittente estraneo, credenziali sbagliate, porta chiusa) | Il campo dice **cosa fare**, il riquadro conserva le parole del server |
| ✅ 6 | **Sospendi** un portale | La sua pagina pubblica dà 404 |
| ✅ 7 | Riattivalo | Torna raggiungibile |
| ✅ 8 | **Account** → filtra per ruolo e portale | I filtri funzionano, anche combinati |
| ✅ 9 | **Disabilita** un account | Al primo caricamento viene buttato fuori, con la spiegazione sulla pagina di login |
| ✅ 10 | Prova ad andare su `/app/bookings` | 403 con la spiegazione dedicata all'admin |
| ✅ 11 | **Elimina** un portale di prova | Spariscono anche utenti, servizi, disponibilità e prenotazioni |

Il passo 5c è nato durante il giro: `scratchpad/fake-smtp.php` accetta ora `--reject-sender=dominio` e `--reject-auth`, e riproduce in locale i rifiuti che il Giro 7 aveva dovuto rimandare a un provider vero.

Cosa resta in piedi dei portali di prova:

| Portale | Manager | Password | Stato |
|---|---|---|---|
| `collaudo-giro-4` | `manager.g4@studio.test` | persa nell'errore del modale | **eliminato** al passo 11, con operatore, servizio e prenotazione |
| `collaudo-giro-4b` | `manager.g4b@studio.test` | `CoE6PetN8xPhLc` | vivo, SMTP svuotato a fine giro |

Per il passo 5 servono i due server SMTP finti accesi (`scratchpad/fake-smtp.php`, porte 1025 e 1026): host `127.0.0.1`, porta `1026`, utente e password qualsiasi, crittografia nessuna.

### Giro 5 — Le cose che i test non possono giudicare

**Stato: in corso dal 2026-09-09. Fermo al passo 3 della Seduta A.** Il banco di prova è
`scratchpad/seed-giro5.php` (da lanciare su un database appena seminato), i passaggi e il
foglio dei ritrovamenti stanno in `scratchpad/giro-5.md`. Guardati: la card del servizio
di gruppo, la conferma manuale, i giorni non selezionabili, la tendina del fuso. Tre dei
quattro hanno prodotto una correzione; la conferma manuale è risultata chiara.

Su ogni schermata le stesse cinque domande:

- [ ] **5 secondi**: sai dire cos'è la pagina e qual è l'azione principale?
- [ ] **Testi**: italiano vero o tradotto dall'inglese? Gergo nostro che il cliente non ha?
- [ ] **Attrito**: quanti passaggi per il compito principale? Ce n'è di superflui?
- [ ] **390 px**: `Ctrl+Shift+M` → iPhone 12. Tabelle, barre filtri, modali, form a due colonne
- [ ] **Errori**: dicono **cosa fare**, o solo cosa è andato storto?

#### Seduta A — il cliente (finestra anonima)

I tre controlli delle correzioni del 2026-09-09:

| # | Fai questo | Deve succedere |
|---|---|---|
| ✅ 1 | *Corso base di contabilità* → ottobre → giorno **13** | Pallino arancione, legenda «Un giorno in arancione è quasi pieno», fascia «· ultimi 2 posti». Settembre giorno 10: 4 e 8 posti liberi, tutto grigio |
| ✅ 2 | *Firma digitale* → ottobre | «Si prenota fino al 9 ottobre» e «Chiuso dal 14 al 20 ottobre» |
| ✅ 3 | *Prima consulenza* → un giorno → **Fuso orario** = America/New_York | Orari scalati di sei ore, «Il portale lavora sull'ora di Europe/Rome», e nel modulo i due orari affiancati |

Poi il flusso. **Si riprende da qui.**

| # | Fai questo | Da giudicare |
|---|---|---|
| 4 | `/p/studio-rossi`, cinque secondi | Si capisce di cosa si occupa lo studio? Prezzi, durate e luoghi si leggono? |
| 5 | *Prima consulenza*, tre operatori con orari diversi | «Indifferente» e la frase «Ti verrà assegnato chi è libero» convincono? Scegliendo Giulia il calendario si restringe |
| 6 | Scegli un giorno, guarda la colonna degli orari | |
| 7 | Il modulo, con le tre domande personalizzate | Si vede cosa è obbligatorio **prima** di sbagliare? Quanti campi prima del pulsante? Il consenso privacy si capisce? |
| 8 | Invia il modulo **vuoto** | Gli errori dicono cosa fare? Stanno accanto ai campi? |
| 9 | **Cambia orario** | Torni agli slot senza perdere il giorno |
| 10 | Compila e conferma | La pagina verde ha tutto quello che serve al cliente? |
| 11 | Copia il link, scarica QR e `.ics` | |
| 12 | **Sposta** dal link | Link e QR **identici**, e rivedi le righe del calendario |
| 13 | **Annulla** dal link con motivo | Pagina rossa, slot di nuovo libero |
| 14 | *Consulenza fiscale* → «Avvisami quando si libera un posto» | La conferma dice cosa succederà e quando? |
| 15 | *Apertura partita IVA*, conferma manuale | Si capisce che **non** è confermata? (già guardato una volta, convinceva) |
| 16 | `?embed=1` | Spariscono intestazione e piè di pagina |
| 17 | Lingua **inglese**, e rifai il passo 7 | Modulo, domande ed errori tradotti? |
| 18 | **390 px** su cinque schermate | Elenco, servizio (calendario + orari affiancati), modulo, conferma, link di gestione |

#### Sedute B, C, D e la passata degli errori

Non iniziate. Elenco delle schermate, credenziali e cosa guardare: `scratchpad/giro-5.md`.

- **Seduta B — operatore** (`giulia@digithub.test`): dashboard, prenotazioni, report, disponibilità, profilo
- **Seduta C — manager** (`manager@digithub.test`): servizi (il form più lungo del prodotto), staff, prenotazioni ed esportazione, clienti, lista d'attesa, report, impostazioni portale, registro
- **Seduta D — admin** (`admin@digithub.test`): portali, dettaglio portale, account, piani, coda (due job falliti veri, con payload autentico)
- **Passata E — errori a comando**: modulo vuoto, domanda obbligatoria in bianco, fasce sovrapposte, eliminazione di un operatore con prenotazioni, PDF come logo, indirizzo email nel campo «Server SMTP», `/app/bookings` da admin
- **Telefono vero**: `npm run build` e `php artisan serve --host=0.0.0.0`, poi l'IP della macchina dal telefono sulla stessa Wi-Fi

### Giro 6 — Le tre aggiunte del 2026-08-27

**Operatore indifferente.** Serve un servizio con **due operatori** che lavorano in fasce diverse.

- [ ] Sulla pagina del servizio parte selezionato *"Operatore indifferente"*, non una persona
- [ ] Il calendario mostra gli orari di **entrambi** — anche quelli in cui solo uno dei due lavora
- [ ] Scegliendo una persona il calendario si restringe ai suoi orari
- [ ] Prenotando senza scegliere, la prenotazione finisce a chi ha **meno appuntamenti futuri**
- [ ] Con un servizio a **un solo operatore** il pulsante *"Operatore indifferente"* non compare

**Logo del portale.** Da *Impostazioni portale* del manager.

- [ ] Un JPG o PNG sotto 1 MB si carica e compare subito nell'anteprima
- [ ] Compare anche in testa alla pagina pubblica e nell'elenco portali dell'admin
- [ ] Sostituendolo, il vecchio sparisce
- [ ] *Rimuovi* riporta alle iniziali colorate
- [ ] Un PDF o un'immagine da 2 MB vengono rifiutati con un messaggio comprensibile

**Esito dell'appuntamento.** Serve una prenotazione **già passata** (filtro *Passate*).

- [ ] Su una passata il menu offre *Segna come completato* e *Segna come assente*
- [ ] Su una **futura** quelle voci non ci sono
- [ ] Su una **annullata** non ci sono
- [ ] Dopo averla segnata, il badge di stato cambia e il filtro per stato la trova
- [ ] Scegliendo l'altro esito, lo stato si corregge

### Giro 7 — La consegna delle email (2026-09-02) ✅ percorso il 2026-09-04

Percorso per intero con **due server SMTP finti in locale** invece di un provider vero (`scratchpad/fake-smtp.php`, porta 1025 = piattaforma, 1026 = portale): si vede il `MAIL FROM` di ogni messaggio e si spegne il server del portale a comando. Da rifare una volta su SMTP reale, dove i rifiuti del mittente sono la cosa che qui non si può riprodurre.

**Da manager**, su un portale senza SMTP proprio.

- [x] Il riquadro dice che manda la piattaforma e che il proprio indirizzo si chiede a noi
- [x] Non c'è nessun campo del server; restano indirizzo di risposta, copia delle prenotazioni, ore di promemoria
- [x] Prenotando dalla pagina pubblica, conferma e copia all'operatore arrivano — entrambe dalla porta della piattaforma, `From: notifiche@digithub.test`, due messaggi separati

**Da admin**, sulla scheda dello stesso portale.

- [x] I campi del server ci sono; salvando parametri validi il riquadro dice *«Configurato, non ancora verificato»*
- [x] Finché è così, una prenotazione manda ancora dall'indirizzo della piattaforma
- [x] *Invia prova* con parametri giusti → riquadro verde *«Invio come …»*, e da lì le email partono dal server del portale
- [x] Scrivere un indirizzo email nel campo del server viene rifiutato con una spiegazione, non con un errore di rete (provati anche `http://` e `:587`, messaggio diverso e giusto)
- [x] Cambiando un parametro e salvando, il portale torna «non verificato» finché non si rifà la prova

**Il guasto.** Con SMTP verificato, cambiare la password sul provider (o mettere una porta chiusa) e far partire una prenotazione.

- [x] Nel riquadro compare *«Un'email non è partita»* con data ed errore
- [x] Le prenotazioni successive continuano ad arrivare, dall'indirizzo della piattaforma
- [x] Nell'elenco portali dell'admin il portale ha il badge rosso *«Email ferme»*
- [x] Rimessa la password giusta, *Invia prova* riporta tutto sul server del portale

### Problemi trovati durante il collaudo

**2026-09-09 · La scarsità dei posti era segnalata dove non c'era.** ✅ risolto
Sulla card del *Corso base di contabilità* la barra colorata è arancione, e si è letta come «pochi posti». Non era un avviso: è `$service->displayColor()`, cioè il colore che il portale dà al servizio — arancione perché così l'aveva messo il seeder. Entrato nella card, i posti erano tanti: una fascia su due ne aveva 4 su 8. Il segnale è stato portato dove il dato è esatto: la **fascia oraria** diventa arancione con «ultimi 2 posti», e il **giorno** si marca solo quando *tutte* le sue fasce sono corte — marcarlo perché una fascia su tre è corta rifarebbe lo stesso danno. Soglia in `Service::seatsRunningLow()`: un quarto della sala, minimo uno; prima di lì è rumore. Nello stesso giro è saltato fuori che `":count posti liberi"` era al plurale fisso — «1 posti liberi» — stessa famiglia del 2026-09-05, sfuggita perché nessuno era passato dai gruppi. Regola in `.ai/rules/portal.md` e `.ai/rules/views.md`.

**2026-09-09 · Un giorno non prenotabile non diceva perché.** ✅ risolto
Su *Firma digitale* metà ottobre era grigia e si è letta come «portale chiuso»: era la finestra di prenotabilità di 30 giorni. Le tre cause — oltre la finestra, portale chiuso, nessuno disponibile — si vedevano identiche, e la chiusura vera (14–20 ottobre) cadeva dentro quel grigio indistinguibile. `App\Support\CalendarNotes` scrive ora le righe sotto il calendario: «Si prenota fino al 9 ottobre» solo se la griglia disegna giorni oltre la finestra, e «Chiuso dal 14 al 20 ottobre» per ogni chiusura che tocca il mese. Solo le date: il motivo che scrive il manager resta nella sua agenda. Vale anche per la pagina di spostamento, che aveva lo stesso difetto.

**2026-09-09 · La tendina del fuso orario non si spiegava.** ✅ risolto
Il collaudatore ha chiesto *perché* ci fosse una scelta del fuso sulla pagina pubblica: il `flux:select` non aveva etichetta e mostrava «Europe/Rome» sopra una colonna di orari, come un'impostazione che nessuno ha chiesto. Etichetta `Timezone` — «Fuso orario», la stessa parola delle altre cinque tendine dell'app, nessuna chiave nuova. Con un fuso diverso da quello del portale compare «Il portale lavora sull'ora di Europe/Rome», e nel modulo i due orari si stampano affiancati (`09:00 (America/New_York)` / `15:00 (Europe/Rome)`): due orari invece di una frase, così mezz'ora di scostamento e cambio dell'ora tornano da soli.

**2026-09-07 · L'errore del server SMTP era scritto per la libreria, non per chi deve rimediare.** ✅ risolto
Facendo rifiutare la prova da un server finto sono usciti tre messaggi, tutti crudi e tutti in inglese dentro una schermata italiana: `Failed to authenticate on SMTP server with username "…" using the following authenticators: "LOGIN", "PLAIN". …` (troncato a metà parola dai 300 caratteri di `readable()`), `Expected response code "250" but got code "550", with message "550 5.7.1 Sender address not allowed".`, e per una porta chiusa un `stream_socket_client()` con dentro un pezzo di messaggio di Windows. Nessuno dei tre dice quale dei sei campi sia sbagliato. Il caso del 550 è quello che pesa: la schermata **prevede** quel rifiuto — l'avviso giallo sul mittente estraneo dice «quasi tutti i server lo rifiutano, e l'errore che restituiscono spiega poco» — e poi mostrava esattamente quell'errore che spiega poco. Aggiunto `App\Support\MailFailure::explain()`, che traduce i casi ricorrenti in una frase che dice cosa fare e restituisce `null` per il resto: sul campo va l'istruzione, nel riquadro restano le parole del server, che sono la prova da girare al provider. `mail_error` continua a conservare il testo grezzo — storage = fatti, presentazione = spiegazione. Regola registrata in `.ai/rules/mail.md`.

**2026-09-05 · Creare un portale finiva in errore 500 mentre mostrava la password.** ✅ risolto
Il modale del form portali chiedeva al browser `@close="$wire.resetValidation()"`. Ma `$wire` raggiunge solo i metodi dichiarati sul componente: `resetValidation()` arriva da un trait di Livewire e non è esposto, quindi `MethodNotFoundException`. Finché il modale si chiudeva da solo nessuno se ne accorgeva; salvando un portale si chiude il form e si apre quello delle credenziali, e l'errore si è mangiato la password generata — mostrata una volta sola. Il portale era stato creato lo stesso: falliva soltanto la chiamata di chiusura. Attributo rimosso (il reset lo fa già chi apre il modale) e regola registrata in `.ai/rules/views.md`.

**2026-09-05 · I filtri non dicevano cosa filtravano.** ✅ risolto
Quattro tendine in fila senza etichetta — «In arrivo», «Tutti gli stati», «Tutti i servizi», «Tutti» — e l'ultima era illeggibile: è l'operatore, ma lo capivi solo per esclusione. Scelto un valore («Confermata», «Giulia Bianchi») la dimensione spariva del tutto. Erano 12 filtri senza nome in 6 pagine; etichettate le quattro barre filtri (Prenotazioni, Registro, Portali, Account) e date un nome alle due date del Registro, prima due campi nudi separati da un trattino.

**2026-09-05 · «In quel periodo ci sono 1 prenotazioni».** ✅ risolto
Nessun messaggio con un conteggio gestiva il singolare: dieci frasi in sette file, tutte scritte al plurale fisso. Non era solo la disponibilità — stessa rottura negli avvisi di eliminazione di portale, account, servizio, operatore e cliente, e nel messaggio di cancellazione dati. Passate a `trans_choice()`, con singolare e plurale scritti in inglese e in italiano.

**2026-09-04 · Il messaggio di prova partiva con il mittente della piattaforma.** ✅ risolto
`MailerTestMail` prendeva il `From` da `mailFromAddress()`, che con `mail_verified_at` ancora null risponde «indirizzo della piattaforma» — ma la prova viaggia sul server del portale, dove quel mittente è estraneo. Su un provider vero è esattamente ciò che fa scattare `550 sender not allowed`: la prova poteva fallire con parametri giusti, oppure passare senza aver mai dimostrato che il mittente del portale è accettato. Ora il `From` della prova viene da `Tenant::ownMailFromAddress()`.

**2026-09-04 · L'anteprima prometteva un mittente che non era in uso.** ✅ risolto
Con i parametri salvati ma non verificati il riquadro «Le tue email si presenteranno così» mostrava già l'indirizzo del portale, contraddicendo l'avviso giallo due centimetri sopra. Ora mostra quello della piattaforma, e quello del portale su una riga «Dopo la prova:».

**2026-08-31 · L'icona dell'occhio della password SMTP finiva sotto il campo.** ✅ risolto
Non era l'icona: in una griglia a due colonne i campi di una riga vengono stirati all'altezza del più alto, e la regola `[data-flux-field] { grid }` faceva crescere anche il contenitore dell'input, contro cui le icone in coda sono posizionate. Aggiunto `content-start` in `resources/css/app.css`. Vale per ogni campo con icona in coda, non solo per la password.

**2026-08-31 · Il calendario pubblico era vuoto l'ultimo giorno del mese.** ✅ risolto
La griglia mostra sempre qualche giorno del mese vicino, ma gli slot venivano cercati solo fra il primo e l'ultimo del mese: il 31 agosto «domani» era il 1° settembre, visibile e mai prenotabile. Ora la ricerca copre tutta la griglia disegnata, sul portale e sulla pagina di spostamento. Stessa radice, il report «mese scorso» sbagliava mese negli ultimi giorni (`subMonth()` dal 31 marzo dà il 3 marzo).

**2026-08-31 · Le email non partivano: l'indirizzo nel campo del server.** ✅ risolto
Nel campo «Server SMTP» c'era la casella di posta invece del server del provider; il trasporto riportava un host che non risolve, che si legge come un guasto di rete. Il campo ora rifiuta un indirizzo, uno schema, una porta o uno spazio. Da lì sono nate due cose più grandi: l'errore di un invio vero che si scrive sul portale, e la consegna che resta sulla piattaforma finché il server del portale non supera una prova.

**2026-08-27 · Il pulsante "Apri la tua agenda" portava a una lista intera.** ✅ risolto
Ora il link arriva filtrato su quel cliente, con l'avviso che prima serve l'accesso.

**2026-08-27 · Lo stesso pulsante dava 403 su pagina bianca.** ✅ risolto
Il permesso era corretto — si collaudava da admin — ma non lo diceva. Aggiunte le pagine 403, 404, 419 e 500.

**2026-08-27 · Uno spazio incollato bloccava l'SMTP.** ✅ risolto
`getaddrinfo failed: Host sconosciuto` per uno spazio iniziale nel nome del server. Ora i campi vengono ripuliti prima della validazione.

**2026-08-27 · Quattro campi email incomprensibili.** ✅ risolto
Raggruppati in un riquadro solo, con anteprima dal vivo delle intestazioni.

**2026-08-27 · L'errore sugli orari restava dopo la correzione.** ✅ risolto
Sovrapponendo due fasce compariva l'errore; correggendole il salvataggio riusciva ma il messaggio restava a schermo. `saveSchedule()` lanciava `ValidationException` a mano, saltando l'azzeramento che `validate()` fa da sé. Stesso difetto trovato e corretto anche nel test SMTP.

_Da riempire man mano. Per ognuno: cosa hai fatto, cosa ti aspettavi, cosa è successo._

---

## Backlog

### Fase 9 — Migrazione dell'interfaccia a Inertia + React (avviata il 2026-10-05)

✅ Completata il 2026-10-07.

Deciso il 2026-10-05: Laravel resta, l'interfaccia passa a Inertia 3 + React 19 in TypeScript, shadcn/ui al posto di Flux, Wayfinder per le rotte. Passaggio **graduale**: una pagina alla volta, test verdi a ogni passo. Per ogni pagina: controller + `Inertia::render`, i `Livewire::test` diventano richieste HTTP con `assertInertia`, i Form object Livewire diventano Form Request, si tolgono i `wire:navigate` che puntano alla pagina nuova.

- [x] 9.0 Infrastruttura (vista radice, props condivise, ponte verso Livewire, `__()` client, tema shadcn), guscio del back-office e **dashboard** — 2026-10-05
- [x] 9.1 Impostazioni dell'account: profilo e foto, aspetto, sicurezza (password, 2FA, passkey), eliminazione account — 2026-10-05
- [x] 9.2 Pagine del manager più semplici: lista d'attesa, clienti, risorse, staff, registro (condiviso con l'admin) — 2026-10-05
- [x] 9.3 Servizi (il form più lungo, pacchetti e requisiti) e Impostazioni portale (branding, logo, SMTP, documenti legali, esportazione) — 2026-10-06
- [x] 9.4 Report, disponibilità, agenda prenotazioni (tabella e tre viste calendario) — 2026-10-06
- [x] 9.5 Pannello admin: portali, dettaglio portale, account, piani, documenti legali, coda — 2026-10-07
- [x] 9.6 Pagine pubbliche `/p/{slug}`: elenco, servizio (calendario, orari, modulo), gestione prenotazione, uscita dalla lista d'attesa; layout del portale con i colori del brand e `?embed=1` — 2026-10-07
- [x] 9.7 Pagine di accesso Fortify (login, recupero e reimpostazione password, sfida 2FA, conferma password, verifica email) — 2026-10-07
- [x] 9.8 Pulizia: via Livewire, Flux, Blaze, `app.css`/`app.js` di Livewire, `VisitNonInertiaPagesInFull`, `layout-notices.blade.php`, layout Blade del back-office; la home `welcome.blade.php` riscritta in React. Restano Blade le email e le pagine di errore — 2026-10-07

Da sistemare strada facendo:
- [x] Notifiche a comparsa (toast) via flash di Inertia: `App\Support\Toast` + `sonner` — 2026-10-05 (9.1)
- [x] Bundle `app.js` (misurato il 2026-11-13): non ci sono più avvisi di Vite; i pezzi sono `app` 139 kB e un pezzo condiviso da 470 kB (≈149 kB compressi) fatto soprattutto di React, Inertia e Radix. Il catalogo italiano pesa 102 kB (30 kB compressi) e non c'è un catalogo inglese: caricarlo «per lingua» non toglierebbe nulla a chi usa l'italiano. Nessun intervento; da rivedere solo se aggiungiamo lingue.
- [x] Contatore «Prenotazioni in arrivo» dell'operatore: ora è il numero vero (`operator.upcoming_count`), non più al massimo 5 — 2026-11-13

### Fase 8 — Completamento del prodotto ✅ completata il 2026-10-04

Scelto dall'utente il 2026-10-04: completare il progetto prima del caricamento su server. Fuori da questa fase: sincronizzazione calendari, link videochiamata, pagamenti dei clienti finali, SMS.

- [x] 8.1 Pulizia del backlog: voci già realizzate spuntate e ricondotte alla fase che le ha chiuse.
- [x] 8.2 Cifratura delle note dei clienti (`bookings.customer_notes`, `customers.notes`) e delle risposte alle domande (`booking_answers.value`).
- [x] 8.3 Secondo pulsante Outlook per gli account Microsoft 365 aziendali.
- [x] 8.4 Esportazione completa di un portale (fine del rapporto, GDPR): ZIP con un JSON per tabella e i file caricati, solo dal manager in Impostazioni portale.
- [x] 8.5 Pagina *Documenti legali* nel pannello admin, con la versione accettata da ogni portale; accettazione del manager in Impostazioni portale e avviso finché manca.
- [x] 8.6 Limiti dei piani applicati e incasso **manuale**: servizi e operatori bloccati oltre il limite, prenotazioni mensili solo con avviso (80% e 100%), SMTP proprio solo se incluso; l'admin segna «pagato fino al», promemoria a 14 e 3 giorni, 7 giorni di tolleranza, poi sospensione automatica.
- [x] 8.7 Agenda: vista settimanale a fasce orarie, appuntamenti sovrapposti affiancati, una lezione di gruppo in un solo blocco; su telefono resta l'elenco per giorno.

### Collaudo complessivo del 2026-10-01 — eseguito in locale, completamento aperto

- [x] Piano e rapporto richiesti in `COLLAUDO.md`, con 72 casi, evidenze, metodi e limiti distinti.
- [x] Percorsi reali browser manager/operatore/cliente/admin, input ostili, isolamento, moduli obsoleti, pacchetti/risorse/gruppi; nuovo operatore e prove irreversibili autorizzate su database separato.
- [x] Correzioni B01–B04 e B06–B10 ripetute; aggiornamenti autorizzati Laravel 13.30/CommonMark 2.10.3; test, Pint, PHPStan e build riusciti.
- [x] B05: conteggio gruppo, titolo Sicurezza, nomi dei comandi e avviso dentro modale corretti e riprovati.
- [x] B11–B13: traduzioni Flux congelate, dettaglio tabella senza note, email con maiuscole che duplica lista d'attesa/notifiche; correzioni e prove browser riuscite. Altri otto casi composti chiusi, incluso iframe.
- [ ] Interventi umani: SMTP proprio nel browser (utente chiede istruzioni nel rapporto), nuova password/token reset, autenticatore e passkey; QR/calendari/telefono reale.
- [ ] Provider reale e DNS, infrastruttura/staging con SQLite e carico simultaneo; ripetizioni UI residue elencate nella matrice di `COLLAUDO.md`.

### Fase 7 — Risorse condivise e appuntamenti con più servizi (piano del 2026-10-01)

Ordine di lavoro concordato: realizzare ora il punto 1; seguire poi i punti 2 e 3 con verifiche separate. Il collaudo precedente resta al Giro 5, Seduta A, passo 4.

**Ripresa il 2026-10-01:** completati anche 7.2 e 7.3, con verifiche automatiche e controllo mirato del modulo nel browser. Prossimo intervento: collaudo complessivo, risoluzioni e migliorie. Il precedente collaudo manuale conserva il punto di ripartenza sopra indicato.

#### 7.1 Gestione e configurazione delle risorse — completata il 2026-10-01

- [x] Sezione **Risorse** per il manager, limitata al proprio portale; nome, tipo, descrizione/note interne, preparazione e riassetto in minuti, stato attivo.
- [x] Una riga per ogni risorsa fisica (Cabina 1 e Cabina 2 sono distinte); modifica, disattivazione e rimozione consentita solo dopo aver scollegato i servizi.
- [x] Periodi di indisponibilità per manutenzione o guasti, inseriti nel fuso del portale e salvati in UTC; aggiunta e rimozione con autorizzazione.
- [x] Nel servizio, requisiti separati: tutti i requisiti sono necessari; dentro ciascuno si sceglierà una delle risorse alternative. Esempio: una fra due cabine **e** un laser specifico. Una risorsa non può comparire due volte nei requisiti dello stesso servizio.
- [x] Interfaccia IT/EN, accesso negato ad altri ruoli, validazione e isolamento fra portali, dati demo e test dei flussi.
- [x] Verifiche: test mirati, Pint, analisi statica e build; migrazioni additive sul database locale senza azzerare i dati.

**Criterio di completamento:** il manager può gestire risorse e indisponibilità e salvare/rileggere i requisiti dei servizi. In questa tappa i requisiti sono configurazione: l'interfaccia deve dichiarare che non limitano ancora gli orari pubblici. L'occupazione automatica entra nel punto 7.2.

#### 7.2 Occupazione delle risorse durante le prenotazioni — completata il 2026-10-01

- [x] Disponibilità calcolata su operatore e tutti i requisiti; scelta automatica fra alternative, senza assegnare la stessa risorsa a due requisiti.
- [x] Allocazioni effettive e finestre di occupazione salvate sulla prenotazione, con preparazione/riassetto; le modifiche al catalogo non riscrivono lo storico.
- [x] Ricontrollo e prenotazione atomica di persone e risorse contro richieste simultanee anche con operatori diversi.
- [x] Richieste in attesa occupano; annullamenti liberano; spostamenti riassegnano tutto oppure lasciano intatta la prenotazione precedente.
- [x] Indisponibilità, disattivazione e cancellazione rifiutano conflitti con occupazioni future. Le prenotazioni già esistenti non ricevono risorse retroattivamente senza una procedura esplicita.
- [x] Servizi di gruppo: occupazione della risorsa per sessione, non per partecipante; liberazione quando l'ultima prenotazione bloccante viene annullata e ricontrollo completo alla riapertura.
- [x] Agenda con risorse assegnate; copertura dei conflitti, alternative, fusi, limiti tra intervalli e regressioni sui servizi senza risorse.

**Criterio di completamento:** due operatori non vendono lo stesso macchinario nello stesso periodo; due cabine equivalenti permettono due appuntamenti compatibili; nessuna assegnazione parziale dopo un errore.

#### 7.3 Pacchetti prestabiliti — completata il 2026-10-01

**Scelte concordate il 2026-10-01:** pause interne configurabili dal manager; una cabina può cambiare tra le fasi. Il pacchetto è un servizio composto (almeno due servizi individuali distinti dello stesso portale), con un solo operatore abilitato a tutti. L'operatore è occupato anche nelle pause; le risorse solo per ciascuna fase più preparazione/riassetto, senza sovrapposizioni incompatibili all'interno dello stesso pacchetto. Prezzo a somma dei componenti oppure specifico. Domande dei componenti e del pacchetto raccolte una volta nel modulo, con etichette contestualizzate; basta una fase con conferma manuale per rendere tutto il pacchetto in attesa. Il preavviso è il più restrittivo e la finestra la più corta. Nomi, prezzi, ordine e orari delle fasi sono snapshot della prenotazione.

- [x] Pacchetti composti da servizi individuali esistenti, consecutivi, con ordine fissato dal manager e un unico operatore abilitato a tutti; i servizi singoli restano disponibili.
- [x] Prezzo pari alla somma o specifico del pacchetto; durata dalle fasi e pause configurabili. Cambio cabina consentito, come concordato prima dell'implementazione.
- [x] Un appuntamento con singole fasi e snapshot di servizi, prezzi, orari e risorse; risorse occupate solo nelle finestre necessarie.
- [x] Una compilazione del modulo, conferma, email e link di gestione; domande aggregate e conferma manuale se richiesta da almeno un componente.
- [x] Spostamento e annullamento dell'intero appuntamento, con test del percorso pubblico e controllo mirato del modulo nel browser. Il collaudo manuale complessivo resta il prossimo intervento.

**Sviluppi successivi da valutare:** composizione libera guidata con compatibilità e ordine; operatori diversi; servizi paralleli e tempi di posa. Non fanno parte del primo rilascio.

### Fase 2 — motore prenotazioni ✅ completata il 2026-08-26
- [x] Tabelle e model `Booking` e `TimeBlock`
- [x] Generatore di slot (`App\Services\SlotFinder`): orari settimanali − eccezioni − blocchi ad hoc − prenotazioni − buffer − preavviso minimo − finestra massima
- [x] Timezone-aware: UTC nel database, fuso del portale per gli orari, fuso del visitatore a schermo
- [x] Calendario pubblico costruito a mano (Flux free non ha date-picker)
- [x] Form di prenotazione + pagina di conferma
- [x] Link di gestione con token non indovinabile: annullamento e spostamento
- [x] Agenda nel back-office con conferma e annullamento motivato
- [x] Blocchi orari ad hoc, nella pagina Disponibilità

**Permessi sulle prenotazioni — implementati (`app/Policies/BookingPolicy.php`):**

| Chi | Cosa può fare |
|---|---|
| Operatore | vede **solo la propria** agenda; annulla le proprie prenotazioni **con motivazione**; blocca fasce ad hoc senza toccare gli orari settimanali |
| Manager | vede tutta l'agenda del portale; annulla qualsiasi prenotazione |
| Conferma manuale | la può dare **sia il manager sia l'operatore assegnato** |

Il blocco ad hoc ha una tabella dedicata `time_blocks`: `availability_exceptions` *sostituisce* gli orari di un giorno, un blocco invece *sottrae* una fascia lasciando aperto il resto.

### Fase 3 — notifiche ✅ completata il 2026-08-27

**Identità del mittente — decisa e implementata, rivista il 2026-09-02.** Dipende da come è configurato il portale:

| Portale | Trasporto | `From:` |
|---|---|---|
| ha un **SMTP proprio verificato** | il suo server | il suo indirizzo — SPF e DKIM del suo dominio tornano, perché è davvero il suo server |
| ha i parametri ma non la verifica, o non ha niente | mailer della piattaforma | indirizzo nostro, con il nome del portale come display name e il suo `Reply-To` |

La seconda riga esiste perché mettere `info@studiorossi.it` nel `From` inviando dai nostri server fa fallire SPF e DMARC del suo dominio: spam o rifiuto. Con SMTP proprio quel vincolo non c'è.

**Il caso normale è il nostro mailer** (come Calendly e Cal.com): l'SMTP del portale è un'eccezione offerta su richiesta, configurata dall'admin, che entra in servizio solo dopo una prova riuscita e ne esce al primo invio fallito.

**Trasporto — deciso il 2026-08-26: solo SMTP, nessuna dipendenza nuova.** Vale sia per la piattaforma sia per i portali. Il provider si sceglie dopo senza toccare il codice, perché parlano tutti SMTP. Si rinuncia per ora a webhook di bounce e statistiche di consegna: richiedono l'API del provider e si aggiungono quando servono.

**Destinatari — deciso il 2026-08-26: email separate.** Al cliente la conferma, all'operatore una email scritta per lui (note del cliente, recapiti, link all'agenda). Niente CC: esporrebbe l'indirizzo dell'operatore, e un testo pensato per il cliente all'operatore serve poco.

#### 3.1 Fondamenta ✅ completata il 2026-08-26
- [x] `MAIL_MAILER=smtp` e variabili documentate in `.env.example`
- [x] In sviluppo si resta su `MAIL_MAILER=log` (le email finiscono in `storage/logs`); Mailpit è comodo per vederle renderizzate ma è un binario esterno, non una dipendenza del progetto
- [x] `tenants.reply_to_email` (nullable, ricade su `contact_email`)
- [x] `bookings.customer_locale` — oggi salviamo il fuso del cliente ma non la sua lingua, e l'email va scritta in quella
- [x] Layout email con il colore del portale, coerente con la pagina pubblica
- [x] Tutte le email in coda; una email fallita non deve mai perdere la prenotazione

#### 3.2 Le email che chiudono i buchi peggiori ✅ completata il 2026-08-26
- [x] **Conferma al cliente** — contiene il link di gestione: da sola risolve metà dei problemi
- [x] **Nuova prenotazione all'operatore** — email dedicata, scritta per lui: note del cliente, recapiti, link all'agenda. Oggi la vede solo se apre l'agenda
- [x] **Richiesta confermata / rifiutata** — chiude la promessa fatta dai servizi con conferma manuale
- [x] **Annullamento** e **spostamento**, verso l'altra parte

- [x] **SMTP per portale**: un'attività può inviare dal proprio server e dal proprio indirizzo. Dal 2026-09-02 lo configura **solo l'admin** e va in servizio solo dopo una prova riuscita; il manager ne vede lo stato e può rifare la prova
- [x] Copia al manager delle nuove prenotazioni, interruttore per portale
- [ ] SMTP della piattaforma da configurare per i portali che non ne hanno uno proprio, più SPF e DKIM sul dominio mittente
- [ ] Avvisare quando i job email di un portale falliscono ripetutamente (oggi finiscono in `failed_jobs` in silenzio)
- [ ] Annotare nella procedura di deploy che cambiare `APP_KEY` rende illeggibili le password SMTP salvate

#### 3.3 Promemoria ✅ completata il 2026-08-27

Obiettivo: tagliare i mancati arrivi ricordando l'appuntamento al cliente.

**Schema**
- [x] `bookings.reminder_sent_at` (nullable) — l'unica difesa contro il doppio invio
- [x] `tenants.reminder_hours_before` (default 24, `null` = promemoria disattivati per quel portale)

**Meccanica**
- [x] Comando `bookings:send-reminders`: seleziona le prenotazioni in `BookingStatus::blocking()`, con `starts_at` dentro la finestra e `reminder_sent_at` nullo, poi invia e marca
- [x] Pianificarlo **ogni 15 minuti** in `routes/console.php`. La finestra da interrogare è `[now + anticipo, now + anticipo + 15 min]`: interrogare un istante esatto salterebbe tutto ciò che cade fra due esecuzioni
- [x] `withoutOverlapping()`, così una esecuzione lenta non ne fa partire una seconda in parallelo
- [x] `Customer\BookingReminderMail`, stesso layout e stesso mailer del portale delle altre

**Da non dimenticare**
- [x] Aggiungere `schedule:work` a `composer run dev`: **oggi non c'è**, quindi in sviluppo il comando non partirebbe mai da solo
- [ ] **In produzione** serve una voce cron che chiami `schedule:run` ogni minuto → vedi [Prima del deploy](#prima-del-deploy)
- [x] Se una prenotazione viene spostata **oltre** l'anticipo, `reminder_sent_at` va riazzerato: altrimenti il nuovo orario non viene mai ricordato
- [x] Niente promemoria per prenotazioni in attesa di conferma — deciso: si saltano

**Test da scrivere**
- [x] Invia una volta sola anche eseguendo il comando due volte
- [x] Salta le annullate e quelle già passate
- [x] Rispetta l'anticipo del portale e i portali che l'hanno disattivato
- [x] Uno spostamento oltre la finestra fa ripartire il promemoria

#### 3.4 Calendario del cliente ✅ completata il 2026-08-27

Obiettivo: far finire l'appuntamento nel calendario del cliente con un clic, e tenercelo aggiornato.

**Il file `.ics`**
- [x] `App\Support\IcsEvent` che genera il testo a mano: il formato è semplice e non serve alcuna dipendenza
- [x] **UID stabile** derivato da `booking.uid`: è ciò che permette a un aggiornamento di sostituire l'evento invece di affiancarlo
- [x] `bookings.ics_sequence` incrementale, alzato a ogni spostamento. Senza, chi sposta due volte si ritrova **tre** eventi in calendario
- [x] `METHOD:REQUEST` su conferma e spostamento, `METHOD:CANCEL` sull'annullamento
- [x] `DTSTART` e `DTEND` in UTC, con `SUMMARY`, `DESCRIPTION`, `LOCATION` e `ORGANIZER` del portale
- [x] Attenzione a due dettagli formali che rompono i calendari se sbagliati: **escape** di virgole, punti e virgola e ritorni a capo nei campi di testo, e **ripiegamento delle righe** a 75 ottetti

**Dove finisce**
- [x] Allegato alle email di conferma, spostamento e annullamento
- [x] Rotta di download `/p/{portale}/b/{token}.ics`, per il pulsante sulla pagina di conferma

**Pulsanti sulla pagina di conferma**
- [x] **Google Calendar** — semplice URL `calendar.google.com/calendar/render?action=TEMPLATE&...`
- [x] **Outlook Web** — URL analogo
- [x] **Scarica .ics** — per Apple Calendar e desktop, che non hanno un URL: il telefono apre il file nel calendario

**Test da scrivere**
- [x] L'UID non cambia dopo uno spostamento, la sequenza sì
- [x] Un annullamento produce `METHOD:CANCEL`
- [x] Virgole e ritorni a capo nelle note del cliente non rompono il file
- [x] Gli orari nel file corrispondono a quelli della prenotazione

### Fase 4 — integrazioni

#### 4.1 Link della call generato automaticamente
- [ ] Interruttore per servizio: "genera un link per la videochiamata"
- [ ] Il link finisce in `location_value` e nell'`.ics`; `LocationType::Video` esiste già
- [ ] v1 con **Jitsi**: stanza casuale derivata dal token, nessun OAuth, funziona subito
- [ ] v2 con **Google Meet** o **Zoom**: richiedono OAuth per portale, quindi dopo la sincronizzazione calendari

#### 4.1b Impersonificazione dal pannello admin ✅ realizzata in 6.6 il 2026-08-30

Deciso il 2026-08-27 di **non** aprire l'agenda dei portali all'admin. Se il giro con le credenziali del proprietario dovesse risultare scomodo, questa è la strada corretta per sostituirlo.

Serve a: assistere un cliente guardando quello che vede lui, senza che nessuno si scambi password.

- [x] Pulsante *"Entra come questo manager"* nel dettaglio portale, riservato all'admin
- [x] Sessione di impersonificazione **a scadenza** (es. 30 minuti), non permanente
- [x] **Banner sempre visibile** durante l'impersonificazione, con il pulsante per uscirne: chi impersona non deve mai dimenticare di non essere sé stesso
- [x] **Registro tracciato**: chi ha impersonato chi, quando è iniziato e quando è finito. È il punto che rende la funzione accettabile rispetto alla privacy, e va scritto prima del resto
- [x] Sola lettura oppure anche scrittura? Deciso in 6.6: anche scrittura, ogni azione attribuita all'admin nel registro (scrivere al posto del cliente è comodo per l'assistenza, il registro copre la responsabilità)
- [x] L'impersonificazione **non** deve poter essere annidata, né usata su un altro admin
- [x] (Risolto nel registro, non nei campi della prenotazione) Le azioni compiute durante l'impersonificazione vanno attribuite all'admin nei campi tipo `cancelled_by_id`, non al manager

Nota pratica sul giro attuale: il pulsante *"Genera nuova password"* nel dettaglio portale **estromette il manager**. Per assistere serve che sia lui a comunicare le credenziali, non che l'admin se le generi.

#### 4.2 Sincronizzazione calendari
- [ ] Google Calendar / Outlook: leggere gli impegni esterni per non doppio-prenotare, e scrivere le prenotazioni nel calendario dell'operatore
- [ ] Webhook per portale
- [ ] Snippet JS di embed oltre all'iframe attuale

### Fase 5 — dal prototipo al prodotto vendibile

Priorità decise con l'utente il 2026-08-28, dopo una lettura d'insieme di cosa manca per vendere il software a studi e attività. Si affronta **un punto alla volta**, nell'ordine sotto.

#### 5.1 Privacy, termini e consenso ✅ completata il 2026-08-28

Oggi il form pubblico raccoglie nome, email, telefono e note libere di persone fisiche **senza informativa, senza consenso e senza una durata di conservazione dichiarata**. È il buco più serio per un prodotto venduto in Italia.

- [x] Campi sul portale: link all'**informativa privacy** e ai **termini di servizio**, più il titolare del trattamento
- [x] In alternativa al link, **caricamento del PDF**: chi non ha un sito su cui pubblicare carica il documento e lo serviamo noi. Il file caricato ha la precedenza sul link
- [x] **Checkbox obbligatoria** nel form di prenotazione, con i link che si aprono in una nuova scheda; senza spunta la prenotazione non parte
- [x] Prova del consenso sulla prenotazione: `privacy_accepted_at` (e la versione/URL accettato, così resta chiaro *cosa* è stato accettato)
- [x] Link a informativa e termini nel piè di pagina pubblico e nelle email
- [x] **Conservazione**: `data_retention_days` per portale (null = non cancellare) e comando pianificato che **anonimizza** le prenotazioni concluse più vecchie della soglia — nome, email, telefono e note via, la riga resta per le statistiche
- [x] **Cancellazione su richiesta**: azione dal back-office per anonimizzare i dati di un cliente, con conferma
- [x] Decisione: i testi legali **non** li fornisce la piattaforma, li mette ogni portale. Noi diamo i campi, i link e il registro del consenso
- [ ] Resta fuori: i campi privacy sono modificabili dal **manager**, non dal form di creazione portale dell'admin. Da aggiungere se l'admin dovrà configurarli in fase di onboarding

#### 5.2 Piani e limiti ✅ completata il 2026-08-28 — *solo la pagina, nessun controllo attivo*

L'utente vuole prima **vedere e definire** i piani, e applicarli in un secondo giro.

- [x] Tabella `plans`: nome, descrizione, prezzo indicativo, e i limiti come colonne (servizi, operatori, prenotazioni al mese, SMTP proprio sì/no, portali…)
- [x] `tenants.plan_id` nullable, **nessun controllo applicato** in questa fase
- [x] Pagina admin *Piani*: elenco, creazione, modifica, e assegnazione del piano a un portale
- [x] Nel dettaglio portale: quale piano ha e i suoi limiti, in sola lettura
- [x] (8.6, 2026-10-04) Far valere i limiti: blocchi alla creazione, avvisi di soglia, sospensione a scadenza; fatturazione manuale fuori dal sistema

#### 5.3 Anagrafica clienti ✅ completata il 2026-08-28

Oggi il cliente è tre stringhe copiate su ogni prenotazione: niente storico, niente "questo è già venuto cinque volte, due volte non si è presentato".

- [x] Tabella `customers` con `tenant_id`, nome, email, telefono, lingua, fuso, note interne; email **unica dentro il portale** (normalizzata in minuscolo)
- [x] `bookings.customer_id` nullable + collegamento automatico alla prenotazione, con backfill dello storico esistente
- [x] I campi `customer_name` / `customer_email` restano **sulla prenotazione** come fotografia del momento: se il cliente cambia cognome, le prenotazioni vecchie non si riscrivono
- [x] Pagina *Clienti* nel back-office: elenco con ricerca, dettaglio con storico prenotazioni, conteggio assenze, note interne
- [x] Solo il manager; l'operatore vede i clienti soltanto attraverso le proprie prenotazioni
- [x] Si incrocia con 5.1: l'anonimizzazione deve svuotare anche l'anagrafica

#### 5.4 Chiusure del portale ✅ completata il 2026-08-28

Le eccezioni di calendario esistono solo **per operatore**: per Natale bisogna passare persona per persona.

- [x] Tabella `portal_closures`: `tenant_id`, giorno di inizio, giorno di fine, motivo
- [x] `SlotFinder` le sottrae **a tutti gli operatori** del portale, come un blocco a giornata intera
- [x] Interfaccia in Disponibilità (sezione riservata al manager): elenco delle chiusure future, aggiunta di un periodo, rimozione
- [x] Deciso: una chiusura che contiene prenotazioni attive si **rifiuta** (come `BlockTime`) oppure si accetta avvisando? Proposta: rifiuta ed elenca cosa c'è dentro
- [x] Le chiusure passate restano come storico, non si mostrano nell'elenco

#### 5.5 Preavviso per annullare o spostare ✅ completata il 2026-08-28

Oggi il cliente annulla anche cinque minuti prima dell'appuntamento.

- [x] `services.cancellation_notice_hours`, **default 48**, impostabile servizio per servizio (0 = sempre permesso)
- [x] Vale per il **cliente** dalla sua pagina di gestione: oltre la soglia niente annulla e niente sposta, con un messaggio che dice entro quando si poteva e come contattare il portale
- [x] **Il portale può sempre**: manager e operatore annullano e spostano anche sotto soglia, è il loro mestiere
- [x] `Booking::isCancellable()` spezzato in "annullabile dal cliente" e "annullabile dal portale", perché oggi è una sola regola usata da entrambi
- [x] La scheda di aiuto del servizio (già presente) guadagna il paragrafo sul preavviso

#### 5.6 Domande personalizzate ✅ completata il 2026-08-28

- [x] Tabella `service_questions`: servizio, etichetta, tipo (**risposta libera breve**, **testo lungo**, **scelta singola**, **scelta multipla**), opzioni, obbligatoria sì/no, posizione
- [x] Risposte salvate con **etichetta e valore copiati** al momento della prenotazione: modificare o cancellare una domanda non deve riscrivere le risposte già date
- [x] Form pubblico: campi generati dinamicamente, validazione dell'obbligatorietà con messaggi tradotti
- [x] Le risposte compaiono nel modale dei dettagli in agenda e nell'email all'operatore
- [x] Interfaccia di gestione dentro il form del servizio: aggiungi domanda, riordina, elimina
- [x] Deciso: tetto di 10 domande per servizio per servizio (proposta: 10) per non trasformare la prenotazione in un modulo

#### 5.7 Non prioritarie ora, ma sul tavolo

Emerse nella stessa lettura d'insieme e **non** scelte dall'utente per questo giro. Restano qui per non riscoprirle fra un mese.

- [ ] **Sincronizzazione calendari** (Google/Outlook, [4.2](#42-sincronizzazione-calendari)): è il difetto n.1 percepito di questi prodotti — l'operatore ha un impegno personale, il software non lo sa e vende lo slot. Lavoro grosso: OAuth per portale, token, webhook
- [ ] **Link videochiamata automatico** ([4.1](#41-link-della-call-generato-automaticamente))
- [x] (6.2) **Servizi di gruppo** con capienza maggiore di uno (corsi, lezioni): oggi ogni slot è per una persona sola
- [x] (6.5) **Lista d'attesa** quando non ci sono slot liberi
- [ ] **SMS o WhatsApp** oltre alla email per i promemoria
- [x] (6.1) **Esportazione CSV** delle prenotazioni e un vero **report**: prenotazioni per servizio e per operatore, tasso di assenza, incassato
- [x] (Fase 7) **Sedi e risorse** prenotabili (stanze, poltrone, attrezzature) oltre alle persone
- [ ] **Pagamenti o caparra** al momento della prenotazione
- [x] (6.7) **Registro delle azioni** (chi ha annullato cosa e quando) oltre ai campi già presenti sulle prenotazioni
- [x] (8.2, 2026-10-04) **Cifratura delle note** (`bookings.customer_notes` e `customers.notes`) con il cast `encrypted`: sono testo libero, nessuna query li filtra, quindi costa poco e toglie dal database il contenuto più delicato. Valutato il 2026-08-28 e rimandato
- [ ] **Blind index sull'email** se servirà cifrare l'anagrafica intera (richiesta di un cliente sanitario, per esempio): colonna HMAC per uguaglianza e unicità, con la ricerca parziale sul nome che però si perde
- [ ] **Requisiti di hosting**: database con encryption at rest e backup cifrati — copre il furto di backup o disco senza toccare il codice, e vale più della cifratura applicativa
- [x] (8.4, 2026-10-04) **Esportazione completa di un portale** (dati e allegati): serve alla fine del rapporto contrattuale, quando il titolare chiede restituzione o cancellazione → vedi [Il ruolo della piattaforma](#il-ruolo-della-piattaforma-e-i-suoi-obblighi)
- [x] (8.5) **Pagina *Documenti legali* nel pannello admin**: DPA, sub-responsabili, informativa della piattaforma, e la versione accettata da ogni portale
- [ ] Produzione: SQLite su server unico (deciso il 2026-10-04), cron, worker, backup, SMTP di piattaforma con SPF e DKIM, avviso sui job email falliti → vedi [Prima del deploy](#prima-del-deploy)
### Fase 6 — strumenti di gestione e tracciabilità

Scelta dall'utente il 2026-08-28, da affrontare **un punto alla volta** nell'ordine sotto. Due voci della lista iniziale — *registro azioni* e *sistema di log completo* — sono la stessa cosa e sono state unite in 6.7.

#### 6.1 Esportazione e report ✅ completata il 2026-08-28
- [x] Esportazione **CSV** delle prenotazioni dall'agenda, che rispetta i filtri attivi (ricerca, stato, servizio, operatore, periodo)
- [x] Esportazione CSV dell'anagrafica clienti
- [x] Pagina **Report** (manager e operatore, ciascuno col proprio perimetro): prenotazioni per periodo, per servizio, per operatore, tasso di assenza, valore delle prenotazioni completate
- [x] Formato pensato per Excel italiano (separatore `;`, BOM UTF-8), altrimenti gli accenti e le colonne saltano
- [x] I dati cancellati (5.1) restano cancellati anche nell'esportazione

#### 6.2 Servizi di gruppo ✅ completata il 2026-08-28
- [x] `services.capacity` (default 1): quante persone può ospitare lo stesso slot
- [x] `GroupSlotFinder` decide gli orari: sessione con posti liberi, sessione piena, oppure operatore libero invece di escluderlo alla prima
- [x] La pagina pubblica mostra i posti rimasti; a posti esauriti lo slot sparisce
- [x] Deciso con l'utente: **più operatori** sullo stesso slot di gruppo (una lezione, un docente) — sì, salvo prova contraria
- [x] Coperto da test prima di toccare il resto (slot, lock, disdette). Ogni caso va coperto da test prima di toccare il resto

#### 6.3 Riordino dei servizi drag & drop ✅ completata il 2026-08-28
- [x] La colonna `position` esiste già: manca l'interfaccia
- [x] Riordino con `wire:sort` di Livewire, salvataggio immediato
- [x] L'ordine vale sulla pagina pubblica e nel back-office

#### 6.4 Avatar degli operatori ✅ completata il 2026-08-28
- [x] `users.avatar_path` esiste già e non la usa nessuno
- [x] Caricamento dalla scheda staff e dal profilo, stesse regole del logo (JPG/PNG/WebP, 1 MB, niente SVG)
- [x] Ricade sulle iniziali colorate quando manca, come per il portale
- [x] Compare nella scelta dell'operatore sulla pagina pubblica e nell'agenda

#### 6.5 Lista d'attesa ✅ completata il 2026-08-28
- [x] Quando un giorno o un servizio non ha slot liberi, il cliente lascia i suoi dati e il periodo che gli andrebbe bene
- [x] Alla liberazione di uno slot (annullamento, spostamento) chi è in lista riceve un avviso
- [x] Deciso con l'utente: avviso a tutti e chi arriva primo prenota, oppure diritto di prelazione a tempo per il primo della lista
- [x] Scadenza gestita: una lista d'attesa vecchia di due mesi non serve a nessuno
- [x] Consenso privacy come per la prenotazione (5.1), e cancellazione insieme al resto

#### 6.6 Impersonificazione dal pannello admin ✅ completata il 2026-08-30
- [x] Già dettagliata in [4.1b](#41b-impersonificazione-dal-pannello-admin): pulsante nel dettaglio portale, sessione a scadenza, banner sempre visibile, uscita immediata
- [x] **Dipendeva da 6.7**, fatto prima: senza registro non è accettabile, perché nessuno saprebbe chi ha fatto cosa
- [x] Le azioni compiute impersonando portano il nome dell'admin **nel registro**, non al manager

#### 6.7 Registro delle azioni e log di sistema ✅ completata il 2026-08-30
- [x] Tabella `activity_logs`: chi (utente, ruolo, portale), cosa (azione), su cosa (modello e id), quando, da quale IP, più un payload con il contesto
- [x] Scritto dalle **azioni di dominio**, non sparso nei componenti: prenotazioni create, confermate, annullate, spostate, esiti, servizi e staff modificati, chiusure, cancellazione dati, accessi e cambi password, impersonificazioni
- [x] Pagina **Registro** (un solo componente, due rotte) con filtri (portale, utente, tipo di azione, intervallo di date, ricerca libera) e paginazione
- [x] Il manager vede il registro **del proprio portale**; l'admin vede tutto
- [x] Conservazione: `activity:prune` ogni lunedì, un anno di storico, va potato come le prenotazioni (5.1)
- [x] Nel payload solo riferimenti e numeri, mai copie di nomi ed email di dati personali: nel payload vanno riferimenti, non copie di nomi ed email

#### 6.8 Avviso sui job email falliti ✅ completata il 2026-08-30
- [x] Un invio fallito finisce in `failed_jobs` e non lo sa nessuno
- [x] Comando pianificato ogni ora che riepiloga i fallimenti per portale e avvisa l'admin oltre una soglia
- [x] Pagina **Coda** nel pannello admin: errori recenti, riprova e rimozione per portale
- [x] Si incrocia con 6.7: ogni fallimento è una riga del registro

#### 6.9 Rimandati, da riprendere dopo
- [x] (8.6) Applicazione dei limiti dei piani ([5.2](#52-piani-e-limiti--completata-il-2026-08-28)), incasso manuale
- [ ] Sincronizzazione calendari ([4.2](#42-sincronizzazione-calendari)) e link videochiamata ([4.1](#41-link-della-call-generato-automaticamente))
- [ ] SMS/WhatsApp, unione schede cliente, sottodomini (cifratura delle note e vista settimanale fatte in 8.2 e 8.7)
- [ ] Documenti legali della piattaforma (pagina pronta in 8.5, testi dal legale) e requisiti di hosting → [Il ruolo della piattaforma](#il-ruolo-della-piattaforma-e-i-suoi-obblighi) e [Prima del deploy](#prima-del-deploy)
### Da valutare
- [x] **L'admin può vedere l'agenda di un portale? No** — deciso il 2026-08-27. `/app/*` resta chiuso all'admin: i dati personali dei clienti di un'attività non passano sotto gli occhi del gestore della piattaforma. Per l'assistenza si accede **con le credenziali del proprietario**, fornite da lui.
  - Conseguenza da tenere presente: il pulsante *"Genera nuova password"* nel dettaglio portale **estromette il manager**, perché la sua vecchia password smette di funzionare. Per l'assistenza serve che sia lui a comunicare le proprie credenziali, non che l'admin se le generi.
  - Alternativa tenuta a mente per il futuro: **impersonificazione** dal pannello admin, dettagliata in [4.1b](#41b-impersonificazione-dal-pannello-admin).
- [x] **Round robin** — fatto il 2026-08-27: pulsante *"Operatore indifferente"*, calendario unito, assegnazione al meno carico con passaggio al successivo se perde la corsa
- [x] Test di concorrenza reale sul lock: processi paralleli su file SQLite, `BookingConcurrencyTest` (2026-10-04)
- [x] (8.7, 2026-10-04) Agenda: terza vista **settimanale a fasce orarie** (blocchi proporzionali alla durata, come Google Calendar) accanto a tabella e mese
- [ ] Gli indirizzi dei seed (`@digithub.test`) vengono rifiutati dall'SMTP reale con `550 recipient rejected`: ogni prova riempie il log di errori. Da decidere se usare domini validi nei seed o tornare a `MAIL_MAILER=log` in sviluppo
- [x] (8.3, 2026-10-04: pulsanti Outlook.com e Microsoft 365) Pulsante Outlook: puntava solo a `outlook.live.com` (account personali). Gli account Microsoft 365 aziendali usano `outlook.office.com` — valutare un secondo link
- [x] **Stati `completed` e `no_show`** — fatti il 2026-08-27: voci di menu nell'agenda, solo su appuntamenti finiti e non annullati
- [ ] Il calendario pubblico raggruppa i giorni nel fuso del **portale**: per un visitatore molto lontano uno slot può cadere in un altro giorno locale (oggi mostrato accanto all'orario)
- [ ] Sottodomini `{slug}.dominio.it` (oggi solo path `/p/{slug}`) — serve DNS wildcard + certificati
- [x] **Logo del portale** — fatto il 2026-08-27 (JPG/PNG/WebP fino a 1 MB, iniziali colorate come ripiego)
- [x] Avatar degli operatori → fatto in 6.4
- [x] Riordino servizi drag & drop → fatto in 6.3
- [x] Domande personalizzate sul form di prenotazione → pianificate in [5.6](#56-domande-personalizzate-nel-form-di-prenotazione)
- [ ] Pagamenti → vedi [5.7](#57-non-prioritarie-ora-ma-sul-tavolo)
- [x] ~~Passaggio da SQLite a MySQL/Postgres prima della produzione~~: deciso di restare su SQLite il 2026-10-04
- [x] Git inizializzato e progetto caricato su GitHub — 2026-08-27

### Controlli ricorrenti
- [ ] `lang/it.json` copre tutte le chiavi `__()` presenti nel codice, e non ne tiene di morte
- [ ] I test non dipendono da «oggi»: `startOfMonth()` prima di `addMonth()`/`subMonth()`, e date fissate dove il giorno del mese conta
- [ ] Ogni nuova entità di dominio ha `tenant_id` e uno scope per portale
- [ ] Ogni nuova rotta è dietro il middleware `role:` corretto
- [ ] Ogni nuova azione Livewire chiama `authorize()` o `abort_unless()`

---

## Diario

### 2026-11-13 — Dialogo «Dettagli» di una prenotazione: controllo e pulsante «Conferma»

**Controllato** (desktop e telefono, aperto da menu «Dettagli» e dall'indirizzo `?booking=`): prenotazione confermata con nota del cliente, annullata, in attesa. Nessun overflow, nessun errore in console; il clic sulla riga della tabella non apre il dialogo (si apre dal menu «…», come previsto).

**Corretto.** Per una richiesta **in attesa di approvazione** il dialogo non aveva un modo evidente di confermarla: «Conferma» stava solo nel menu «…». Ora il piede del dialogo ha un pulsante «Conferma» visibile quando `can.confirm` è vero; premuto, la prenotazione passa a «Confermata», il pulsante sparisce e compare la notifica (verificato nel browser). Sulle prenotazioni già confermate o annullate il pulsante non c'è.

**Aperto.** Su telefono il menu «…» sta sotto «Chiudi» (l'ordine del piede si inverte su schermo stretto): leggibile ma poco evidente. Nessun commit fatto.

### 2026-11-13 — Passkey nascoste dietro `PASSKEYS_ENABLED`

**Obiettivo.** L'utente vuole solo la 2FA con l'app: le passkey spente e invisibili, riattivabili con una variabile nel `.env`.

**Fatto.**
- `config/fortify.php`: `Features::passkeys()` compare solo con `PASSKEYS_ENABLED=true` (default `false`; un valore non valido vale `false`). Con le passkey spente Fortify non registra le rotte `passkey.*`.
- Pagine: il pulsante nel login e nella conferma password e la sezione in Sicurezza spariscono; `.well-known/passkey-endpoints` risponde 404.
- Gli indirizzi non sono più importati da `@/routes/passkey` (file generati da Wayfinder, che senza le rotte non esistono più e romperebbero la build): arrivano dal server come props, `null` quando le rotte non ci sono.
- `.env` e `.env.example`: `PASSKEYS_ENABLED=false` con commento; `phpunit.xml` lo fissa a `false`. Regola in `.ai/rules/auth.md`.

**Verifica.** Nuovo `PasskeysSwitchTest` (11 casi: lettura della variabile con valori validi e sbagliati, nessuna rotta, pagine senza pulsante né sezione, indirizzi dal server quando ci sono, 404/200 dell'indirizzo `.well-known`); `SecurityTest` e `InertiaBridgeTest` adeguati (le passkey ora si accendono nel test). Mutazioni (configurazione sempre accesa, `.well-known` sempre aperto) intercettate. Rigenerati i file Wayfinder: la cartella `passkey` sparisce, `tsc` e build ok. Browser, server di prova con la variabile spenta e accesa: spenta nessuna traccia di «passkey» in login, conferma e Sicurezza; accesa pulsante e sezione presenti; nessun errore in console.

**Aperto.** Se un giorno si riattivano: https, `APP_URL` vero e prova con un dispositivo reale. Nessun commit fatto.

### 2026-11-13 — Prova reale della 2FA con l'app di autenticazione

**Fatto.** Prova eseguita dall'utente con un telefono vero, sull'account `admin@digithub.test` del proprio database di sviluppo: attivazione dalla pagina Sicurezza (QR, conferma con il primo codice), accesso con la 2FA, **codice sbagliato rifiutato e blocco dopo i tentativi**, accesso con un codice di recupero, visualizzazione dei codici dalla pagina Sicurezza. Tutto come previsto; dal database: segreto e codici di recupero salvati, conferma registrata. Nessuna modifica al codice.

**Decisioni.** La 2FA resta con l'app (TOTP) e le passkey; la 2FA via email non è costruita (Fortify non la offre): se servirà, sarà un'alternativa scelta dall'utente e solo dopo aver configurato l'SMTP della piattaforma, perché un'email che non parte lascerebbe l'utente fuori.

**Aperto.** L'account admin di sviluppo ha ora la 2FA attiva: i codici di recupero vanno conservati (ogni uno vale una volta). Passkey da provare con un dispositivo reale.

### 2026-11-13 — Chiusura dei punti aperti del passaggio visivo, contatore e bundle

**Fatto.**
- **Registro e `uid`:** il soggetto di una prenotazione ora è «servizio · data e ora» (fuso del portale), non più lo `uid`, che è il segreto del link di gestione del cliente. `Activity::labelOf()` tratta `Booking` a parte; `uid` non è più tra gli attributi usati come etichetta. Le righe già scritte restano com'erano (in sviluppo; nessun dato in produzione). Aggiunta anche l'etichetta «Impersonato da» (`impersonated_by`), che il giro precedente aveva perso. Regola aggiornata in `.ai/rules/support.md`.
- **Vista mese su telefono:** nelle celle compaiono puntini colorati (toccando si apre il giorno) invece di orari tagliati; da `md` in su resta l'elenco di prima.
- **Contatore «Prenotazioni in arrivo» dell'operatore:** `upcoming_count` è il conteggio vero (prima al massimo 5, perché contava l'elenco).
- **Bundle:** misurato, nessun intervento (vedi Backlog): le traduzioni sono 30 kB compressi, il peso è di React, Inertia e Radix.

**Verifica.** Test aggiunti/aggiornati in `ActivityLogTest` e `DashboardTest`; mutazioni (etichetta con `uid`, contatore dall'elenco) intercettate. Browser: vista mese su telefono (puntini) e desktop (invariata).

**Aperto.** Dialogo «Dettagli» di una prenotazione e passkey con un dispositivo reale (la 2FA con l'app è stata provata, vedi sotto).

### 2026-11-12 — Passaggio visivo delle pagine React (primo giro)

**Obiettivo.** Ripetere sulle pagine React il controllo visivo che il collaudo aveva fatto sulle vecchie pagine Livewire.

**Come.** Database usa e getta con `migrate:fresh --seed`, server su una porta a parte, Chrome headless via CDP: 32 pagine (admin 10, manager 12, operatore 5, pubbliche 5) in 4 modi (desktop e telefono 390 px, chiaro e scuro) = 128 viste, con rilevazione di errori in console, risposte HTTP e overflow orizzontale, più screenshot guardati a mano (dashboard, prenotazioni, servizi, disponibilità, report, dettaglio portale, staff, clienti, pagine pubbliche, operatore).

**Trovato e corretto.**
- **Prenotazioni (manager) usciva dallo schermo su desktop fino a ~1400 px**: la riga dei filtri ha larghezze fisse (1088 px in tutto) e non andava a capo, mentre su un portatile da 1366 px restano ~1046 px. Ora va a capo (`lg:flex-wrap`).
- **Disponibilità su telefono**: «Copia sugli altri giorni» usciva dal riquadro e ogni fascia oraria andava a capo con il trattino da solo. Ora i due orari restano sulla stessa riga e i pulsanti vanno a capo.
- **«Scegli Un Giorno»** (pagina pubblica): la classe `capitalize` serviva ai nomi dei giorni, ma colpiva anche il testo fisso. Ora solo il nome del giorno.
- **`DatabaseSeeder` non dava il `license_id` al portale demo** (il seeder usa `WithoutModelEvents`, quindi l'evento di creazione non scatta): con `migrate:fresh --seed` il campo «Tenant id» dell'admin restava vuoto. Ora il seeder lo assegna. Test aggiunto in `TenantLicenseIdTest` (rosso senza la correzione).

**Non toccato, da decidere.**
- Il riquadro «Inizia da qui» della dashboard del manager resta sempre visibile, anche quando servizi e operatori ci sono già, con il testo «Tre passaggi e il tuo portale è pronto».
- Il logo del guscio è ancora quello Laravel (segnaposto).
- Su telefono le tabelle (portali, staff, clienti) scorrono in orizzontale dentro il riquadro: accettabile, ma le colonne di stato e azioni restano fuori vista.
- «La tua settimana» dell'operatore ripete il giorno per ogni fascia invece di raggrupparle.
- Il segnaposto della ricerca nell'elenco portali si taglia su telefono.
- **Secondo giro (stesso giorno): guardate a mano anche** profilo, sicurezza (dopo la conferma della password), aspetto, risorse, lista d'attesa, registro, documenti legali, piani, coda, pagina 403 dell'operatore, impostazioni del portale, le tre viste calendario (mese, settimana, giorno), il menu azioni della riga e i dialoghi (nuovo servizio, risorsa, operatore, chiusura, nuovo portale) su desktop e telefono: nessun overflow, nessun errore in console.
- **Registro, corretto:** i dettagli mostravano le chiavi grezze in inglese (`role: manager`, `service: …`) nell'interfaccia italiana; ora `ActivityController::contextLine()` le traduce (27 chiavi note, 16 voci nuove in `lang/it.json`; una chiave sconosciuta resta com'è) e un valore vero/falso si legge Sì/No invece di «1» o vuoto. I *valori* (per esempio `portal`, `manager`) restano com'erano. Test aggiunto in `ActivityLogTest`. Resta aperto: sotto «Prenotazione creata» compare lo `uid` della prenotazione (scelta voluta in `.ai/rules/support.md`), ma lo `uid` è anche il segreto del link di gestione del cliente: da confermare che sia accettabile mostrarlo al manager e all'admin.
- **Vista mese su telefono:** gli orari nelle celle si tagliano («09:0»); basterebbero i puntini.
- Il logo Laravel segnaposto compare anche nelle pagine di accesso e conferma password.
- Non verificato a mano: il dialogo di dettaglio prenotazione (aperto dal menu «Dettagli»), la sequenza di conferma 2FA e le passkey con un dispositivo reale.

**Verifica.** Suite completa: 953 test, 951 passati, 2 saltati; PHPStan 0, Pint ok, `tsc` 0, traduzioni complete. Il database e il server di prova sono usa e getta (nello scratchpad), il server è fermato. Nessun commit fatto.

### 2026-11-12 — `LICENSE_ENFORCEMENT=on` nel `.env` di sviluppo

**Fatto.** Nel `.env` locale (git-ignored) c'è ora `LICENSE_ENFORCEMENT=on`, preceduto da un commento in italiano con la tabella off / observe / on (chiamate al License Manager, blocco, pagine pubbliche, piano e limiti, «pagato fino al», scheda in dashboard, «Verifica ora»). `.env.example` resta `off`; `phpunit.xml` fissa `off`, quindi la suite non cambia.

**Effetto in sviluppo.** `studio-rossi` è esente (nessun effetto); `digithub` è governato, con copia attiva (piano Studio): nessun blocco, il manager vede la scheda «Licenza». Con lo scheduler acceso il License Manager viene richiamato ogni 5 minuti.

**Prova con sospensione (segnalata dall'utente).** L'utente ha sospeso la licenza nel pannello e riferisce che il portale si è bloccato. Controllo del 22:46: nel database la copia di `digithub` risulta ancora `active` (ultima risposta valida 22:20), mentre il License Manager non risponde (`unreachable` negli ultimi tentativi) e per progetto una risposta mancata **non blocca** e tiene l'ultima copia valida. Quindi la sospensione non è registrata nel database che leggo: da riverificare con il License Manager acceso (nuovo «Verifica ora»).

**Aperto.** Scenari ancora da provare dal pannello: riattivazione, rinnovo con cambio piano (i limiti si restringono), revoca, tenant senza licenza (`no_license`). Nessun commit.

### 2026-11-12 — Scheda «Licenza» nella dashboard del manager

**Obiettivo.** Ogni cliente (manager del portale) vede lo stato della propria licenza con tutti i dati, importi compresi, e può aggiornarlo con un pulsante. Decisioni dell'utente: importi al manager (dovuto, pagato, ancora da pagare), aggiornamento ogni 5 minuti con l'orario dell'ultimo aggiornamento visibile (non una volta al giorno), prezzo di rinnovo, scheda **solo con `on`**.

**Fatto.**
- Migrazione `add_balance_and_renewal_prices_to_tenant_licenses_table`: `due_cents`, `paid_cents` e `renewal_prices` (JSON). `LicenseService` tiene i totali e i prezzi ancora in vendita (`active: true`), ordinati dal più breve, il prezzo «a vita» per ultimo; un prezzo che non ha la forma del contratto si scarta (mai indovinato); una risposta «non attivato» azzera totali e prezzi vecchi.
- `DashboardController`: il manager riceve `manager.license` (stato, colore, piano, inizio, fine, fine tolleranza, ultimo aggiornamento, totali formattati, prezzi di rinnovo, id del portale) solo se il License Manager governa il portale (`on` e non esente); `null` altrimenti. Un credito (saldo negativo) si legge «Credito» con importo positivo. Operatori e admin non ricevono nulla.
- React: componente `LicenseCard` nella dashboard del manager, con il pulsante «Aggiorna» (stessa rotta `license.recheck`: pausa di 10 secondi e 6 richieste al minuto; resta in dashboard con il messaggio «Licenza controllata.»), avviso se l'ultimo tentativo è fallito («si vedono gli ultimi dati noti») e id del portale copiabile.
- Il riquadro «Licenza» delle impostazioni del portale è stato **tolto** (ora vive solo in dashboard); la regola in `database.md` («saldo solo all'admin») è aggiornata.

**Decisioni.**
- L'aggiornamento resta ogni 5 minuti (`licenses:refresh`): una sola passata al giorno ritarderebbe di ore rinnovi, sospensioni e scadenze (che scattano a mezzanotte italiana) e farebbe rifare la chiamata alle richieste degli utenti, perché la copia resterebbe più vecchia di 10 minuti. Un controllo in più a mezzanotte e 15 si può aggiungere senza togliere gli altri.
- Totali e prezzi sì, **elenco dei singoli pagamenti no** (sono dati personali e non li salviamo).
- Con `off` e `observe` il manager non vede la scheda: nessun aggiornamento automatico la alimenta.

**Verifica.** `php artisan test --compact`: **951 test, 949 passati, 2 saltati, 5.449 asserzioni** (13 nuovi: scheda e importi, credito, saldo a zero, vita, tolleranza, copia in errore, assenza per operatori/admin e quando non governa, aggiornamento dal pulsante; prezzi tenuti/ordinati/scartati e azzeramento nel servizio). PHPStan 0 errori, Pint ok, `tsc` 0 errori, build ok, traduzioni complete. Otto guasti introdotti apposta (scheda mostrata quando non governa, credito negativo, prezzi ritirati tenuti, ordine, prezzi malformati accettati, totali non salvati, prezzi vecchi dopo «non attivato», scheda agli operatori) tutti intercettati. Browser (finto License Manager con prezzi, enforcement `on`): 14 controlli su 14 (scheda con piano, date, importi, prezzi ordinati e senza quello ritirato, id; «Aggiorna» che mostra il piano cambiato; niente scheda per operatore ed esente; tolleranza con «Rinnova entro»; impostazioni senza il vecchio riquadro).

**Aperto.** Prova reale con il License Manager vero e `on`: la scheda dipende dai prezzi `planType.prices` che arrivano dal contratto (verificare con il piano reale). Nessun commit fatto.

### 2026-11-12 — Prova reale con il License Manager locale

**Obiettivo.** Passo 3 del Prompt 2: provare il collegamento contro il License Manager vero (`http://localhost:3000`, software `DH_CONNECT_001`), con la chiave letta dal `.env` e mai stampata (ne ho controllato solo formato e lunghezza: `lk_` + 43 caratteri). La modalità `observe` solo da riga di comando, `.env` intatto (`LICENSE_ENFORCEMENT` resta `off`).

**Fatto.** Portale di prova `digithub` (id 2, registrato dall'utente con una licenza emessa):
- `licenses:refresh --tenant=2`: risposta valida, **attiva, piano Studio, scadenza 12 dic 2026, tolleranza fino al 19 dic, da incassare € 79,99**; copia salvata senza anagrafica né pagamenti.
- Portale **non registrato** (`studio-rossi`, id 1): `404 tenant_not_found` → `not_activated`, blocca back-office e pubblico, motivo `tenant_not_found`.
- **Chiave sbagliata** (valore finto): `401` → resta la copia valida marcata stale, errore `unauthorized`, **nessun blocco**.
- **Codice software sbagliato**: `403 software_mismatch` → stessa cosa, errore `software_mismatch`.
- Chiave e software veri di nuovo: stato pulito, nessun errore.
- Pagina admin del portale (browser, observe, database di sviluppo): la sezione «Licenza» mostra stato Attivo, scadenza, tolleranza, piano della licenza e piano locale applicato (Studio), ultimo controllo nel fuso del portale e saldo da incassare.
- `licenses:suspended-portals`: nessun portale spento.

**Verifica sulla checklist §10 della guida.** Fatti con il License Manager vero: 404 `tenant_not_found`, 200 attiva con piano, 401, 403 `software_mismatch`, e che un guasto non blocca chi ha una copia valida. **Da fare nel pannello del License Manager** (non si possono scatenare da qui): `no_license` (tenant senza licenza), sospendi → blocco e riattiva → sblocco, rinnovo con cambio di piano (limiti ridotti), revoca. Tutti già coperti da test con `Http::fake` e, per blocco e ritorno, dalla prova nel browser con un finto License Manager.

**Aperto.** I quattro scenari da pannello (sopra). Prima di passare a `on`: `studio-rossi` (demo) nel database di sviluppo ha ora una copia `not_activated` e non è esente: va segnato «Fuori dalla licenza», altrimenti `on` lo bloccherebbe. Nessun commit fatto.

### 2026-11-12 — Esportazione JSON del cliente per il License Manager

**Obiettivo.** Poter importare nel License Manager cliente e tenant di un portale in un passaggio: un file JSON per portale con la struttura di `docs/struttura-json-registrazione-cliente.md` (stessi nomi e regole del License Manager; domani lo stesso formato sarà il corpo di una registrazione automatica).

**Fatto.**
- `App\Support\LicenseManagerExport::payload()`: `schemaVersion` 1, `exportedAt` (UTC), `software` (da `LICENSE_SOFTWARE_CODE`), `tenant.externalId` (= `license_id`) e il blocco `customer` con i 17 campi sempre presenti, `null` e mai testo vuoto. Niente chiave, niente dati di licenza, niente manager né clienti del portale.
- Rotta admin `GET /admin/portals/{tenant}/license-manager.json` (`admin.portals.license-json`, `Admin\LicenseExportController`): scarica `<slug>-license-manager.json` con `Cache-Control: no-store`, riservata all'admin (`manageBilling`).
- Elenco admin dei portali: accanto all'UUID nella colonna «Tenant» un pulsante di download. È disabilitato, con il motivo nel tooltip, finché i dati di fatturazione sono incompleti (elenco di cosa manca) o `LICENSE_SOFTWARE_CODE` non è impostato.
- Ogni download scrive `portal.license_json_exported` nel registro della piattaforma (solo l'id del portale, nessun valore).

**Decisioni.**
- Il pulsante **scarica** il file (la struttura del documento dice «un file JSON per ogni portale»); una versione «copia negli appunti» si aggiunge in un attimo se serve.
- Si rifiuta l'esportazione di un portale con dati di fatturazione incompleti: il file non passerebbe i controlli del License Manager. Lo stesso `BillingData::gaps()` decide per il pulsante e per la rotta.
- Il codice software viene solo dalla configurazione, mai dal codice.

**Verifica.** `php artisan test --compact`: **938 test, 936 passati, 2 saltati, 5.307 asserzioni** (16 nuovi in `LicenseJsonExportTest`: struttura esatta per azienda, privato ed estero; campi sempre presenti e mai vuoti; file scaricabile e non in cache; niente di altro nel file; il contenuto passa le stesse regole del form di fatturazione e ne esce invariato; rifiuti con messaggio; registro senza valori; solo admin; stato dei pulsanti in lista). PHPStan 0 errori, Pint ok, `tsc` 0 errori, build ok, traduzioni complete. Guasti introdotti apposta (id numerico come `externalId`, esportazione di un portale incompleto, testo vuoto al posto di `null`, download non registrato, file in cache, download aperto al manager) tutti intercettati. Browser (copia del database): pulsante attivo con link al file su un portale completo, disabilitato con motivo sugli altri, file servito come allegato con la struttura attesa (5 controlli su 5).

**Aperto.** «Importa da JSON» nel License Manager (lato suo) e, più avanti, la registrazione automatica con lo stesso corpo. Nessun commit fatto.

### 2026-11-12 — Identificativo del portale per il License Manager: UUID al posto dell'id numerico

**Obiettivo.** L'`externalId` mandato al License Manager era `tenants.id` (autoincrement): dipende dal database e riparte da 1 se lo si ricrea o si ripristina un backup vecchio, quindi un portale nuovo potrebbe ereditare la licenza di uno vecchio. Meglio un UUID, deciso con l'utente prima che qualunque portale fosse registrato nel License Manager.

**Fatto.**
- Migrazione `add_license_id_to_tenants_table`: `tenants.license_id` (string 36, nullable, **indice unico**), con riempimento dei portali esistenti (provato su una copia del database di sviluppo; applicata anche a quello reale).
- `Tenant::booted()`: alla creazione `license_id` si genera da solo (`Tenant::newLicenseId()`: UUID minuscolo, controllo su indice unico e fino a 3 tentativi, poi eccezione); un portale esistente **non può cambiarlo** (`LogicException`), né per assegnazione di massa (non è `fillable`).
- Al License Manager va `license_id` (`LicenseService`; senza, errore `no_license_id` e nessuna chiamata). Lo stesso valore è il «Tenant id» nel dettaglio admin, nel blocco da copiare dei dati di fatturazione, nel riquadro del manager e nella schermata di blocco.
- Elenco admin: colonna «Tenant» con i primi 8 caratteri e copia dell'UUID intero (`copy-icon-button.tsx`); i campi copiabili sono più larghi; la ricerca cerca anche per `license_id`.
- `tenants.id` resta chiave primaria e interna: id nei log, nelle chiavi di cache e nelle chiavi esterne.

**Decisioni.**
- Colonna separata e non cambio della chiave primaria: l'`id` è la chiave esterna di tutto e su SQLite cambiarla vorrebbe dire riscrivere molte tabelle.
- Il controllo «già usato?» è una lettura su indice unico e non una scansione dei tenant; il vincolo del database è ciò che regge anche contro due creazioni nello stesso istante. Il ciclo fino a 3 tentativi non scatta mai in pratica.
- Colonna nullable a livello di database (SQLite non la rende obbligatoria senza ricostruire la tabella a cui puntano tutte le altre); il modello la riempie sempre.

**Verifica.** `php artisan test --compact`: **922 test, 920 passati, 2 saltati, 5.206 asserzioni** (8 nuovi in `TenantLicenseIdTest`: formato, unicità, immutabilità, assegnazione di massa, indice unico, rigenerazione, rinuncia dopo 3 tentativi, ricerca). PHPStan 0 errori, Pint ok, `tsc` 0 errori, build ok, traduzioni complete. Cinque guasti introdotti apposta (id numerico al License Manager, id modificabile, UUID già usato non rilevato, UUID non generato, ricerca senza tenant) tutti intercettati. Browser (Chrome headless, copia del database con migrazione e riempimento): 6 controlli su 6 (colonna, tooltip, ids tutti diversi, copia negli appunti, ricerca per frammento, dettaglio con lo stesso UUID).

**Aperto.** Prova reale con il License Manager: il tenant di prova da registrare è il `license_id` del portale. Nessun commit fatto.

### 2026-11-12 — Collegamento al License Manager (Prompt 2): la licenza governa accesso e piano

**Obiettivo.** Il License Manager (`GET /api/v1/license`, sola lettura, contratto in `docs/guida-integrazione-licenze.md`) diventa la fonte unica per l'accesso e il piano dei portali, senza doppio governo con le scadenze locali (`paid_until`, promemoria, sospensione). Specifica in `docs/prompt-2-collegamento-api.md`; piano e decisioni 1–4 approvati dall'utente.

**Fatto.**
- `App\Services\LicenseService`: unica porta verso il License Manager (header `Authorization: Bearer`, tenant e `software` come parametri, timeout 5 s). Restituisce un `App\Support\LicenseStatus` già interpretato. Cache per portale (5 min per i 200, 60 s per i 404), copia dell'ultima risposta in `tenant_licenses`, blocco `429` con `Retry-After`, errore senza risposta precedente = «non verificabile». Piano dal nome `planType.name` (trim + minuscole) abbinato ai `Plan` locali; nome sconosciuto = piano con `position` più bassa e avviso nel log.
- `LICENSE_ENFORCEMENT` = `off` (default: nessuna chiamata automatica), `observe` (controlla e logga una volta al giorno cosa bloccherebbe) o `on`. Variabili in `config/services.php` e `.env.example` (senza valori); il codice software sta solo in config/.env.
- `Tenant::isOperational()` (pagine pubbliche: 404 come un portale sospeso, link di gestione prenotazione aperti), `Tenant::effectivePlan()` (usato da `PlanUsage` e `planAllowsCustomMailer`), `Tenant::license()`; colonna `tenants.license_exempt` e interruttore «Fuori dalla licenza» in admin (il portale demo del seeder è esente).
- Middleware `EnsureLicenseAllowsAccess` (dopo `HandleInertiaRequests`): manager e operatori di un portale non valido vanno a `/license` senza essere disconnessi; restano aperti export dei dati, impostazioni dell'account, uscita e «Verifica di nuovo» (`license.recheck`, solo manager). L'admin non è mai fermato; l'admin in impersonificazione scavalca il blocco e **ogni schermata lo dice in rosso**; un manager vero non scavalca nulla.
- Schermata `license/blocked` (React): motivo, data, id del portale copiabile, contatto (`LICENSE_SUPPORT_EMAIL` o mittente), export, uscita; l'operatore vede solo «rivolgiti a chi gestisce il portale».
- `LayoutNotices`: avviso licenza per il manager (rosso in tolleranza, ambra da 14 giorni, rosso da 3 giorni), avviso di scavalcamento durante l'impersonificazione, avviso all'admin se il License Manager non è utilizzabile con la configurazione attuale. Per un portale governato l'avviso del periodo pagato sparisce.
- Admin: sezione «Licenza» nel dettaglio portale (stato, scadenza, tolleranza, piano della licenza e piano locale applicato, ultimo controllo, saldo da incassare **solo admin**, interruttore esenzione, «Verifica ora» che salta la cache con pausa per portale e `throttle`), campo piano del form in sola lettura per un portale governato, «Pagato fino al» sostituito da una nota. Manager: riquadro «Licenza» nelle impostazioni (stato, scadenza, piano, id, mai importi).
- Comandi: `licenses:refresh` (ogni 5 minuti, `withoutOverlapping`, 100 portali per giro dai più vecchi, si ferma sul `429`; non fa niente con `off`) e `licenses:suspended-portals` (sola lettura: elenca i portali con `is_active=false` con `paid_until`, data della sospensione per mancato pagamento, esenzione e ultima risposta, da controllare a mano prima di passare a `on`).
- `CheckSubscriptions`, `PaidUntilController` e `SavePortal` si fanno da parte per i portali governati; per esenti, `off` e `observe` tutto resta com'era.
- Elenco admin dei portali: nuova colonna «Tenant» in fondo con l'identificativo del portale per il License Manager (UUID accorciato a 8 caratteri, intero nel tooltip, con pulsante di copia); la ricerca trova un portale anche per tenant.
- Registro: `portal.license_exempt` a livello piattaforma (come la fatturazione). Regole registrate in `database.md`, `services.md`, `tests.md`.

**Decisioni.**
- Copia ridotta in `tenant_licenses`: verdetto, date, nome del piano, saldo; **niente** anagrafica del cliente né pagamenti (deviazione dalla guida §6, approvata). Fuori dall'esportazione del portale.
- Servizio non raggiungibile: si usa l'ultima risposta anche oltre le 72 ore (allarme in admin e nei log, nessuno penalizzato); chi non ha mai avuto risposta resta fuori dal back-office («impossibile verificare»), le pagine pubbliche restano aperte.
- La licenza non cambia mai `is_active` e non riscrive `plan_id` (piano effettivo calcolato): la sospensione manuale dell'admin è una cosa separata e un portale rinnovato riparte da solo.
- Soglie dell'avviso «in scadenza»: il License Manager segnala 14 e 7 giorni (`EXPIRING_14`, `EXPIRING_7`), non 3. L'avviso compare quando il License Manager dice `EXPIRING_14` (14 giorni, come i promemoria di oggi) e diventa rosso a 3 giorni calcolati dalla data di scadenza che arriva nella risposta: solo per il testo dell'avviso, mai per decidere l'accesso.
- Con `off` il «Verifica ora» dell'admin è l'unica chiamata possibile. Con `observe` il manager non vede il riquadro licenza (non governa niente).
- Fusi: date mostrate nel fuso del portale.

**Problemi e soluzioni.**
- Il browser ha scovato che il middleware reindirizzava alla schermata di blocco anche le pagine **pubbliche** di un portale visitate da chi era collegato: `portal.*` e `home` ora sono fuori dal perimetro del middleware (test dedicato).
- `tests/Pest.php` ora chiama `Http::preventStrayRequests()` e `phpunit.xml` fissa URL e chiave finti: il License Manager vero non si raggiunge dai test.
- I click con le coordinate non sempre partono nel browser headless: il pilota usa il clic DOM per i pulsanti normali e `pointerdown` per i menu Radix.

**Verifica.** `php artisan test --compact`: **914 test, 912 passati, 2 saltati, 5.178 asserzioni** (116 nuovi sul tema: `LicenseServiceTest`, `LicenseEnforcementTest`, `LicenseCommandsTest`, `LicensePagesTest`; tutti con `Http::fake`). PHPStan 0 errori, Pint ok, `npm run types` 0 errori, build ok, chiavi di traduzione tutte presenti. Sette guasti introdotti apposta (enforcement `off` che blocca, chiamate con `off`, manager vero che scavalca, `CheckSubscriptions` che ignora la licenza, licenza che spegne `is_active`, contatti della piattaforma a un operatore, anagrafica salvata) tutti intercettati. **Prova nel browser** (Chrome headless, copia del database, finto License Manager locale con lo stesso contratto): 35 controlli su 35 (blocco di manager e operatore, portale non registrato, in tolleranza, in scadenza, esente, pagine pubbliche, export raggiungibile, impersonificazione con avviso rosso, esenzione e «Verifica ora» dall'admin, piano bloccato nel form, ritorno dopo «Verifica di nuovo»). **Non ancora fatta:** la prova reale con il License Manager locale (serve il tenant di prova) e la checklist §9 voce per voce sull'ambiente reale.

**Aperto.** Prova reale con il License Manager su `http://localhost:3000` (tenant di prova da decidere). Passaggio a `on`: prima `licenses:suspended-portals`, segnare gli esenti (demo e di prova) e passare da `observe`. In produzione servono lo scheduler (anche per `licenses:refresh`) e un software e una chiave del License Manager diversi per ambiente (l'`externalId` è l'UUID `tenants.license_id`). Nessun commit fatto.

### 2026-11-12 — Dati di fatturazione del portale (Prompt 1 del collegamento al License Manager)

**Obiettivo.** Raccogliere, quando l'admin crea o modifica un portale, chi paga la piattaforma (privato o azienda), con gli stessi nomi e le stesse regole di DigitHub License Manager (`docs/riferimento/customer-profile.ts`), così oggi si copiano a mano e domani si inviano via API. Specifica in `docs/prompt-1-dati-fatturazione.md`.

**Fatto.**
- Tabella 1:1 `tenant_billing_profiles` (colonne tutte facoltative), modello `TenantBillingProfile`, enum `BillingKind` (`PERSON`/`COMPANY`), `Tenant::billingProfile()`, factory con stati `person`/`foreign`/`incomplete`; il portale demo del seeder ha un profilo azienda completo.
- `App\Support\ItalianTaxId` (cifra di controllo del codice fiscale con omocodia e della P.IVA) e `App\Support\BillingData` (normalizzazione, regole per tipo e nazione, elenco di cosa manca, blocco da copiare, email per gli avvisi, nomi dei campi cambiati): porting PHP del riferimento TypeScript.
- `PortalRequest` normalizza in `prepareForValidation()` e applica le regole di `BillingData` alla sezione annidata `billing`; `SavePortal` salva il profilo nella stessa transazione tramite la nuova `Actions\Tenants\SaveBillingProfile`. Messaggi e nomi dei campi in `lang/it.json`.
- Permessi: `TenantPolicy::manageBilling` (solo admin), usata da `PortalRequest::authorize()` e dal dettaglio portale.
- React: `components/billing-fields.tsx` (sezione «Dati di fatturazione» nella finestra del portale: i campi seguono Privato/Azienda, errori accanto al campo, cursore sul primo campo da correggere in ordine di pagina), `components/copy-button.tsx`, sezione «Dati di fatturazione» in `admin/portal.tsx` (chip Completi/Incompleti con ciò che manca, Tenant id ben visibile e copiabile, «Copia dati di fatturazione»). `CopyableInput` accetta ora un `id`.
- Registro: evento `portal.billing_updated` con i soli nomi dei campi, etichetta in `ActivityController`.
- Regole registrate: `database.md` (eccezione per `ExportPortal`), `support.md` (BillingData unico punto, solo admin), `tests.md` (`portalForm()` porta già `billingForm()`).

**Decisioni.**
- Tabella separata e non colonne sulla Tenant: `ExportPortal` esporta il modello Tenant intero, le colonne sarebbero finite nello zip. Il profilo resta **fuori dall'esportazione** (sono dati con cui la piattaforma fattura il portale, non dati del portale restituiti a fine rapporto) e fuori dalle pagine del manager e dalle pagine pubbliche.
- L'evento del registro è scritto a livello piattaforma (`tenant_id` e soggetto nulli, il portale solo nel contesto): il registro del portale lo legge il manager, che non deve nemmeno sapere che esiste.
- Portale nato prima della tabella: sezione tutta vuota in modifica = nessun profilo e «Incompleti»; basta un campo (la nazione IT di default non conta) e valgono tutte le regole. Nei portali nuovi la sezione è sempre obbligatoria. Un profilo esistente non si può svuotare.
- Rispetto al riferimento: da noi i campi «completi» sono obbligatori al salvataggio (nel riferimento `billingGaps` segnala soltanto) e l'email è obbligatoria; nell'elenco di cosa manca c'è anche l'email.
- Le etichette del blocco copiabile («Partita IVA», «Codice SDI»…) sono italiane fisse e non seguono la lingua dello schermo: sono dati per il software di fatturazione. Nomi dei campi del form in snake_case (`first_name` per `firstName`).
- Errore sul gruppo «SDI o PEC» mostrato sul campo SDI.

**Problemi e soluzioni.**
- Il nome legale di un'azienda viaggia come `company_name` nel form e si salva in `name` (nome mostrato di entrambi i tipi), come nel riferimento.
- `public/hot` rimasto dallo stop forzato di `ferma-progetto.bat` (processo Vite ucciso) faceva puntare le pagine a Vite spento: pagina vuota. Cancellato; ora `ferma-progetto.bat` lo toglie da solo (provato).
- `record-rule` ha messo la regola di `app/Support/**` in `portal.md` allargandone l'ambito: spostata a mano in `support.md` e ripristinati `portal.md` e l'indice.
- La migrazione è stata applicata anche al database di sviluppo (non distruttiva): lì i portali esistenti risultano «Incompleti».

**Verifica.** `php artisan test --compact`: **797 test, 795 passati, 2 saltati, 4.482 asserzioni** (erano 727 prima: i nuovi sono 70, `BillingDataTest` per i casi del riferimento TypeScript e `PortalBillingTest` per i flussi). PHPStan 0 errori, Pint ok, `npm run types` 0 errori, build ok, chiavi di traduzione client e server tutte presenti. Tre guasti introdotti apposta (SDI/PEC non più richiesto, permesso aperto al manager, evento legato al portale) sono tutti stati intercettati dai test. **Prova nel browser** (Chrome headless pilotato dal protocollo DevTools, su una copia del database con server su un'altra porta): 20 controlli su 20 — form con i campi giusti per Privato/Azienda, creazione di privato (digitato con spazi e minuscole), azienda italiana ed estera, errore con campi conservati e cursore sul codice fiscale, dettaglio con chip e Tenant id, testo copiato identico all'atteso, portale vecchio salvato con la sezione vuota e poi con «sezione obbligatoria» e cursore sulla ragione sociale. Nessuna prova su telefono né in tema chiaro.

**Aperto.** Prompt 2 (collegamento API al License Manager: `Http`, `Cache`, `config/services.php`) non iniziato. Da decidere se le email automatiche della piattaforma useranno `BillingData::noticeEmail()` (oggi nessuno la chiama). Nessun commit fatto; `docs/` (riferimento TypeScript e i due prompt) non è tracciata.

### 2026-11-12 — Cartella «comandi server» con gli script .bat

**Fatto.** `comandi server/` in radice, quattro script da doppio clic (tutti provati davvero, con le variabili `NO_PAUSE`/`NO_BROWSER` per non fermarsi):
- `avvia-progetto.bat`: controlla PHP ≥ 8.4 e la porta 8000, crea `.env`/database demo/dipendenze se mancano, applica le migrazioni, apre il browser, lancia `composer run dev`.
- `ferma-progetto.bat`: chiude l'albero di Vite/concurrently di questo progetto, toglie `public/hot` rimasto dallo stop forzato, i `php artisan serve|queue:|schedule:` orfani e chi ascolta su 8000 e 5173. Attenzione: i `php artisan` li chiude **tutti**, di qualunque progetto.
- `zip-backup.bat`: zip di tutto (anche `.env`, database, `vendor`, `node_modules`, `.git`; ~100 MB) in `..\digithub-connect-react-zip\`. Contiene i segreti.
- `zip-server.bat`: `npm run build`, poi copia in una cartella temporanea solo il necessario e vi reinstalla `vendor` senza pacchetti di sviluppo (`--no-dev --classmap-authoritative`); niente `.env`, database, file caricati, log, test, sorgenti React, documentazione. ~11 MB. Provato: l'app parte dallo zip in produzione (98 rotte).

**Decisioni.** Per i `.bat` aggiunta in `.gitattributes` la regola `*.bat text eol=crlf`: con gli LF le etichette `goto` si rompono. Gli script usano `%SystemRoot%\System32\tar.exe` (quello di Git non scrive zip) e testo senza accenti (codepage di cmd).

**Aperto.** Sul server servono ancora i passi a mano stampati dallo script (`.env`, `key:generate` solo la prima volta, `migrate --force`, `storage:link`, cron dello scheduler e worker). Corretto anche il commento di `DatabaseSeeder::seedPlans()`, che diceva ancora «nessun limite è applicato» (non più vero dalla 8.6).

### 2026-11-12 — Commit della Fase 9 e correzione sugli operatori dei servizi

**Fatto.** Commit unico della Fase 9 (363 file). Una revisione di sicurezza automatica ha segnalato che `ServiceRequest` validava `operator_ids.*` con `exists:users,id`: un manager poteva collegare ai propri servizi utenti di un altro portale. Corretto con `Rule::exists(User::class, 'id')->where('tenant_id', …)` più `distinct`, e `SaveService::syncRelations` rifiltra gli id sul portale prima del `sync`. Test: «a service cannot be assigned to staff of another portal» in `ServiceManagementTest` (creazione e modifica), verificato rosso senza la correzione.

**Verifica.** Test dei servizi (54) verdi, PHPStan 0 errori, Pint ok. Suite completa non rilanciata.

**Aperto.** Invariato: controllo visivo dell'interfaccia React e collaudo da rifare sulle pagine nuove.

### 2026-10-07 — Fase 9.8: pulizia finale, Livewire e Flux fuori

**Obiettivo.** Chiudere la Fase 9: togliere Livewire, Flux e Blaze con tutto ciò che serviva alla convivenza, e portare in React l'ultima pagina rimasta, la home della piattaforma.

**Fatto.**
- Home: `resources/js/pages/welcome.tsx` (rotta `Route::inertia('/', 'welcome')`, nessun layout: header e footer suoi), stessi testi e stessi link di prima. Il logo delle pagine di accesso ora porta alla home con un `<Link>`.
- Errori durante una visita Inertia: `AppServiceProvider::handleInertiaErrors()` (con `Inertia::handleExceptionsUsing`) prende il posto del ponte `VisitNonInertiaPagesInFull`. Una pagina (GET) che finisce in errore diventa una visita completa dello stesso indirizzo, e il browser disegna la pagina di errore Blade; un'azione (POST/PUT/PATCH/DELETE) rifiutata torna alla pagina di partenza con un toast («Il tuo account non può farlo.», «Quello su cui stavi lavorando non esiste più.», pagina scaduta, troppe richieste, errore nostro). I caricamenti completi, le risposte JSON e i redirect restano a Laravel.
- Rimossi con `composer remove`: `livewire/livewire`, `livewire/flux`, `livewire/blaze` (decisione del piano di Fase 9). Eliminati: il middleware ponte, `app/Livewire/Actions/Logout` (inutilizzata: il logout è di Fortify), il layout Blade del back-office con sidebar e header, gli otto componenti Blade rimasti, `partials/head`, le personalizzazioni in `resources/views/flux`, `welcome.blade.php`, `resources/css/app.css` e `resources/js/app.js` di Livewire. `resources/css/react.css` diventa `resources/css/app.css` (aggiornati `vite.config`, `app.blade.php`, `components.json`).
- Regole: tolte `flux.md`, `components.md`, `pages.md` (parlavano di file che non esistono più); riscritta `views.md` (Blade solo per radice, errori, email); la regola sui conteggi con `trans_choice` e sulle etichette dei filtri è passata in `resources.md`; aggiornate `js.md`, `tests.md`, `support.md`, `availability.md`, `models.md`, `database.md`, `controllers.md`. Commenti di codice che descrivevano la convivenza aggiornati.
- Test: `InertiaBridgeTest` ora prova la nuova gestione degli errori (pagina in errore → visita completa, anche per un indirizzo inesistente; azione rifiutata → indietro con toast; fuori da Inertia tutto come prima). Nessun test tolto.

**Decisioni.**
- Le pagine di errore restano Blade, autosufficienti: devono leggersi anche con gli asset rotti. Per questo una visita Inertia in errore le carica in modo completo invece di avere una copia React.
- La chiave del tema in `localStorage` resta `flux.appearance`: cambiarla avrebbe azzerato la scelta chiaro/scuro di chi l'aveva già fatta.

**Problemi e soluzioni.**
- `composer remove` lancia da solo `boost:update` (script `post-update-cmd`), e la versione di Boost installata ha riscritto `CLAUDE.md` e `AGENTS.md` togliendo anche le regole generali (foundation, Boost, PHP, Pint) e ha cancellato le skill `testing-best-practices`, `infer-conventions` e `wayfinder-development`, oltre a quelle di Livewire, Flux e Blaze. Non era voluto: ripristinate le sezioni dalla versione in git (senza la parte Livewire), le due skill tracciate da git e `wayfinder-development` dal pacchetto `laravel/wayfinder`; `boost.json` ha di nuovo l'elenco completo delle skill. **Attenzione:** un prossimo `composer update` rilancia `boost:update` e rifà lo stesso danno, finché Boost non offre di nuovo quelle skill.
- Le viste Blade compilate in `storage/framework/views` citavano ancora Livewire e facevano fallire le pagine di errore nei test: `php artisan optimize:clear`.
- Un test dell'errore 403 caricato per intero trovava ancora l'header `X-Inertia` delle richieste precedenti: `flushHeaders()`.
- La suite ora gira anche con la memoria di default: `php artisan test --compact` non si blocca più a 128 MB (succedeva dentro i test Livewire).

**Verifica.** Suite completa con `php artisan test --compact` (memoria di default): **726 test, 724 passati, 2 saltati, 4.163 asserzioni**. PHPStan 0 errori, Pint ok, `tsc` 0 errori, chiavi di traduzione client tutte presenti, build ok (un solo CSS da 86 kB e l'entry da 136 kB, più il pezzo delle traduzioni). Prova HTTP: `/`, `/login`, `/p/studio-rossi` senza più script di Livewire; una visita Inertia verso un indirizzo inesistente risponde 409 verso lo stesso indirizzo, che caricato per intero mostra «Questa pagina non esiste». Nessuna prova visiva nel browser.

**Aperto.** Controllo visivo complessivo dell'interfaccia nuova (tutte le aree e i tre ruoli, chiaro e scuro, telefono). Il collaudo in [COLLAUDO.md](COLLAUDO.md) era stato fatto sulle pagine Livewire: va ripetuto sulle pagine React. Resta in backlog il peso del pezzo delle traduzioni (caricarle per lingua). Nessun commit fatto.

### 2026-10-07 — Fase 9.7: pagine di accesso in React

**Obiettivo.** Portare in React le pagine di Fortify: accesso, recupero e reimpostazione della password, sfida a due fattori, conferma della password, verifica dell'email (e la registrazione, oggi spenta).

**Fatto.**
- `FortifyServiceProvider::configureViews()` restituisce pagine Inertia `auth/*` con le props che servono: `status` della sessione, `canResetPassword`, `canRegister`, token ed email del reset, regole della password per i password manager. Nessun controller nuovo: i form postano alle rotte di Fortify, che rispondono con redirect ed errori in sessione.
- React: `AuthLayout` (scelto in `app.tsx` per le pagine `auth/…`), `auth-header.tsx` (titolo e messaggio di stato), `passkey-verify.tsx` (accesso e conferma con passkey tramite `@laravel/passkeys`), pagine `auth/login|forgot-password|reset-password|two-factor-challenge|confirm-password|verify-email|register`.
- Eliminati: le sette view Blade di accesso, i layout `auth` (card, simple, split), i componenti Blade `auth-header`, `auth-session-status`, `passkey-verify` e `resources/js/passkeys.js` con la sua voce in `vite.config`.
- Test: i test di rendering in `tests/Feature/Auth/` ora controllano componente e props (anche lo stato «verification-link-sent» dopo il nuovo invio e il token del reset); la sfida 2FA ora viene anche aperta dopo il redirect. `PreferencesTest` legge la lingua dalla prop invece del testo «Accedi».
- Regola nuova: `.ai/rules/auth.md`.

**Decisioni.**
- Il codice a due fattori è un solo campo numerico (`one-time-code`, 6 cifre) invece delle sei caselle di Flux: stesso nome `code` per Fortify, e il riempimento automatico del telefono funziona.
- Dopo un accesso con passkey resta una visita completa a `response.redirect`, come prima: la sessione cambia proprietario e la pagina d'arrivo la rilegge da zero.
- Le pagine di accesso seguono il tema scelto (chiaro/scuro/sistema) come le altre: il layout Blade dello starter kit forzava `class="dark"`.
- Nessun selettore di lingua aggiunto: la versione Blade non l'aveva; `?lang=` continua a funzionare.

**Problemi e soluzioni.**
- Nessun bug trovato in questa fase. Il registro delle rotte di Wayfinder non genera `register.store` perché la registrazione è spenta: la pagina di registrazione posta a `/register` scritto a mano.

**Verifica.** Suite completa (`php -d memory_limit=1G vendor/bin/pest --compact`): **724 test, 722 passati, 2 saltati, 4.151 asserzioni**. PHPStan 0 errori, Pint ok, `tsc` 0 errori, chiavi di traduzione client tutte presenti, build ok. Prova HTTP come farebbe il client Inertia (XSRF, `X-Inertia`): `/login` e `/forgot-password` con il componente giusto; password sbagliata → di nuovo il login con «Le credenziali non sono corrette.»; password giusta → `/dashboard` con il manager collegato; `/user/confirm-password` in React. Nessuna prova visiva nel browser (passkey e 2FA vanno provati a mano).

**Aperto.** Controllo visivo delle pagine di accesso, accesso con passkey e codice 2FA reale. Per la 9.8: oltre a Livewire, Flux, Blaze, `app.css`/`app.js` e il ponte, resta `welcome.blade.php` (home della piattaforma, Blade con componenti Flux): va riscritta senza Flux o portata in React. Nessun commit fatto.

### 2026-10-07 — Fase 9.6: pagine pubbliche in React

**Obiettivo.** Migrare le pagine che vedono i clienti dei portali: l'elenco dei servizi, la pagina del servizio con calendario, orari, modulo di prenotazione e lista d'attesa, la pagina della prenotazione con spostamento e annullamento, l'uscita dalla lista d'attesa. Con i colori del portale e `?embed=1` per l'iframe sui siti dei clienti.

**Fatto.**
- Controller in `app/Http/Controllers/Portal/`: `PortalController` (elenco), `ServicePageController` (pagina e prenotazione), `BookingPageController` (pagina, spostamento, annullamento), `WaitingListController` (iscrizione, pagina di uscita, uscita). Form Request `PublicBookingRequest` (domande del servizio comprese) e `JoinWaitingListRequest`. Rotte nuove in `routes/booking.php`: `portal.service.book`, `portal.service.waiting-list`, `portal.booking.cancel`, `portal.booking.reschedule`, `portal.waiting-list.destroy`.
- `App\Support\SlotCalendar`: la griglia del mese e gli orari del giorno, condivisi da pagina del servizio e spostamento. `App\Support\PortalPage`: lingua del portale, props del layout, `?embed=1` portato su ogni link, limite delle azioni pubbliche.
- React: `PortalLayout` (scelto in `app.tsx` per le pagine `portal/…`, colori del brand sulle variabili di shadcn), `slot-picker.tsx`, `portal-logo.tsx`, pagine `portal/show|service|booking|waiting-list`. Lo stato del calendario sta nella query string (`month`, `operator`, `d`, `tz`, `reschedule`), così il link si condivide e il tasto indietro funziona; il fuso del browser si adotta al primo arrivo come prima. `LocaleSwitcher` accetta `side` e `align` per l'header.
- Eliminati: le quattro pagine Livewire pubbliche, i partial `booking-calendar|slots|form|questions|phases|waiting-list`, il layout `portal.blade.php`, i componenti Blade `portal-header|footer|logo` e `user-avatar`.
- Test: `PublicBookingTest` riscritto in HTTP; convertiti i casi pubblici di `ActivityLogTest`, `CalendarNoticeTest`, `CancellationNoticeTest`, `CustomerDirectoryTest`, `GroupServiceTest`, `PrivacyTest`, `PublicPortalTest`, `ResourceAllocationTest`, `RoundRobinTest`, `SecurityAuditTest`, `ServicePackageTest`, `ServiceQuestionTest`, `WaitingListTest`, `CalendarFileTest`, `LocaleSwitcherTest`, `LocalizationTest`, `OperatorAvatarTest`, `PortalLogoTest`, `ServiceColorTest`. Helper `bookingForm()` e `offeredTimes()` in `tests/Pest.php`. Nuovi: la pagina incorporata resta incorporata dopo la prenotazione; un fuso non offerto non viene preso; prenotare su un portale sospeso dà 404; annullare con il token di un altro portale dà 404; una lingua scelta resta su tutte le pagine pubbliche.
- Regole: `.ai/rules/portal.md` riscritta per React (stato nell'URL, `PortalPage`, `SlotCalendar`), `.ai/rules/pages.md` aggiornata.

**Decisioni.**
- Il modulo di prenotazione e l'orario scelto restano nel browser; giorno, mese, persona e fuso sono nell'URL e ricaricano la pagina. Gli errori di prenotazione (orario preso, troppi tentativi) tornano sul campo `slot` e riportano alla lista degli orari, già aggiornata dal redirect.
- Chiavi dei campi semplificate: `name`, `email`, `phone`, `notes`, `privacy`, `slot`, `answers.N`; per la lista d'attesa `name`, `email`, `phone`, `from`, `to`, `privacy`.
- Uno spostamento su un orario non più libero ora è un errore di validazione su `slot`, mostrato sopra gli orari (prima un toast rosso).
- Uscire dalla lista d'attesa (`DELETE`) risponde direttamente con la pagina di conferma: la voce non esiste più, non c'è nulla verso cui fare redirect.
- Test tolti: «the language links survive a Livewire update instead of aiming at its endpoint» (esisteva solo per l'endpoint di Livewire). «the language links point at the page on first load» è diventato «a language picked on a public page is kept over the portal one»: il selettore ora legge l'indirizzo dalla barra del browser, lato server resta da provare che la lingua scelta vince su quella del portale.

**Problemi e soluzioni.**
- **Bug corretto**: con `?embed=1` il primo click (da elenco a servizio) perdeva il parametro, e dentro l'iframe del cliente ricomparivano header e footer del portale. Ora ogni link e ogni azione lo porta con sé (`PortalPage::keepEmbed`), con test.
- **Bug corretto**: il messaggio dei troppi tentativi diceva «Riprova fra 1 secondi». Ora `trans_choice`.
- PHPStan: `?->` a sinistra di `??` è superfluo (copiato dalla pagina Livewire), corretto.

**Verifica.** Suite completa (`php -d memory_limit=1G vendor/bin/pest --compact`): **724 test, 722 passati, 2 saltati, 4.078 asserzioni**. PHPStan 0 errori, Pint ok, `tsc` 0 errori, chiavi di traduzione client tutte presenti, build ok. Prova HTTP sul portale demo: `/p/studio-rossi` (5 servizi, anche con `?embed=1`), `/p/studio-rossi/prima-consulenza?d=…` (37 orari dalle 09:00), classe di gruppo con «ultimi 2 posti», pagina di una prenotazione (classe: spostamento non offerto), pagina di uscita dalla lista d'attesa; una prenotazione non valida viene rifiutata dalla validazione senza scrivere nulla. Nessuna prova visiva nel browser.

**Aperto.** Controllo visivo delle pagine pubbliche (calendario, scelta della persona, modulo con domande, lista d'attesa, spostamento, QR, colori del brand, iframe). Prossimo: 9.7 pagine di accesso Fortify. Nessun commit fatto.

### 2026-10-07 — Fase 9.5: pannello admin in React

**Obiettivo.** Migrare tutte le schermate dell'amministratore della piattaforma: elenco e dettaglio dei portali (piano, pagamento, impersonificazione, consegna email), account, piani, documenti legali, coda dei job falliti.

**Fatto.**
- Controller in `app/Http/Controllers/Admin/`: `PortalController` (elenco con il modulo di ogni riga già pronto, crea con il manager, modifica, sospendi/riattiva, elimina, dettaglio), `PaidUntilController`, `ImpersonateManagerController`, `AccountController` (elenco, abilita/disabilita, nuova password, elimina), `PlanController`, `LegalDocumentController`, `QueueController`. Rotte nuove in `routes/admin.php`; la consegna email dell'admin riusa `MailSettingsController` su `admin.portals.mail.*`.
- Form Request `PortalRequest`, `PlanRequest`, `PublishLegalDocumentRequest`. Actions `Tenants/SavePortal`, `Tenants/DeletePortal`, `Plans/SavePlan`, `Users/DeleteAccount` (stessa logica e stesso registro di prima).
- Pagine `admin/portals`, `admin/portal`, `admin/users`, `admin/plans`, `admin/legal`, `admin/queue`. Componente `credentials-dialog.tsx` (password generata mostrata una volta, flash `credentials`), ora usato anche dallo staff; `StatCard` accetta `valueClassName`.
- Eliminati: le sei pagine Livewire admin, `PortalForm`, `PlanForm`, il componente Livewire `smtp-settings`. In `/app` e `/admin` non resta nessuna pagina Livewire: il layout Blade del back-office non lo usa più nessuno.
- Test: `PortalManagementTest`, `PlanManagementTest`, `AccountManagementTest`, `TenantMailerTest` (26 casi), e i casi admin di `FailedJobAlertTest`, `ImpersonationTest`, `LegalDocumentTest`, `ModelBindingTest`, `SubscriptionTest`, `ServicePackageTest` convertiti in HTTP. Helper `portalForm()` in `tests/Pest.php`, `mailForm()` in `TenantMailerTest`. Nuovi: dati del manager chiesti solo alla creazione, props della lista per modulo e avviso di eliminazione, manager senza accesso a creare/eliminare portali o piani, tipo di documento sconosciuto (404), conteggio al singolare nel riepilogo dei job, manager che salva la sua parte senza togliere dal servizio un SMTP già provato.
- Regole: `.ai/rules/controllers.md` (binding implicito e controller condivisi; password con `CredentialsDialog`), `.ai/rules/tests.md` (utente riusato fra le richieste dei test; helper dei moduli).

**Decisioni.**
- Il modulo di ogni portale e di ogni piano arriva già nelle righe della lista (10 portali per pagina, pochi piani): la modifica si apre senza un giro dal server.
- Il reset della password del manager dal dettaglio portale usa la stessa rotta dell'elenco account (`admin.users.password`).
- Eliminare un account con prenotazioni future ora risponde con un errore sul campo `account` (prima un toast rosso); la finestra lo dice già prima e disattiva il pulsante.
- Test tolti perché provavano solo logica ormai nel browser: «the markup never asks the browser for a framework method» (`$wire` non esiste più in React), «a sender on the same domain raises nothing» e «an empty sender address never warns» (il confronto dei domini del mittente gira nel client). Gli altri casi dell'anteprima email ora controllano le props che la pagina usa.

**Problemi e soluzioni.**
- **Bug corretto (latente dalla 9.3)**: `MailSettingsController` non tipizza il `Tenant`, quindi su `/admin/portals/{tenant}/mail` il parametro arrivava come slug e `MailSettingsRequest` cadeva sul portale dell'utente: per l'admin 404. Ora la request risolve lo slug.
- **Bug corretto**: conteggi senza singolare nella coda («1 operazioni fallite nelle ultime 24 ore») e nel riepilogo orario per email (oggetto, testo e «1 errori» per portale). Ora `trans_choice`, con test.
- Un test sulla prova SMTP falliva perché l'utente autenticato dei test è la stessa istanza fra le richieste e tiene in cache il portale: aggiornato il portale tramite il manager. Non succede nel browser.
- `assertInertiaFlash` esiste, `inertiaFlash()` no: la password si legge da `session()->get(SessionKey::FLASH_DATA)`.

**Verifica.** Suite completa (`php -d memory_limit=1G vendor/bin/pest --compact`): **724 test, 722 passati, 2 saltati, 3.581 asserzioni**. PHPStan 0 errori, Pint ok, `tsc` 0 errori, chiavi di traduzione client tutte presenti, build ok. Prova HTTP con l'admin demo: `/admin/portals`, `/admin/portals/studio-rossi` (piano «Studio», stato email «platform», campi server visibili), `/admin/users`, `/admin/plans`, `/admin/legal`, `/admin/queue` (2 job falliti nel database locale), `/admin/activity` tutte con il componente React giusto. Nessuna prova visiva nel browser.

**Aperto.** Controllo visivo del pannello admin (modulo portale con slug automatico, credenziali dopo la creazione, impersonificazione, consegna email dal dettaglio, pubblicazione PDF). Prossimo: 9.6 pagine pubbliche `/p/{slug}`. Nessun commit fatto.

### 2026-10-06 — Fase 9.4: Report, disponibilità e agenda in React

**Obiettivo.** Migrare le tre pagine che manager e operatori aprono ogni giorno: i report, la disponibilità settimanale con eccezioni, blocchi e chiusure, e l'agenda delle prenotazioni con la tabella e le viste mese, settimana e giorno.

**Fatto.**
- Report: `ReportController` (invocabile, periodi `this_month`, `last_month`, `last_90_days`, `this_year`), pagina `app/reports.tsx`.
- Disponibilità: `AvailabilityController` (orario settimanale, eccezioni per data, blocchi orari, chiusure del portale; `?member=` per il manager), `WeeklyScheduleRequest` (fasce ordinate e senza sovrapposizioni, errori sulla fascia esatta), pagina `app/availability.tsx`.
- Agenda: `App\Support\Agenda` (filtri dell'URL ripuliti, tabella, calendario mese/settimana/giorno, griglia oraria della settimana, riga e dettaglio di una prenotazione con i permessi di chi guarda), `BookingController` (elenco, export CSV, conferma, esito, annullamento con motivo, cancellazione dati cliente), `BookingSessionController` (chi tiene una sessione di gruppo). Pagina `app/bookings.tsx`: dettaglio aperto con `?booking=uid` in partial reload, menu azioni, finestre per annullare, cancellare i dati e leggere la nota.
- `useFilters` ha un terzo valore, `apply()`, per cambiare vista o giorno senza aspettare il debounce. `SearchInput` accetta un'etichetta (l'agenda dice «Cliente»).
- Eliminate la pagina Livewire dell'agenda e i partial `agenda-calendar|day|month|week` e `booking-actions`; `booking-phases` resta per la pagina pubblica della prenotazione. Eliminate anche le pagine Livewire di report e disponibilità.
- Test: `BookingAgendaTest` riscritto in HTTP + `assertInertia`; convertiti i casi agenda di `BookingOutcomeTest`, `GroupServiceTest`, `SecurityAuditTest`, `PrivacyTest`, `ReportingTest` (export ora su `app.bookings.export`), `ResourceAllocationTest`, `ServicePackageTest`, `ServiceQuestionTest`, `BookingAutoCompleteTest`, `CancellationNoticeTest`, `CustomerDirectoryTest`, `ModelBindingTest`, `WaitingListTest`; già prima `AvailabilityTest`, `DateOverrideTest`, `TimeBlockTest`, `PortalClosureTest`. Nuovi: esito non valido rifiutato dal server, insegnante di un altro portale non aggiungibile a una sessione (403), filtro operatore ignorato per l'operatore, permessi per riga.
- Regole: `.ai/rules/js.md` (filtri nell'URL e dettagli da query string), `.ai/rules/app.md` (`toBase()->groupBy()` per Larastan).

**Decisioni.**
- La griglia della settimana arriva calcolata dal server (minuti da mezzanotte, corsia e numero di corsie): il client fa solo `top = start − startHour·60`, altezza `max(18, end − start)`. I test controllano i numeri invece delle stringhe CSS.
- I filtri sbagliati nell'URL tornano al default invece di dare errore: vista sconosciuta → tabella, data non valida → oggi (prima la vista giorno dava 422).
- Test sostituiti perché controllavano testi ormai disegnati nel client: «Booking actions» come aria-label → test sui permessi di ogni riga; etichette di chiusura di finestre e notifiche → prop `locale`; ordine delle etichette dei filtri → filtro operatore solo per il manager; tooltip «Open this day» → tolto (la chiave è controllata dallo script delle traduzioni). Il troncamento a tre prenotazioni per cella del mese ora è nel client: il test verifica che il giorno le abbia tutte.
- L'avviso di impersonificazione e la lingua del profilo si provano sulle props Inertia: in `/app` non resta più nessuna pagina Livewire.

**Problemi e soluzioni.**
- **Bug corretto**: le chiusure del portale di un solo giorno dicevano «1 giorni». Ora `trans_choice('one day|:count days')`.
- Il periodo dei report non aveva un'etichetta: aggiunta «Periodo».
- `match (...) { ... }->format()` non è PHP valido: va tra parentesi. Uscito al primo test, non in produzione.
- PHPStan: `->get()->groupBy()` dà una collezione Eloquent di collezioni Eloquent, tipo non valido per Larastan → `toBase()`; in `WeeklyScheduleRequest` `collect()` su dati validati non tipizzati → `usort` sui minuti.
- La suite completa a 128 MB esaurisce la memoria: va lanciata con `php -d memory_limit=1G vendor/bin/pest --compact`, come già scritto nei comandi di verifica.

**Verifica.** Suite completa: **723 test, 721 passati, 2 saltati, 3.366 asserzioni**. PHPStan 0 errori, Pint ok, `tsc` 0 errori, chiavi di traduzione client tutte presenti, build ok. Prova HTTP con il manager demo: `/app/bookings` 200 in tabella (6 prossime), mese (35 giorni), settimana (griglia 8–19), giorno, passate (15); dettaglio in partial reload (solo `details`); export CSV con intestazioni italiane e 6 righe; `/app/reports` e `/app/availability` 200. Nessuna prova visiva nel browser.

**Aperto.** Controllo visivo di agenda (griglia settimana, menu azioni, finestre, sessioni di gruppo), disponibilità e report. Prossimo: 9.5 pannello admin. Nessun commit fatto.

### 2026-10-06 — Fase 9.3: Servizi e Impostazioni portale in React

**Obiettivo.** Migrare le due pagine più dense del manager: il modulo servizi (pacchetti, requisiti risorse, domande, ordinamento) e le impostazioni del portale (dati, privacy, logo e PDF, consegna email, piano, documenti della piattaforma, esportazione).

**Fatto.**
- Servizi: `ServiceController` (elenco, `?edit=` per aprire il modulo, crea, modifica, pubblica/nascondi, sposta, elimina), `ServiceRequest`, `App\Support\ServiceFormData` (forma del modulo, usata da controller e test), Actions `Services/SaveService` e `Services/DeleteService` con la logica di `ServiceForm` sotto `AgendaLock`. Pagina `app/services.tsx` con trascinamento delle card, foglio laterale del modulo, guida `service-help.tsx`.
- Impostazioni portale: `PortalSettingsController` (props, salvataggio, accettazione documenti), `PortalFileController` (logo, privacy e termini in PDF), `MailSettingsController` + `MailSettingsRequest` + `Actions/Tenants/SaveMailSettings` + `Support/MailSettingsData`. Pagina `app/portal.tsx` e componente `mail-settings.tsx`, già pronto per l'admin (9.5).
- Il componente Livewire `smtp-settings` (resta per il dettaglio portale dell'admin fino a 9.5) ora usa le stesse regole e la stessa Action: niente logica doppia.
- Eliminati `ServiceForm`, la pagina Livewire dei servizi e del portale, `partials/service-help`. `PortalForm` resta: lo usa l'elenco portali dell'admin.
- Test: tutti i casi Livewire di servizi, pacchetti, requisiti, domande, colore, preavviso, gruppi, limiti piano, logo, privacy, documenti legali convertiti in HTTP; helper globale `serviceForm()` in `tests/Pest.php`. Nuovi: slug condiviso fra portali, servizio di altro portale (404 su modifica/pubblica/elimina/sposta), domanda riformulata che mantiene le risposte collegate, troppe domande rifiutate dal server, operatore che non può caricare file del portale, tre test sugli endpoint email del manager.
- Regole: `.ai/rules/routes.md` ({ownService}), `.ai/rules/tests.md` (serviceForm), `app.md` aggiornato (limiti in SaveService/SaveOperator, non più nei Form Livewire).

**Decisioni.**
- Errori di dominio con chiavi semplici: `name`, `capacity`, `is_package`, `operator_ids`, `package_items.N.service_id`, `service` (eliminazione).
- I test che cercavano solo testi di interfaccia nell'HTML Livewire (menu «Service actions», guida dei servizi, testo del riquadro risorse) sono stati tolti: in React quei testi stanno nel client. I test che usavano `/app/services` come pagina HTML di comodo (lingua, avvisi, ponte) ora controllano `<html lang>`, la prop `notices` o la pagina 403, che resterà Blade.
- Il ponte Inertia→non-Inertia si prova sulla pagina 403 invece che su una pagina Livewire: deve restare valido anche quando Livewire sparirà.

**Problemi e soluzioni.**
- **Bug latente trovato e corretto**: lo slug dei servizi è unico per portale, ma il binding implicito (anche nella vecchia pagina Livewire) cercava lo slug in tutta la tabella. Con due portali che vendono «consulenza», il manager del secondo apriva il servizio del primo e prendeva 403. Ora `{ownService}` cerca solo nel portale di chi è collegato. Test: «two portals may share a service slug».
- Rischio evitato: `validated()` scarta le chiavi senza regola, quindi senza `questions.*.id` ogni salvataggio avrebbe ricreato le domande e sganciato le risposte già date. Regola aggiunta e test dedicato.
- Un test JSON con lettere accentate («verrà») falliva su `assertSee`: nelle props Inertia gli accenti sono `\u00e0`. Convertito a `assertInertia` sulla prop `notices`.
- `git rm` rifiutato sulle pagine con una mia modifica locale (`wire:navigate` tolto): forzato, erano file da eliminare.
- Prova HTTP: `/tmp` di bash e di PHP non coincidono su Windows; usata la cartella scratchpad.

**Verifica.** Suite completa: **719 test, 717 passati, 2 saltati**. PHPStan 0 errori, Pint ok, `tsc` 0 errori, build ok. Prova HTTP con il manager demo: `/app/services` 200 (componente `app/services`), apertura di «Prima consulenza» con `?edit=` (3 operatori, 3 domande), risalvataggio identico → 303 e toast «Servizio aggiornato.»; `/app/portal` 200. Nessuna prova visiva nel browser.

**Aperto.** Controllo visivo di Servizi (trascinamento, pacchetto, requisiti, domande) e Impostazioni portale (upload, email, documenti). Prossimo: 9.4 Report, disponibilità, agenda prenotazioni. Nessun commit fatto.
### 2026-10-05 — Fase 9.1 e 9.2: impostazioni account e pagine semplici del manager

**Obiettivo.** Proseguire la migrazione: impostazioni dell'account, poi lista d'attesa, registro, clienti, risorse e staff.

**Fatto.**
- 9.1: controller `Settings/{Profile,ProfilePhoto,Password,Security}Controller` + Form Request; pagine `settings/{profile,security,appearance}` in React. 2FA e passkey parlano con le rotte di Fortify/Passkeys. Toast via flash di Inertia (`App\Support\Toast` + `sonner`).
- 9.2: `WaitingListController`, `ActivityController` (admin e manager), `CustomerController` (scheda con `?customer=`, nota, export CSV), `ResourceController` + `ResourceBlockController`, `StaffController` + `StaffPhotoController`. Credenziali del nuovo operatore via flash (`credentials`), mai nella cronologia.
- Logica dei Form Livewire spostata in Actions: `Actions/Resources/{SaveResource,DeleteResource,BlockResource}`, `Actions/Staff/{SaveOperator,DeleteOperator}`, `Actions/Users/ReplaceAvatar` (riusata da profilo e staff). Eliminati `ResourceForm`, `ResourceBlockForm`, `StaffForm` e le pagine Livewire migrate.
- Componenti React riusabili: `pagination`, `search-input`, `empty-state`, `form-field`, `password-input`, `color-badge`, `toaster`, hook `use-filters` (filtri in query string con debounce) e `use-appearance`. `Controller::filter()`/`dateFilter()` per leggere filtri sporchi senza errori.
- Tolto ogni `wire:navigate` dal guscio Livewire (sidebar e header). Nuove chiavi in `lang/it.json`.
- Regole nuove: `.ai/rules/controllers.md`, voci in `tests.md` e `app.md` (Pint e `@var Resource`).

**Decisioni.**
- Test di sole etichette Flux (aria-label, data-flux-label) sostituiti con test sulle props (ricerca, locale): in React quei testi stanno nel client e il server non li vede.
- Gli errori di dominio diventano errori di validazione con chiave semplice (`is_active`, `starts_at`, `resource`, `member`, `name`) al posto di `form.*`.
- `app.md` cita ancora `StaffForm::store`: il limite operatori ora vive in `Actions/Staff/SaveOperator` (errore su `name`).

**Problemi e soluzioni.**
- Uno script `php -r` con `preg_replace` fallito ha svuotato `WaitingListTest.php` e `SecurityAuditTest.php` → ripristinati con `git checkout` (nessuna modifica precedente persa) e rifatti con l'Edit tool.
- Pint ha riscritto `@var Resource` in `@var resource` → `instanceof Resource`, regola registrata.
- La CLI shadcn reinstalla il pacchetto `cn` a ogni `add` → rimosso ogni volta.

**Verifica.** Suite completa: **713 test, 710 passati + 1 corretto subito dopo (StaffManagementTest, 9/9 verdi), 2 saltati**. PHPStan 0 errori, Pint ok, `tsc` 0 errori, build ok. Prova HTTP delle impostazioni: pagine 200, salvataggio profilo 303 con toast «Profilo aggiornato.». Nessuna verifica visiva nel browser.

**Aperto.** Controllo visivo di impostazioni, lista d'attesa, registro, clienti, risorse, staff. Prossimo: 9.3 (Servizi e Impostazioni portale). Nessun commit fatto.

### 2026-10-05 — Fase 9.0: infrastruttura React e dashboard

**Obiettivo.** Avviare la migrazione a Inertia + React: base tecnica, guscio del back-office e una prima pagina vera.

**Fatto.**
- Pacchetti: `inertiajs/inertia-laravel` 3.5, `laravel/wayfinder`; npm `@inertiajs/react`/`vite` 3.8, React 19, TypeScript 7, `@vitejs/plugin-react`, plugin Vite di Wayfinder, base shadcn/ui (`radix-ui`, `lucide-react`, `clsx`, `tailwind-merge`, `class-variance-authority`, `tw-animate-css`). SSR spento in `config/inertia.php`.
- Server: `HandleInertiaRequests` (props condivise `name`, `locale`, `auth.user`, `notices`), vista radice `resources/views/app.blade.php` (stesso tema chiaro/scuro di Flux, chiave `flux.appearance`), `DashboardController` al posto di `pages::dashboard`.
- **Ponte** `VisitNonInertiaPagesInFull`: una visita Inertia che riceve una pagina HTML non Inertia (Livewire, errore, login) diventa 409 + `X-Inertia-Location`, quindi ricarica completa. Così il guscio React usa `<Link>` ovunque, anche verso pagine non migrate.
- `App\Support\LayoutNotices`: gli avvisi sopra la pagina (impersonificazione, documenti legali, scadenza, soglia prenotazioni) calcolati una volta per i due gusci. I tre componenti Blade sostituiti da `<x-layout-notices>`.
- Client: `resources/js/app.tsx`, `lib/i18n.ts` (`__()` e `trans_choice()` su `lang/it.json`, porting del MessageSelector di Laravel), `layouts/app-layout.tsx`, sidebar per ruolo con cambio lingua e menu utente, `pages/dashboard.tsx` per admin, manager e operatore. Componenti shadcn: button, card, badge, dropdown-menu, sidebar, avatar, input, separator, tooltip, sheet, skeleton, table.
- Tolto `wire:navigate` dai link Livewire verso la dashboard (sidebar, header, welcome) e `Livewire.navigate` dopo l'accesso con passkey.
- Quattro chiavi nuove in `lang/it.json` (Copied, Main navigation, Menu, Show or hide the menu). Script `npm run types`. Cartelle generate da Wayfinder in `.gitignore`.
- Regole nuove: `.ai/rules/js.md`, `.ai/rules/ui.md`, una voce in `.ai/rules/views.md`.

**Decisioni.**
- Scelte dell'utente: shadcn/ui (scartati Headless UI e componenti a mano), Wayfinder (scartato Ziggy), partenza da guscio + dashboard (scartate pagine pubbliche e pagina di prova).
- Pagine in `resources/js/pages` minuscolo, come lo starter kit React di Laravel.
- Traduzioni nel bundle client dallo stesso `lang/it.json`: un solo file da mantenere. Date e orari formattati dal server nel fuso di chi guarda, come prima.
- Le props della dashboard: una sola chiave per ruolo (`admin`, `manager`, `operator`), e solo i dati di quel ruolo vengono calcolati.
- Il cambio lingua resta una ricarica con `?lang=`, identica sulle due metà dell'app.

**Problemi e soluzioni.**
- La CLI di shadcn ha scritto `import { cn } from "cn"` in tutti i componenti e installato il pacchetto npm `cn` → non risolve l'alias `@/lib/utils` con un `tsconfig` senza `baseUrl` (rimosso in TypeScript 7) → disinstallato `cn`, import corretti a mano; registrato in `.ai/rules/ui.md`.
- PHPStan: `__()` con sostituzioni assegnato a una variabile, poi messo in un ternario, perde il tipo «benevolo» e risulta `array|string` → estratto in un metodo che restituisce direttamente `?string`. `list<…>` nei PHPDoc → `array<int, …>` come nel resto del progetto.
- Nel portare l'avviso di impersonificazione avevo scritto `actor()->timezone` senza tenere conto del `null`; PHPStan conferma che `->` va bene a sinistra di `??`.

**Verifica.**
- `php -d memory_limit=1G vendor/bin/pest --compact`: **709 test, 707 passati, 2 saltati, 2.199 asserzioni** (prima 701). Nuovi: 4 in `DashboardTest` (admin, manager, operatore, props del guscio con impersonificazione) e 4 in `InertiaBridgeTest` (verso Livewire → 409, pagina React → oggetto pagina, redirect e JSON intatti).
- Pint passato; PHPStan livello 7 con 1G: 0 errori; `npm run types` (tsc 7.0.2): 0 errori; `npm run build` riuscito, avviso Vite sul pezzo da 600 kB.
- Prova HTTP con `php artisan serve` sul database locale: login manager → `/dashboard` 200 con componente `dashboard` e i numeri del portale demo (5 servizi, 3 prenotabili, 6 in arrivo); visita Inertia a `/app/services` → 409 con la stessa URL; `/app/services` e `/app/bookings` Livewire ancora 200.
- **Non verificato nel browser**: in questa sessione non c'era un browser pilotabile. Aspetto, menu, cambio lingua, tema scuro e telefono vanno guardati a mano.

**Aperto.** Controllo visivo della dashboard React con i tre ruoli; poi 9.1 (impostazioni account) con le notifiche a comparsa. Restano dalla sessione precedente: repo GitHub e remote senza token. Nessun commit fatto. Vedi [Fase 9](#fase-9--migrazione-dellinterfaccia-a-inertia--react-avviata-il-2026-10-05).
### 2026-10-05 — Copia del progetto per il passaggio a Inertia + React

**Obiettivo.** Rifare l'interfaccia in React mantenendo Laravel, continuando lo sviluppo solo sulla copia.

**Fatto.**
- Clonato `C:\ARCHIVIO\AI\digithub-connect` in `C:\ARCHIVIO\AI\digithub-connect-react` con tutta la storia (`git clone --no-local`), remote `origin` rimosso: il nuovo repo GitHub è da creare.
- Copiati i file fuori da git: `.env`, database (`VACUUM INTO`, non copia a caldo), `storage/app/public/{legal,logos}`, database di collaudo, `vendor`, `node_modules`, cartelle di Boost. Rifatti `storage:link` e `npm run build`.

**Decisioni.**
- La copia sostituisce l'originale: l'originale resta fermo al commit `f2404ab` come riferimento, nessuno sviluppo parallelo.
- Inertia + React in **TypeScript**, nello stesso repository (niente API separata).
- Passaggio **graduale**: Livewire e Inertia convivono, una pagina alla volta, test verdi a ogni passo; Livewire e Flux si rimuovono alla fine. Scartato il «tutto insieme»: app inutilizzabile fino all'ultima pagina.
- Il remote del vecchio progetto conteneva un token GitHub in chiaro nell'URL: da revocare, e nella copia il remote va impostato senza token.

**Problemi e soluzioni.** Primo giro di test sulla copia: 70 falliti con `ViteManifestNotFoundException` → `public/build` è ignorato da git e non era stato copiato → `npm run build`.

**Verifica.** `php -d memory_limit=1G vendor/bin/pest --compact`: **701 totali, 699 passati, 2 saltati, 2.111 asserzioni**, identico all'originale.

**Aperto.** Creare il repo GitHub e impostare il remote; riaprire la sessione di Claude Code nella nuova cartella (Boost e memoria puntano alla cartella in cui gira la sessione); piano di migrazione pagina per pagina.

### 2026-10-04 — Fase 8: il prodotto completo prima del server

**Obiettivo.** Completare il progetto prima del caricamento su server (VPS Windows Server, rimandato). Scelte dell'utente: limiti dei piani e vista settimanale sì; incasso manuale; soglia mensile solo con avvisi; 7 giorni di tolleranza. Fuori: calendari, videochiamate, pagamenti, SMS.

**Fatto.**
- 8.1 Backlog ripulito: 16 voci già realizzate spuntate.
- 8.2 Cast `encrypted` su `bookings.customer_notes`, `customers.notes` e `booking_answers.value`, con migrazione che cifra le righe esistenti (idempotente, reversibile).
- 8.3 Pulsanti Outlook.com e Microsoft 365.
- 8.4 `Actions\Tenants\ExportPortal`: ZIP con un JSON per tabella e i file caricati, dal manager in Impostazioni portale (`app.portal.export`, throttle 6/min), registrato nel registro.
- 8.5 Documenti legali: pagina admin `admin.legal` (versioni PDF, tabella delle accettazioni), sezione «Documenti della piattaforma» e avviso per il manager, `legal_documents` e `legal_acceptances` (nome, IP e data copiati).
- 8.6 `Services\PlanUsage` e blocchi su servizi e operatori. Avvisi soglia all'80% e al 100%. SMTP proprio solo se il piano lo include. «Pagato fino al» in dettaglio portale (`Actions\Tenants\SetPaidUntil`). Comando giornaliero `portals:check-subscriptions` (06:00) con email `SubscriptionNoticeMail`. Riquadro «Il tuo piano» e avvisi per il manager.
- 8.7 Vista settimanale a fasce orarie con `Support\TimeGrid` (corsie per le sovrapposizioni), una lezione di gruppo in un solo blocco, linea dell'ora attuale, elenco su telefono.

**Decisioni.**
- L'esportazione è solo del manager: l'admin passa dall'impersonificazione, coerente con la decisione del 2026-08-27.
- L'admin che impersona **non** può accettare il DPA al posto del portale.
- Il manager non conta come operatore nei limiti.
- Le prenotazioni annullate contano nella soglia mensile.
- `paid_until` non è assegnabile in massa: si scrive solo con l'azione.
- La riattivazione automatica vale solo per i portali sospesi per mancato pagamento.

Regole registrate con Boost: models (testo cifrato), database (ogni tabella del portale nell'esportazione), app (limiti e scadenza).

**Problemi e soluzioni.**
- Il vecchio test «i limiti non bloccano nulla» era messo apposta come segnale per questo giorno: l'ho invertito.
- Blade non regge `@php(...)` in linea insieme a blocchi `@php` nello stesso file: passato alla forma a blocco.
- Con `array_map` su due array le chiavi andavano perse: il test unitario l'ha colto subito.
- Un `sed` con barre rovesciate su file con fine riga CRLF non applicava le modifiche: usati Edit o script PHP.

**Verifica.**
- Suite `php -d memory_limit=1G vendor/bin/pest --compact`: **701 totali, 699 passati, 2 saltati, 2.111 asserzioni**. Pint pulito, PHPStan **0 errori**, `npm run build` riuscito.
- Migrazioni applicate al database locale dopo copia con `VACUUM INTO` nello scratchpad. Note locali lette correttamente dopo la cifratura.
- Prova HTTP reale con `php artisan serve` e account di seed: pagine admin e manager a 200 senza errori, testi in italiano, ZIP scaricato valido (26 file), settimana del 13 ottobre con la lezione di gruppo in un blocco.
- **Non** fatto un controllo visivo nel browser: nessuno strumento browser in sessione.

**Aperto.**
- Controllo visivo di: vista settimana (anche tema scuro), pagina Documenti legali, riquadri piano e avvisi.
- I database di collaudo (`collaudo-*.sqlite`) vanno migrati prima di riaprirli, altrimenti le note in chiaro danno errore di decifratura.
- Testi legali veri dal legale.
- La prova HTTP ha lasciato una voce «Dati del portale esportati» nel registro locale.

### 2026-10-04 — SQLite resta anche in produzione

**Obiettivo.** Decidere se tenere SQLite in produzione invece di passare a MySQL/Postgres, e provarne la tenuta sotto concorrenza.

**Fatto.** `journal_mode` a `wal` in `config/database.php`, da affiancare a `IMMEDIATE` e `busy_timeout` già presenti. `SESSION_DRIVER` e `CACHE_STORE` a `file` in `.env.example` e nell'`.env` locale. Nuovo `tests/Feature/BookingConcurrencyTest.php`, con processi PHP paralleli veri su file SQLite temporaneo: tre richieste per lo stesso operatore e slot producono una sola prenotazione; tre richieste senza operatore scelto, con due operatori liberi, producono una prenotazione per ciascuno e un rifiuto; il file gira in WAL. Riscritta la sezione Database di "Prima del deploy", aggiornati Backlog, Stack e `COLLAUDO.md` (R03, S06, § 5).

**Decisioni.** Resta SQLite: le scritture sono serializzate da `IMMEDIATE`, più rigido dei lock di riga. Condizioni: un solo server con disco persistente e backup con `VACUUM INTO` o Litestream, mai copia a caldo. Regola registrata con Boost in `.ai/rules/config.md`. `synchronous` lasciato al default (FULL) per non perdere prenotazioni confermate in caso di blackout.

**Problemi e soluzioni.** Il diario sosteneva che SQLite rendesse inutile il lock contro le doppie prenotazioni: era vero per `lockForUpdate()`, ma da Fase 7.2 la protezione viene da `IMMEDIATE`. Prova al contrario: rimesso temporaneamente `DEFERRED`, i nuovi test falliscono 3 volte su 3 con `database is locked`, che per il cliente sarebbe un errore 500 invece di "slot occupato". Ripristinato `IMMEDIATE`.

**Verifica.** Nuovi test e `ResourceAllocationTest` ripetuti 5 volte: 40/40 ogni volta. Suite completa `php -d memory_limit=1G vendor/bin/pest --compact`: **639 totali, 637 passati, 2 saltati, 1.902 asserzioni, 47,7 s**. Pint riuscito, PHPStan **0 errori**. Database locale ora in `wal`, sessioni e cache su file (le sessioni aperte nel browser vanno rifatte).

**Aperto.** Backup automatico da scrivere al deploy. Carico simultaneo su un server di staging vero. Le tabelle `sessions` e `cache` restano nello schema, inutilizzate.

### 2026-10-04 — Commit del lavoro di Fase 7 e collaudo

**Obiettivo.** Salvare in git il lavoro rimasto fuori dal repository dal 2026-10-01.

**Fatto.** Commit `6e6b22e` su `master`: 130 file, Fase 7 (risorse, allocazioni, pacchetti), correzioni B01–B13, `COLLAUDO.md` con screenshot in `scratchpad/`, regole `.ai/rules`, config SQLite (`busy_timeout` 5000, transazioni `IMMEDIATE`).

**Decisioni.** Commit unico, come da storia del repository. Screenshot inclusi perché citati dal rapporto; caselle SMTP finte restano escluse da `.gitignore`. Nel rapporto solo credenziali di seed fittizie.

**Problemi e soluzioni.** Nessuno; solo avvisi di normalizzazione CRLF→LF.

**Verifica.** `git status` pulito dopo il commit. Suite non rieseguita in questa sessione; ultima esecuzione il 2026-10-02 (636 test).

**Aperto.** Nessun push. Restano le prove umane/infrastrutturali di `COLLAUDO.md`, la Fase 4 e l'applicazione dei piani.

### 2026-10-02 — Comandi per avvio e prove in autonomia

**Obiettivo.** Fornire i comandi essenziali per avviare il progetto locale e ripetere la suite automatica.

**Fatto.** Verificato `composer run dev` in `composer.json`: avvia server, coda, scheduler e Vite. Corretto il conteggio dei processi nella sezione di avvio del diario. Indicati avvio, indirizzo locale e arresto con Ctrl+C.

**Decisioni.** Avvio ordinario con la configurazione locale esistente; suite separata con `php -d memory_limit=1G vendor/bin/pest --compact`. Nessuna reinstallazione o inizializzazione del database.

**Problemi e soluzioni.** Nessun problema riscontrato.

**Verifica.** Lettura degli script del progetto e risoluzione dell'indirizzo tramite Boost. Nessun nuovo avvio o test eseguito in questa sessione informativa.

**Aperto.** Restano le prove manuali già descritte in `COLLAUDO.md`.

### 2026-10-02 — Ripresa del collaudo: risorse alternative, lista d'attesa e iframe

**Obiettivo.** Proseguire personalmente le prove browser e correggere altri difetti, aggiornando il rapporto e gli interventi residui.

**Fatto.** Chiusi M05/M06/O04/C10/R01/R02/S03/M11: apertura straordinaria creata/rimossa, annullamento manager motivato fino all'email, persistenza profilo dopo logout/login, ricerche letterali, strumento e cabine alternative simultanei. Luca prenota il 6 ottobre alle 11 con seconda cabina/strumento mentre la prima è occupata. Snippet su pagina ospite locale di diversa origine: prenotazione reale interamente nell'iframe, conferma/agenda/email coerenti. Fixture HTML in `scratchpad/collaudo-embed`, server temporaneo fermato. Screenshot e istruzioni aggiornati in `COLLAUDO.md`.

**Decisioni.** Dati fittizi nei soli database separati. Vecchi duplicati della lista d'attesa conservati con i token, invii raggruppati senza bonifica distruttiva. Regole durevoli registrate con Boost in `.ai/rules/flux.md` e `.ai/rules/portal.md`.

**Problemi e soluzioni.** B05: singolare gruppo, titolo Sicurezza e nomi dei comandi corretti; errore ultimo operatore inline. B11: Blaze fold/memo congelava le etichette nella prima lingua compilata, risolto con cinque override locali e regressioni EN→IT, senza modificare vendor/dipendenze. B12: accesso Dettagli aggiunto al menu della tabella anche senza note. B13: maiuscole email creavano iscrizioni doppie; normalizzazione, riuso dei record precedenti e avviso unico con silenzio condiviso. Dopo fix browser mostra una sola nuova iscrizione; email locali confermano un avviso per indirizzo anche per i duplicati precedenti. Stato Inattivo tradotto.

**Verifica.** Regressioni prima riproducono i difetti, poi passano; corrette anche due aspettative iniziali dei test (guardia del penultimo operatore e whitespace titolo). Lista d'attesa: 17 test, 50 asserzioni. Suite finale `php -d memory_limit=1G vendor/bin/pest --compact`: **636 totali, 634 passati, 2 saltati, 1.891 asserzioni, 38,67 s**; 19 casi aggiunti. Pint `--dirty --format agent` riuscito, PHPStan 1 GB **0 errori**, build riuscita con avviso font opzionale preesistente. Nessun nuovo errore console manager; Escape chiude modale e ripristina focus, copia link IT→EN→IT corretta.

**Aperto.** Matrice: 36 PASSATO, 31 PARZIALE, 5 UTENTE. Restano varianti UI e prove umane/infrastrutturali nei §§ 1–6 di `COLLAUDO.md`: credenziali SMTP/password, 2FA/passkey, QR/calendari/telefono, provider/DNS, staging/concorrenza/carico e lettore di schermo. Suite completata il 2026-10-01, chiusura rapporto il 2026-10-02. Nessun commit o deploy; schede utili conservate, temporanee chiuse.

### 2026-10-01 — Collaudo complessivo nel browser e correzioni

**Obiettivo.** Collaudare personalmente manager, operatore e cliente, tentando anche input ostili e aggiramenti; documentare esiti e passaggi umani necessari.

**Fatto.** `COLLAUDO.md`: 72 casi con esiti e limiti, registro B01–B10, evidenze e istruzioni operative. Database dedicato `collaudo-20261001.sqlite` su 8010 e copia storica autorizzata `collaudo-storico-20261001.sqlite` su 8011; SMTP locali senza invii esterni. Percorsi reali di prenotazione/approvazione/spostamento/annullamento, risorse/gruppi/pacchetti, quattro tipi di domande, report/export, ruoli/impersonificazione/sospensioni. Creato e usato Operatore Collaudo; eliminati portale esterno/manager fittizi e anonimizzata cliente fittizia con approvazioni. Storico: esiti passati e conservazione verificati nel browser; comandi completi/idempotenti per completamento, reminder e anonimizzazione.

**Decisioni.** Dati originali conservati; credenziali nuove lasciate all'utente secondo policy browser. L'utente chiede di lasciare le istruzioni SMTP nel rapporto. Parti automatiche e browser dichiarate separatamente; moduli obsoleti non presentati come benchmark simultaneo. Nessuna attestazione della produzione assente. Regole durevoli su mail serializzate/reset registrate tramite Boost in `.ai/rules/mail.md` e `.ai/rules/app.md`.

**Problemi e soluzioni.** Corrette traduzioni/errori duplicati; pending esportato come confermato ora senza calendari/ICS; tooltip mese generava errore JavaScript, ora tradotto lato server; collisione variabile `slot` nelle email lista d'attesa causava job falliti, ora `openingAt` compatibile con serializzazione e invio riprovato realmente; reset distingueva account noto/ignoto, risposte web/JSON uniformate; quattro tipi di domande duplicavano errori, ora singoli; durata pacchetto admin obsoleta, ora aggiornata. Tre advisory dipendenze risolti con i soli aggiornamenti autorizzati Laravel 13.30 e CommonMark 2.10.3. Simulatori SMTP a connessione singola inizialmente in timeout: riprova riuscita, limite del simulatore documentato.

**Verifica.** Suite finale con `php -d memory_limit=1G vendor/bin/pest --compact`: **617 totali, 615 passati, 2 saltati, 1.839 assert, 37,89 s**; 24 casi aggiunti. Primo tentativo a 128 MB esauriva memoria; ripetizione 1 GB riuscita. Pint `--dirty --format agent` riuscito; PHPStan 1 GB **0 errori**; build riuscita con avviso font opzionale preesistente; Composer/npm audit **0 vulnerabilità segnalate**. Casi mirati ripetuti dopo ogni correzione. Storico: completamento 2 poi 0, anonimizzazione 7 poi 0, promemoria 1 poi 0 con .eml verificato; potature senza fixture idonee, casi positivi solo automatici. Override mobile rimossi e schede utili lasciate aperte.

**Aperto.** B05 e casi PARZIALE/UTENTE in `COLLAUDO.md`: SMTP proprio UI, password/token/2FA/passkey, QR/import calendari/telefono, provider/DNS e produzione/carico. Prossima ripresa da quelle istruzioni; Backlog aggiornato. Nessun commit né pubblicazione eseguiti.

### 2026-10-01 — Fase 7.3: pacchetti con pause e cambio cabina

**Obiettivo.** Completare i pacchetti prestabiliti dopo l'occupazione automatica delle risorse, rispettando le due scelte confermate dall'utente.

**Fatto.** Modulo manager per composizione, riordino, pause e prezzi; `ServicePackageItem`, `BookingPhase` e migrazione applicata senza azzerare i dati. `PackagePlan` calcola il programma e i vincoli; `ResourceAvailability` cerca assegnazioni compatibili fra fasi. Percorso pubblico, domande aggregate, conferma unica, riepilogo nelle email e nell'agenda, annullamento e spostamento dell'intero appuntamento. Protezioni su eliminazione e conversione dei componenti. Traduzioni italiane aggiornate e ordinate.

**Decisioni.** Pause configurabili; cabine diverse ammesse fra le fasi. Un solo operatore rimane impegnato nell'intero appuntamento. Spostare conserva ordine, durata, pause, nomi e prezzo concordati, ricontrollando le risorse al nuovo orario. Regole condivise registrate tramite Boost.

**Problemi e soluzioni.** La validazione del filtro booleano nei servizi selezionabili richiedeva il valore numerico `0`. La cancellazione individuale liberava già la disponibilità attraverso lo stato ma ora marca esplicitamente le allocazioni come rilasciate. Risolta un'importazione duplicata; correzioni verificate dai test. Lo spostamento verso un conflitto conserva tutte le fasi e allocazioni originali.

**Verifica.** `ServicePackageTest.php`: 31 casi, 126 assert; verificato anche lo storico delle risorse nell'agenda dopo l'annullamento. Suite completa `php artisan test --compact`: **593 test, 591 passati, 2 saltati, 1739 assert**, circa 26 secondi. Pint applicato; PHPStan con 1G: **0 errori**. `npm run build`: riuscito (avviso già noto su Fontaine opzionale). Migrazione locale riuscita. Nel browser controllati apertura del modulo, attivazione pacchetto, selettori delle fasi, pausa, riordino e modalità prezzi in italiano; prova chiusa senza salvare dati. Nessun errore recente nella console del tab.

**Aperto.** Collaudo complessivo della Fase 7 e ripresa del precedente Giro 5, Seduta A, passo 4; resta il Giro 6. Modifiche delle Fasi 7.1–7.3 non committate. Server locale attivo.

### 2026-10-01 — Fase 7.2: occupazione automatica delle risorse

**Obiettivo.** Richiedere persone e risorse libere per prenotare, anche con operatori diversi, e gestire atomicamente assegnazioni, spostamenti e annullamenti.

**Fatto.** `ResourceAvailability` integra `SlotFinder` con requisiti e alternative, manutenzione e occupazioni precaricate per intervallo. Nuovo modello `ResourceAllocation` e migrazione additiva con snapshot di nomi, requisiti, tempi e finestre UTC. Le sessioni di gruppo hanno una sola occupazione condivisa; ogni partecipante mantiene i riferimenti agli snapshot originali. Ultimo annullamento e riapertura sono gestiti; l'agenda mostra le assegnazioni. I form risorse impediscono manutenzione, disattivazione e rimozione in conflitto. Aggiornati messaggi IT/EN e aggiunti 37 casi in `ResourceAllocationTest.php`; adeguati due test della 7.1 al comportamento nuovo.

**Decisioni.** L'occupazione della risorsa è appuntamento più preparazione/riassetto propri; i buffer del servizio restano dell'operatore. `AgendaLock` acquisisce il portale prima di operatori e sessioni, serializzando le scritture dello stesso portale e preservando l'indipendenza fra portali. Su SQLite usati transazioni IMMEDIATE e attesa di 5 secondi. Conferme e annullamenti rileggono lo stato sotto lock. Nessuna assegnazione retroattiva alle prenotazioni esistenti. Regole durevoli registrate con Boost in actions, bookings, models e services.

**Problemi e soluzioni.** Un test attendeva il messaggio di eliminazione precedente: aggiornato. Il primo nuovo test usava `Mail::queued()` senza classe: sostituito con l'asserzione disponibile `assertQueuedCount`. PHPStan segnalava scope nei builder generici, ritorno ambiguo di `findOrFail` e id risorsa nullable: corrette query e gestione del null, senza soppressioni. Individuata e protetta la conferma di una prenotazione annullata basata su un modello obsoleto.

**Verifica.** 168 test mirati passati, 540 asserzioni. Suite completa: 562 test, 560 passati, 2 saltati, 1613 asserzioni. Due prove con processi PHP simultanei su database SQLite temporaneo: una sola assegnazione sia con operatori diversi sia fra due servizi di gruppo. Errori di salvataggio simulati dimostrano il rollback di creazione, sessione e spostamento. Pint passato, PHPStan 0 errori, build riuscita (avviso opzionale del font già presente). Migrazione locale applicata senza reset. Browser: pagina Risorse, modulo risorsa e requisiti nei Servizi visualizzati correttamente in italiano. I log browser restituiti da Boost sono vecchi, quindi non sono usati come prova. Nessun collaudo su MySQL/PostgreSQL o telefono fisico.

**Aperto.** Fase 7.3, pacchetti prestabiliti, poi collaudo complessivo. Il precedente Giro 5 riparte dal passo 4 della Seduta A. Modifiche 7.1 e 7.2 non committate. Server locale riavviato per la verifica.

### 2026-10-01 — Riepilogo del punto di ripartenza

**Obiettivo.** Ricostruire dove si era fermato il lavoro su richiesta dell'utente.

**Fatto.** Letti stato, Backlog e ultime voci del Diario; verificato lo stato Git. Confermata la Fase 7.1 completata, con modifiche ancora non committate.

**Decisioni.** Nessun nuovo sviluppo avviato; resta la sequenza concordata: 7.2, poi 7.3, quindi collaudo complessivo.

**Problemi e soluzioni.** Il riepilogo iniziale indica ancora il passo 3 del Giro 5; le ultime voci precisano che i controlli 1–3 sono conclusi e si riparte dal passo 4 della Seduta A.

**Verifica.** Consultazione del diario e stato Git; nessun nuovo test eseguito. Ultima suite registrata: 523 test passati e 2 saltati.

**Aperto.** Occupazione automatica delle risorse (7.2), pacchetti prestabiliti (7.3); collaudo manuale sospeso al Giro 5, Seduta A, passo 4.

### 2026-10-01 — Applicazione arrestata e sviluppo in pausa

**Obiettivo.** Fermare l'applicazione locale su richiesta dell'utente e conservare il punto di ripartenza.

**Fatto.** Arrestato il server Laravel avviato per la verifica della Fase 7.1. Annotata nel Backlog la pausa e la sequenza delle attività successive.

**Decisioni.** Riprendere lo sviluppo dal punto 7.2, occupazione automatica delle risorse, poi 7.3, pacchetti prestabiliti. Il collaudo complessivo e le eventuali correzioni/migliorie seguiranno il completamento delle aggiunte; restano le verifiche mirate dei singoli interventi.

**Problemi e soluzioni.** Nessun problema durante l'arresto.

**Verifica.** Dopo l'arresto nessun processo in ascolto sulle porte 8000 e 5173. Nessun codice modificato e nessun nuovo test eseguito; gli esiti restano quelli della precedente voce.

**Aperto.** Fase 7.2 e 7.3; collaudo manuale conservato al Giro 5, Seduta A, passo 4. Lavoro e dati locali conservati; modifiche della Fase 7.1 non committate.

### 2026-10-01 — Piano della Fase 7 e gestione delle risorse completata

**Obiettivo.** Annotare il piano concordato e realizzare il primo punto: risorse condivise e requisiti dei servizi.

**Fatto.**

- Piano in Backlog con criteri di completamento per 7.1, occupazione automatica 7.2 e pacchetti prestabiliti 7.3; scritto prima di iniziare il codice.
- Nuova pagina manager `resources/views/pages/app/⚡resources.blade.php`, voce di menu e rotta `app.resources`: creazione, modifica, disattivazione, eliminazione protetta e indisponibilità nel fuso del portale.
- `Resource`, `ResourceBlock`, `ServiceResourceRequirement`, relative factory, policy e form; migrazione additiva `2026_10_01_152735_create_shared_resource_catalog.php` applicata al database locale senza reset.
- `ServiceForm` e pagina Servizi: requisiti necessari con alternative, limite di 10 requisiti, risorse vincolate al portale e non ripetibili; salvataggio transazionale insieme a servizio, operatori e domande.
- Traduzioni IT/EN, registro delle azioni e `ResourceSeeder` idempotente: due sale demo aggiunte allo Studio Rossi senza modificare i servizi esistenti.
- 31 nuovi test in `ResourceManagementTest.php` e `ServiceResourceRequirementsTest.php`: permessi, isolamento, validazione, fusi estivo/invernale, eliminazioni, requisiti e regressioni del percorso pubblico.

**Decisioni.** Questa tappa configura il catalogo e non occupa ancora le risorse: avviso esplicito in entrambe le pagine. Una riga identifica una risorsa fisica; ogni requisito sarà necessario e offrirà una scelta fra alternative. Le risorse collegate si disattivano oppure si scollegano prima della rimozione. Regole permanenti registrate in `.ai/rules/models.md`.

**Problemi e soluzioni.** La relazione caricata nel filtro delle indisponibilità richiedeva `HasMany`, non `Builder`: corretto il tipo dopo il primo giro di test. Pint interpretava `Resource` nei generici PHPDoc come il tipo PHP `resource`: nomi completamente qualificati e factory con query `sole()` hanno risolto gli errori dell'analisi statica. Nel browser gli asset non caricavano per `public/hot`, rimasto sul server di sviluppo spento: eliminato il marcatore generato e utilizzata la build aggiornata.

**Verifica.** Test delle nuove funzionalità: 31 passati, 140 asserzioni; regressioni mirate su domande, registro, binding, ruoli, pubblico e gruppi: 70 passati, 233 asserzioni. Suite completa `php artisan test --compact`: 525 test, 523 passati, 2 saltati, 1466 asserzioni. `php vendor/bin/pint --dirty --format agent` passato; PHPStan livello 7 con memoria 1G: 0 errori; `npm run build` riuscito (avviso già presente per il font di fallback opzionale). Migrazione e seed mirato riusciti; idempotenza verificata nei test. Browser: pagina Risorse e modali, requisiti e alternative nei Servizi, controllo a 390 px senza overflow orizzontale; vista desktop ripristinata. Non è una prova su telefono fisico.

**Aperto.** Prossimo intervento: 7.2, assegnazione e occupazione delle risorse nelle prenotazioni. Poi 7.3, pacchetti prestabiliti. Il precedente collaudo riparte sempre dal Giro 5, Seduta A, passo 4. Modifiche di questa sessione non committate.

### 2026-10-01 — Verificata la base per risorse condivise e pacchetti

**Obiettivo.** Valutare se il progetto è pronto per iniziare l'aggiunta discussa con l'utente.

**Fatto.** Ricontrollati ambiente, versioni installate e stato Git; eseguiti i test esistenti su disponibilità, azioni di prenotazione, percorso pubblico, servizi di gruppo e avvisi del calendario. Nessun codice del prodotto modificato.

**Decisioni.** Raccomandata una prima fase dedicata alle risorse condivise, seguita da pacchetti prestabiliti con servizi consecutivi e un unico operatore abilitato a tutti i componenti. Composizione libera, operatori diversi e tempi di posa restano sviluppi successivi da valutare. La verifica di fattibilità non avvia l'implementazione.

**Problemi e soluzioni.** Nessun errore nei controlli eseguiti. Rispetto alla prima lettura della sessione, le modifiche del precedente collaudo risultano ora incluse nel commit `3167d24`; soltanto il diario ha modifiche locali.

**Verifica.** PHP 8.5.0; dipendenze dirette verificate con `composer show --direct`. `php artisan test --compact tests/Feature/SlotFinderTest.php tests/Feature/BookingActionsTest.php tests/Feature/PublicBookingTest.php tests/Feature/GroupServiceTest.php tests/Feature/CalendarNoticeTest.php` → 80 test passati, 202 asserzioni. Suite completa non rieseguita; nessuna formattazione o build necessaria per questa verifica senza modifiche al prodotto.

**Aperto.** Implementazione delle risorse e dei pacchetti da avviare su indicazione dell'utente. Rimane il collaudo manuale dal Giro 5, Seduta A, passo 4; backlog invariato durante la verifica.

### 2026-10-01 — Ripreso il punto del collaudo

**Obiettivo.** Ricostruire per l'utente dove si era interrotto il lavoro.

**Fatto.** Letti il diario, `scratchpad/giro-5.md` e lo stato Git. L'ultima sessione operativa registrata è quella del 2026-09-09: Giro 5, Seduta A, controlli 1–3 conclusi; il prossimo passo è il 4, l'elenco pubblico dei servizi. Discussa su richiesta dell'utente l'applicabilità a parrucchieri, estetiste e tatuatori: buona base per appuntamenti con durata definita e consulenze; esigenze ulteriori da valutare sono servizi combinati e tempi di posa, risorse condivise, durata personalizzata, acconti e promemoria SMS/WhatsApp. Consultate pagine ufficiali di Square e Fresha come confronto di settore.

**Decisioni.** Nessun nuovo intervento sul prodotto; ripartenza dal percorso del cliente già preparato. L'utente indica come priorità più servizi nello stesso appuntamento e risorse condivise, e propone una sezione Risorse con collegamento ai servizi. Proposta discussa, ancora da confermare nei dettagli: risorse fisiche individuali con alternative equivalenti, requisiti simultanei e tempi di preparazione/pulizia; pacchetti prestabiliti composti da servizi esistenti come primo passo, combinazioni selezionate dal cliente in seguito con vincoli di compatibilità e ordine. Un appuntamento deve conservare le singole fasi e le risorse effettivamente assegnate, con un unico flusso di conferma, spostamento e annullamento. Nessuna implementazione avviata o approvata dalla sola discussione.

**Problemi e soluzioni.** Nessun nuovo problema verificato. Le modifiche del 2026-09-09 risultano ancora presenti e non committate.

**Verifica.** Consultati `git status --short` e gli ultimi cinque commit. Nessun test eseguito in questa sessione di riepilogo; gli esiti dei test restano quelli registrati il 2026-09-09.

**Aperto.** Giro 5 dal passo 4 della Seduta A, poi sedute B–D e passata degli errori; Giro 6 e prova su telefono vero ancora da completare. Backlog invariato.

### 2026-09-09 — Giro 5 aperto: tre schermate che non spiegavano quello che mostravano

**Obiettivo.** Cominciare il Giro 5, quello che i test non possono giudicare. Prima però un banco di prova: il seeder demo lascia tre prenotazioni future su un servizio, e report, filtri, esiti e lista d'attesa si giudicano male da vuoti.

**Fatto.**

- **`scratchpad/seed-giro5.php`** — allestimento del banco, da lanciare su un database appena seminato (si rifiuta di girare due volte). 39 prenotazioni: due mesi di storico sui giorni lavorativi con tutti gli stati, tre passate lasciate «Confermata» a bella posta per il Giro 6, due richieste in attesa, una spostata, una annullata. Sei servizi: uno di gruppo (8 posti, 4 presi, più un giorno riempito fino agli ultimi 2), uno con un solo operatore, uno nascosto, uno a conferma manuale. Domande personalizzate, 8 clienti con note, lista d'attesa, chiusura 14–20 ottobre, blocchi ed eccezioni, orari serali per Luca, e **due job falliti veri** — messi in coda per davvero e poi spostati in `failed_jobs`, così «Riprova» lavora su un payload autentico. Le future passano da `CreateBooking`, lo storico da factory con `created_at` retrodatato.
- **`scratchpad/giro-5.md`** — i passaggi delle quattro sedute e il foglio dei ritrovamenti.
- **Tre correzioni**, tutte nate da quello che il collaudatore ha letto a schermo: la scarsità dei posti spostata su fascia e giorno, le righe che spiegano i giorni grigi (`App\Support\CalendarNotes`), l'etichetta e la spiegazione del fuso orario. Dettaglio in [Problemi trovati durante il collaudo](#problemi-trovati-durante-il-collaudo).

**Decisioni.**

**Un giorno si marca «quasi pieno» solo se lo sono tutte le sue fasce.** La tentazione era marcarlo appena una fascia è corta. Sarebbe lo stesso difetto da cui siamo partiti: un segnale che promette scarsità e porta in una sala mezza vuota costa un click e insegna a ignorare i segnali. Soglia relativa, `Service::seatsRunningLow()`: un quarto della capienza, minimo uno.

**Delle chiusure escono solo le date.** `PortalClosure::reason` lo scrive il manager per la propria agenda — il segnaposto del campo dice «Ferie, festività, …» — e «chiuso per lutto» non è affare del visitatore. Un test lo verifica.

**Per il fuso, due orari invece di una frase.** «Sei ore avanti» sarebbe sbagliato con l'India (+5:30) e due volte l'anno col cambio dell'ora. Nel modulo si stampano l'ora del visitatore e quella del portale una sotto l'altra: si spiegano da sé e non c'è niente da mantenere. L'etichetta della tendina riusa la chiave `Timezone` già presente, la stessa delle altre cinque tendine dell'app.

**Il seeder resta uno script, non un seeder di Laravel.** Serve solo al collaudo a mano, non deve entrare in `DatabaseSeeder` né essere eseguito in produzione. Sta in `scratchpad/` sotto controllo di versione, con in testa la ragione per cui non è un test — la lezione del 2026-09-07, quando i file citati dalla checklist erano spariti.

**Problemi e soluzioni.**

**Il colore di un servizio si legge come uno stato.** Il difetto della sessione. La barra arancione in testa alla card era `displayColor()`, cioè branding, ma un colore caldo dice «attenzione»: il collaudatore è entrato nella card convinto che i posti stessero finendo. Non l'ho cambiato — è il colore che il manager scelga — ma è la ragione per cui la scarsità ora si scrive con parole e non con una tinta. Annotato in `.ai/rules/portal.md`.

**«1 posti liberi».** Trovato mentre riscrivevo la fascia oraria: `":count places left"` era a plurale fisso. È la stessa rottura chiusa il 2026-09-05 in dieci frasi, sopravvissuta qui perché quel giro non era passato dai servizi di gruppo. Ora `trans_choice`, e la regola è registrata in `.ai/rules/views.md` invece di restare un fatto storico.

**Il partial del calendario è condiviso con la pagina di spostamento.** Aggiungendo `$this->nearlyFullDays` dentro `partials/booking-calendar` tre test di `PublicBookingTest` sono caduti con «Property [$nearlyFullDays] not found on component: [pages::portal.booking]»: quella pagina non ha quella proprietà, e i gruppi non si spostano comunque. I dati ora entrano nel partial come variabili d'include (`['nearlyFull' => ..., 'notes' => ...]`) con default vuoto, invece di essere letti da `$this`.

**Un conteggio per ora disegnata.** `seatsLeftAt()` faceva una query per slot: accettabile per un giorno, non per una griglia mensile. Aggiunto `GroupSlotFinder::seatsLeftBetween()`, una sola query con `withCount` sui posti occupati, e la pagina la tiene in un `#[Computed]` azzerato dove si azzera `monthSlots`.

**Una prova che si mordeva la coda.** Il test dello spostamento apriva il calendario con la finestra già ridotta a un giorno, e `SlotFinder` non offriva più lo slot su cui prenotare. Ora lo slot si prende con la finestra larga e la finestra si stringe dopo, prima di caricare la pagina.

**Verifica.**

```
vendor/bin/pest tests/Feature/GroupServiceTest.php      → 18 test, 18 passati
vendor/bin/pest tests/Feature/CalendarNoticeTest.php    → 6 test, 6 passati
vendor/bin/pest tests/Feature/PublicBookingTest.php     → 21 test, 21 passati
vendor/bin/pint --dirty --format agent                  → passed
vendor/bin/phpstan --memory-limit=1G                    → passed, 0 errori
php artisan test --compact                              → 494 test, 492 passati, 2 saltati
```

Tredici test nuovi. Le due formulazioni dei posti con il singolare, il giorno segnato solo se tutte le fasce sono corte, la sessione svuotata dagli annullamenti che torna a sala piena, la mappa dei posti in una query, la soglia del quarto di sala; la riga della finestra che compare solo se serve, le tre formulazioni delle chiusure, il motivo che non esce, le stesse righe sulla pagina di spostamento; l'etichetta del fuso, la riga del portale solo con fusi diversi, e i due orari affiancati con il conteggio che verifica che a casa il fuso sia nominato una volta sola. `lang/it.json` a 908 chiavi.

Un check di sicurezza sulle modifiche non ha prodotto rilievi: le query nuove restano vincolate al portale del servizio, tutto il nuovo output passa da `{{ }}`, e le righe pubblicate (fine finestra, date di chiusura) erano già deducibili da quali giorni sono cliccabili.

**Aperto.** Il **Giro 5 riprende dal passo 4 della Seduta A** — il flusso del cliente dall'elenco servizi alla prenotazione, allo spostamento, all'annullamento, e poi inglese e 390 px. Sedute B, C, D e la passata degli errori non iniziate. Resta il **Giro 6** e il passo del Giro 1 sul telefono vero. Le modifiche di questa sessione **non sono ancora committate**.

### 2026-09-07 — Giro 4 chiuso: l'errore SMTP che parlava alla libreria e non al gestore

**Obiettivo.** Finire il Giro 4 dell'admin, dal passo 3 all'11: dettaglio portale, rigenerazione password, SMTP, sospensione, filtri account, disabilitazione, 403, eliminazione con cascata.

**Come è stato percorso.** Senza mani sulla tastiera e senza browser: un piccolo driver HTTP (sessione con cookie, token CSRF preso dal cookie `XSRF-TOKEN`, chiamate vere a `POST /livewire-<hash>/update` con lo snapshot letto dalla pagina) guida l'applicazione come farebbe Chrome. Passa da rotte, middleware, policy, componenti, azioni e mailer; il testo delle schermate si legge con `DOMDocument`, non a regex. **Non sostituisce il Giro 5**: dice che le cose funzionano, non che si capiscano a colpo d'occhio. Il codice del driver sta in `scratchpad/collaudo-driver.php`, con in testa la ragione per cui non è un test.

**Fatto.** Nove passi su nove.

- **Dettaglio e password** (3, 4): la rigenerazione è stata provata due volte di fila, così da avere una password «vecchia» vera da rifiutare — e viene rifiutata; la nuova entra.
- **SMTP e mittente estraneo** (5, 5b): salva, avvisa che `prenotazioni@altro-dominio.test` non è il dominio del nome utente, il riquadro resta «Configurato, non ancora verificato» e l'anteprima mostra ancora l'indirizzo della piattaforma con quello del portale su «Dopo la prova». La prova esce davvero dalla 1026 **con il mittente estraneo**: la correzione del 2026-09-04 tiene.
- **I rifiuti veri** (5c, passo nuovo): `scratchpad/fake-smtp.php` ha imparato `--reject-sender` e `--reject-auth`, e riproduce in locale i tre rifiuti che il Giro 7 aveva dovuto rimandare a un provider vero — 550 sul mittente, 535 sulle credenziali, porta muta.
- **Sospensione** (6, 7): pagina pubblica 200 → 404 → 200.
- **Filtri account** (8): ruolo, portale, ricerca testuale, e le combinazioni. Sono `#[Url]`, quindi guidati dalla query come li guida il browser.
- **Account disabilitato** (9): la sessione aperta resta valida fino al caricamento successivo, che lo rimanda al login con «Questo account è stato sospeso». Riattivato, rientra.
- **403 dell'admin** (10) e **eliminazione con cascata** (11): il portale è stato prima riempito (operatore, servizio, disponibilità, prenotazione futura) perché l'eliminazione avesse qualcosa da portarsi via. Il modale avvisa al **singolare** — «ha ancora una prenotazione futura» — e dopo l'eliminazione restano zero righe su utenti, servizi, prenotazioni, regole di disponibilità e tabella pivot.

**Problemi e soluzioni.**

**L'errore del server SMTP era scritto per la libreria.** Il difetto della sessione, trovato al passo 5c. Le tre risposte del server arrivavano a schermo così come le formula il trasporto Symfony: in inglese dentro una schermata italiana, con i codici di risposta al posto del campo sbagliato, e la prima troncata a metà parola dai 300 caratteri di `readable()`. Il caso che pesa è il 550 sul mittente: la schermata **prevede** quel rifiuto, l'avviso giallo dice testualmente «quasi tutti i server lo rifiutano, e l'errore che restituiscono spiega poco» — e poi mostrava proprio quell'errore che spiega poco, senza mai dire che bastava rimettere il proprio indirizzo nel campo mittente.

`App\Support\MailFailure::explain()` traduce i sei casi che ricorrono davvero (credenziali, mittente, destinatario, host inesistente, porta muta, cifratura sbagliata) e restituisce `null` per tutto il resto: davanti a un errore mai visto le parole del server valgono più di una spiegazione inventata sul campo sbagliato. Sul campo va l'istruzione, nel riquadro restano le parole del server — che sono la prova da girare al supporto del provider, e prima erano stampate due volte identiche. `mail_error` continua a conservare il testo grezzo: storage = fatti, presentazione = spiegazione.

**Due cose che sembravano difetti e non lo erano.** `mailDeliveryFailing()` resta `false` dopo una prova fallita, e in effetti deve: distingue apposta l'invio vero che nessuno ha visto dalla prova con una persona davanti allo schermo. E un account disabilitato supera ancora il `POST /login` — la sessione nasce e muore al caricamento dopo — ma `EnsureUserIsActive` è in coda al gruppo `web` al completo, quindi nessuna rotta lo lascia passare e il messaggio arriva. Annotato, non toccato: cambiare il punto in cui si rifiuta un accesso è una modifica di sicurezza, e non migliorerebbe niente di visibile.

**Un falso allarme mio.** Il primo estrattore di testo del driver spezzava le classi Tailwind con `>` dentro (`[&:has(>[data-flux-menu-item-has-icon])]:block`) e le faceva sembrare markup che colava nella pagina. Era la mia regex, non il prodotto: `<[^>]+>` non sa che dentro un attributo quotato il `>` è legittimo. Passato a `DOMDocument`.

**Ritrovamenti.** `scratchpad/` era sparito fra una sessione e l'altra, portandosi via `fake-smtp.php` che la checklist cita per nome. Riscritto e questa volta **messo sotto controllo di versione** insieme al driver e al seeder del passo 11: una procedura che rimanda a file che non esistono più costa mezz'ora ogni volta.

**Verifica.**

```
vendor/bin/pest tests/Feature/TenantMailerTest.php → 44 test, 44 passati
vendor/bin/pint --dirty --format agent            → passed
vendor/bin/phpstan --memory-limit=1G              → passed, 0 errori
php artisan test --compact                        → 481 test, 479 passati, 2 saltati
```

Tre test nuovi: la mappatura di `explain()` con i quattro errori nelle parole esatte del trasporto (più il caso non riconosciuto, che deve restare `null`), il campo che dice cosa fare mentre il riquadro conserva le parole del server, e la resa italiana della spiegazione. `lang/it.json` a 902 chiavi.

**Aperto.** Restano il **Giro 5** e il **Giro 6**, che chiedono occhi umani su schermate vere — testi che suonano naturali, flussi troppo lunghi, schermo piccolo. Il **Giro 1** (il cliente) era già stato percorso, ma il passo sul telefono vero non è replicabile da qui. Il portale `collaudo-giro-4b` resta in piedi con SMTP svuotato; `collaudo-giro-4` non esiste più.

### 2026-09-05 — Giro 4 iniziato: il 500 che si mangiava la password del manager

**Obiettivo.** Cominciare il Giro 4, quello dell'admin: creazione portali, credenziali, SMTP, sospensione, eliminazione.

**Dove si è fermato.** Passi 1 e 2 fatti. Si riprende dal **passo 3**, con due portali di prova già in piedi (vedi la tabella nel Giro 4).

**Problemi e soluzioni.**

**Creare un portale finiva in errore 500 proprio mentre mostrava la password.** Il modale del form portali aveva `@close="$wire.resetValidation()"`. `$wire` però raggiunge **solo i metodi dichiarati sul componente**: `resetValidation()` viene da un trait di Livewire e non è esposto al browser, quindi `MethodNotFoundException: Public method [resetValidation] not found on component`.

Perché era rimasto nascosto: l'errore parte da un evento del markup, non da un'azione. Finché si chiudeva il modale a mano non lo notava nessuno — chiudere e vedere un 500 in una richiesta in background non cambia la pagina. Salvando un portale invece il codice chiude il form e apre subito il modale delle credenziali: il `@close` scatta, l'eccezione diventa una pagina di errore, e con essa se ne va **la password del manager, mostrata una volta sola**. Il portale nel frattempo era stato creato: falliva soltanto la chiusura.

Perché nessun test lo copriva: i test Livewire chiamano i metodi direttamente, non passano mai da `$wire`. Il guardiano nuovo lavora quindi sul markup renderizzato — se qualcuno rimette `$wire.` davanti a un metodo del framework, il test cade. Verificato reintroducendo il difetto: fallisce.

Attributo rimosso senza sostituirlo, perché il reset lo fanno già `createPortal()` ed `editPortal()` all'apertura. Regola registrata in `.ai/rules/views.md`.

**Verifica.**

```
vendor/bin/pest tests/Feature/PortalManagementTest.php → 6 test, 6 passati
vendor/bin/pint --dirty --format agent                 → passed
php artisan test --compact                             → 478 test, 476 passati, 2 saltati
```

**Aperto.** Giro 4 dal passo 3 in poi, più i Giri 5 e 6.

### 2026-09-05 — Giro 3: il manager, dai servizi alle email del portale

**Obiettivo.** Percorrere il Giro 3 per intero: servizi e parametri degli slot, staff, agenda, disponibilità altrui, impostazioni del portale.

**Fatto.** Diciassette passi su diciassette. Le cose che valeva la pena verificare a mano:

- **Parametri dello slot.** Servizio nuovo con durata 45 e intervallo 15, poi preavviso e pause. Il conto delle pause torna: con una prenotazione 10:00–10:45 e 15 minuti per lato, la mattina si riapre alle **11:15** — uno slot alle 11:00 vorrebbe la sua pausa dalle 10:45, dentro quella della prenotazione. Il motore toglie ciò che si sovrappone, non solo ciò che sta dentro.
- **Conferma manuale.** La prenotazione arriva «in attesa» e il cliente riceve un messaggio che dice di aspettare, **senza** file calendario; il `.ics` parte solo con la conferma. Due email distinte, non una riscritta.
- **Staff.** Operatore nuovo con credenziali mostrate una volta sola e funzionanti; eliminazione rifiutata finché ha prenotazioni future, con il pulsante disabilitato oltre al controllo nel metodo. Provati sia il plurale sia il singolare del messaggio corretto stamattina.
- **Agenda di un altro.** Orari salvati su Antonio, quelli di Marco intatti: il rischio vero era scrivere sull'operatore sbagliato.
- **Il colore del brand arriva alle email.** Cambiato in rosso, le tre email della prenotazione successiva lo portano dentro, senza tracce del verde precedente.
- **La copia al manager** è un messaggio **separato**, non una copia carbone: l'operatore non vede che qualcuno è in ascolto.

**Problemi e soluzioni.**

**I filtri non dicevano cosa filtravano.** Quattro tendine in fila, nessuna etichetta, e l'ultima diceva soltanto «Tutti»: è il filtro operatore, ma si capiva per esclusione. Peggio ancora una volta scelto un valore, perché la dimensione spariva. Cercando la stessa forma sono usciti **12 filtri senza nome in 6 pagine**. Etichettate le quattro barre filtri (Prenotazioni, Registro, Portali, Account), allineate al fondo perché le etichette non sfalsassero i campi, e date un nome alle due date del Registro, che erano due campi nudi separati da un trattino. Lasciate come stavano le due tendine incastonate nelle intestazioni (periodo dei Report, operatore in Disponibilità): lì le voci si spiegano da sole e un'etichetta sballerebbe la riga del titolo.

**Correzione alla checklist.** Il passo 18 — mittente su un altro dominio — non è più cosa da manager: dal 2026-09-02 i campi del server li vede solo l'admin. Spostato al Giro 4 come passo 5b.

**Verifica.**

```
vendor/bin/pest BookingAgendaTest ActivityLogTest PortalManagementTest AccountManagementTest → 44 test, 44 passati
vendor/bin/pint --dirty --format agent                                                       → passed
php artisan test --compact                                                                   → 477 test, 475 passati, 2 saltati
```

Un test nuovo, `every filter says what it filters`, che fissa le etichette dell'agenda. `lang/it.json` a 896 chiavi.

**Aperto.** Restano i Giri 4 (admin), 5 e 6. Il portale demo è ormai pieno di dati di collaudo: brand rosso, un servizio nascosto, un operatore in più, orari rimaneggiati.

### 2026-09-05 — Giro 2 chiuso: i rifiuti della disponibilità, e il plurale che non c'era

**Obiettivo.** Finire il Giro 2 dai passi 11–16: fasce sovrapposte, blocchi ed eccezioni sopra una prenotazione, e gli orari settimanali che avvisano senza bloccare.

**Fatto.**

- **Fasce sovrapposte** (11): rifiutate con il giorno nel messaggio; corrette e salvate, **l'errore sparisce** — la regressione del 27/08 non è tornata.
- **Blocco su fascia libera** (12): venerdì `09:00–10:00` → gli slot pubblici partono dalle 10:00. Quattro slot in meno per un blocco di un'ora: cade anche quello delle 09:45, che comincerebbe dentro il blocco. Il motore toglie ciò che si **sovrappone**, non solo ciò che sta dentro.
- **Blocco ed eccezione sopra una prenotazione** (13, 14): entrambi rifiutati, e con messaggi diversi — «in quel periodo» per il blocco, «fuori dai nuovi orari» per l'eccezione che riscrive la giornata. Provate tutte e due le strade dell'eccezione: chiusura totale e orari nuovi.
- **Eccezione che copre la prenotazione** (15): accettata; la giornata passa a `08:00–13:00` e il pomeriggio sparisce, perché un'eccezione **sostituisce** gli orari, non li somma.
- **Orari ridotti** (16): il mercoledì passa a solo pomeriggio e **salva**, avvisando che due prenotazioni restano scoperte, elencate con data, cliente e servizio. Restano confermate: gli orari valgono per il futuro.

**Problemi e soluzioni.**

**«In quel periodo ci sono 1 prenotazioni».** Il rifiuto del passo 13 ha mostrato il difetto: nessun messaggio con un conteggio gestiva il singolare. Cercando la stessa forma sono saltate fuori **dieci frasi in sette file** — non solo la disponibilità, ma anche gli avvisi che difendono le eliminazioni (portale, account, servizio, operatore, cliente) e la conferma di cancellazione dati. Tutte passate da `__()` a `trans_choice()`, con singolare e plurale scritti in entrambe le lingue. Il difetto era visibile solo in italiano nella pratica, quindi un test lo verifica proprio in italiano.

**Verifica.**

```
vendor/bin/pest tests/Feature/TimeBlockTest.php DateOverrideTest.php → 25 test, 25 passati
vendor/bin/pint --dirty --format agent                               → passed
vendor/bin/phpstan --memory-limit=1G                                 → passed, 0 errori
php artisan test --compact                                           → 476 test, 474 passati, 2 saltati
```

Tre test nuovi: il conteggio del rifiuto al singolare e al plurale (blocchi e eccezioni), e la resa italiana del singolare.

**Aperto.** Restano i Giri 3 (manager), 4, 5 e 6. Il portale demo porta i segni del collaudo: giovedì chiuso, venerdì ridotto, mercoledì solo pomeriggio, un blocco e un'eccezione. `php artisan migrate:fresh --seed` riparte pulito.

### 2026-09-04 — Giro 2 del collaudo: l'operatore, fino al passo 10

**Obiettivo.** Percorrere il Giro 2 subito dopo il Giro 7, con lo stesso ambiente ancora acceso (server finti su 1025 e 1026, quindi Studio Rossi manda dal proprio server verificato).

**Fatto.** Passi 1–10, cioè perimetro dei permessi, agenda, annullamento, profilo pubblico, fuso, lingua, e la disponibilità che arriva alla pagina pubblica.

- **Perimetro.** Barra laterale con le sole voci sue; `/app/services` a mano → 403 che dice il ruolo e chi sei, con due uscite; in Prenotazioni solo le sue, nessuna delle sei di Marco Rossi.
- **Annullamento.** Rifiutato senza motivazione; con «malattia» → `cancelled`, `cancelled_by` l'operatore, email al cliente **dalla 1026** (il server del portale) con il motivo, il pulsante per riprenotare e un `.ics` `METHOD:CANCEL`. Nessuna email all'operatore: l'ha annullata lei.
- **Profilo.** Ruolo e biografia compaiono davvero sulla pagina pubblica del servizio; fuso e lingua salvati sull'account, la lingua sopravvive al riaccesso.
- **Disponibilità.** Tolto il giovedì → 0 slot quel giorno; venerdì portato a `09:00–11:00` + `14:00–16:00` → 14 slot, `09:00…10:30` e `14:00…15:30`, niente in mezzo. Verificato anche lato motore con `SlotFinder`, non solo a schermo.

**Correzioni alla checklist** (due sviste della checklist stessa, non del prodotto):

1. Il Giro 2 diceva di accedere con un indirizzo Gmail personale, account creato a mano in una sessione vecchia e sparito col `migrate:fresh` successivo. Ora indica `giulia@digithub.test`.
2. Mancava **Report** fra le voci della barra laterale dell'operatore: è arrivato con la Fase 6.1 e la checklist era rimasta alla Fase 2. È legittimo e filtrato (`user_id = auth()->id()`, sottotitolo «Come sta andando il tuo lavoro»), coperto dal test `an operator only reports on their own bookings`. Aggiunto il passo 1b che lo verifica.

**Problemi e soluzioni.** Nessun difetto del prodotto in questi dieci passi.

**Verifica.** Manuale, con controllo dello stato nel database dopo ogni blocco. Nessun test automatico toccato in questa parte.

**Aperto.** Passi 11–16 (i rifiuti su fasce sovrapposte, blocchi ed eccezioni sopra prenotazioni, e gli orari settimanali che avvisano senza bloccare). L'ambiente è predisposto: `scratchpad/seed-giro2.php` rifà le prenotazioni che servono.

### 2026-09-04 — Giro 7 del collaudo: la consegna delle email, con due server SMTP finti

**Obiettivo.** Percorrere per intero il Giro 7 della checklist — la consegna delle email decisa il 2026-09-02 — senza un provider vero e senza credenziali reali.

**Come.** Un server SMTP giocattolo in PHP (`scratchpad/fake-smtp.php`, fuori dal repository): parla quel tanto di ESMTP che serve a Symfony (EHLO, AUTH LOGIN/PLAIN, MAIL FROM, RCPT TO, DATA), salva ogni messaggio come `.eml` e stampa a video mittente, destinatario e oggetto. Due istanze: **1025** per la piattaforma (dove `MAIL_HOST` già puntava) e **1026** per il portale. Così si vede il `MAIL FROM` reale di ogni email, e il guasto si riproduce spegnendo la 1026. Prima di toccare i dati, copia di `database.sqlite` nello scratchpad; i parametri SMTP veri di Studio Rossi sono stati azzerati per partire dal caso «manda la piattaforma».

**Cosa ha retto.** Tutte le caselle del Giro 7. In particolare i due passaggi che valevano la decisione del 2 settembre:

- parametri salvati ma **non provati** → la prenotazione esce comunque dalla 1025 con `From: notifiche@digithub.test`, la 1026 resta muta;
- server verificato e poi spento → il primo invio fallisce, `RecordMailFailure` azzera `mail_verified_at`, e **il messaggio successivo dello stesso lotto esce già dalla piattaforma**. Il portale mostra «Un'email non è partita» con data ed errore, l'elenco portali il badge rosso.

**Problemi e soluzioni.**

1. **Il messaggio di prova partiva come la piattaforma.** Sul server finto: `MAIL FROM:<notifiche@digithub.test>` su un invio che stava passando dalla 1026. Causa: `MailerTestMail` usava `Tenant::mailFromAddress()`, che risponde «cosa esce oggi» e quindi, con `mail_verified_at` ancora null, dà l'indirizzo nostro. Ma la prova è proprio ciò che viaggia sul server del portale: quel mittente lì è estraneo, ed è il caso in cui i provider rispondono `550 sender not allowed`. Una prova poteva quindi fallire con parametri giusti, o passare senza dimostrare che il mittente del portale è accettato — cioè senza provare la cosa che deve provare. Aggiunta `Tenant::ownMailFromAddress()` (`mail_from_address ?: mail_username`, indipendente dalla verifica), usata dalla prova; `mailFromAddress()` resta invariata per gli invii veri. Regola in `.ai/rules/mail.md`.
2. **L'anteprima prometteva un mittente che non era in uso.** Stessa radice: guardava solo se i campi erano compilati. Ora finché il server non è verificato mostra l'indirizzo della piattaforma e mette quello del portale su una riga «Dopo la prova:».
3. **`php artisan tinker percorso.php` resta appeso** (timeout a 2 minuti, nessun output). Con `--execute 'require "percorso";'` funziona.

**Da tenere presente.** Il worker di sviluppo gira `--tries=1`: la conferma al cliente caduta sul guasto non è stata ritentata e resta in `failed_jobs`. In produzione il worker documentato è `--tries=3` e il secondo tentativo trova il portale già retrocesso, quindi la conferma parte dalla piattaforma. La retrocessione protegge i messaggi successivi; il primo lo salva solo il riprovare.

**Verifica.**

```
vendor/bin/pest tests/Feature/TenantMailerTest.php  → 41 test, 41 passati
vendor/bin/pint --dirty --format agent              → passed
vendor/bin/phpstan --memory-limit=1G                → passed, 0 errori
php artisan test --compact                          → 473 test, 471 passati, 2 saltati
```

Quattro test nuovi: la prova parte come il portale (con e senza indirizzo mittente), l'anteprima non promette il mittente del portale finché non è verificato. `lang/it.json` a 891 chiavi.

**Aperto.** Il Giro 7 va rifatto una volta su SMTP reale: il rifiuto del mittente da parte del provider è l'unica cosa che un server finto non può riprodurre, ed è il caso che ha originato entrambe le correzioni di oggi. Restano da percorrere i Giri 2, 3, 4, 5 e 6.

### 2026-09-02 — La posta la manda la piattaforma; l'SMTP del portale è un'eccezione da provare

**Decisione.** Come fanno Calendly e Cal.com: per impostazione predefinita le email delle prenotazioni partono dalla piattaforma, con il nome del portale e il suo indirizzo di risposta — SPF e DKIM sono i nostri, che è quasi sempre più affidabile di una casella Aruba o Register.it usata come server d'invio. L'SMTP proprio resta disponibile **su richiesta**, configurato da noi in fase di attivazione.

**Chi tocca cosa.** Nuova ability `TenantPolicy::configureMailer`, solo admin. Il manager non vede più i campi del server: legge lo stato, il server e l'utente configurati, e può premere «Invia prova». Resta suo tutto ciò su cui può agire — indirizzo di risposta, copia delle prenotazioni, ore di promemoria. Gli operatori, come prima, non entrano nella schermata.

**Il cambio vero: i parametri non bastano.** `hasMailerSettings()` dice che host, utente e password ci sono; `hasCustomMailer()` — l'unico che decide trasporto e mittente — vuole anche `mail_verified_at`, la nuova colonna. Quindi:

- una configurazione salvata e mai provata **non** dirotta niente: si continua a mandare dalla piattaforma;
- la prova riuscita mette in servizio il server, quella fallita lo toglie;
- un invio vero fallito lo toglie a sua volta, e il portale torna sulla piattaforma. Un mittente sbagliato vale meno di una conferma che non parte;
- salvare parametri nuovi li rimette in dubbio: servono parametri nuovi e prova nuova.

È la risposta strutturale al guasto di ieri: una configurazione sbagliata non può più mangiarsi le email, chiunque l'abbia scritta.

**Migrazione.** `tenants.mail_verified_at`, con i portali già funzionanti (test passato, nessun errore) promossi in automatico.

**Nota.** `plans.allows_custom_smtp` esiste già ma non è ancora applicato: quando si passerà ai piani, quello diventa l'interruttore per «SMTP proprio su richiesta».

**Verifica.** `php artisan test --compact` → **470 test, 468 passati, 2 saltati** (sei nuovi: parametri non provati, prova che mette in servizio, guasto che riporta alla piattaforma, manager che non può cambiare il server né vederne i campi, parametri modificati che perdono la verifica). Pint pulito, PHPStan 0 errori, `lang/it.json` a **890 chiavi**, 0 mancanti e 0 inutilizzate, `npm run build` ok.

### 2026-08-31 — Un invio fallito ora si vede dove serve

**Perché.** Con l'SMTP rotto le prenotazioni continuavano ad arrivare, le email morivano in `failed_jobs` e la schermata delle impostazioni parlava solo dell'ultimo *test* — che poteva essere passato mesi prima, magari prima che la password cambiasse. Il gestore non aveva modo di accorgersene.

**Come funziona ora.** I mailable di portale condividono il trait `Mail\Concerns\DeliveredByPortal`, che sceglie il mailer del tenant dentro il job e implementa `failed()`: la coda lo chiama quando il job si arrende, e `Actions\Tenants\RecordMailFailure` scrive l'errore su `tenants.mail_error` + la nuova colonna `mail_failed_at`. Solo per i portali con SMTP proprio: chi manda dal mailer della piattaforma non può farci nulla, quindi quell'errore va a log per noi.

**Cosa si vede.** Nella scheda del portale l'avviso distingue i due casi: «Un'email non è partita», con data e messaggio del server, quando è caduto un invio vero; «L'ultimo test è fallito» quando c'era una persona davanti allo schermo. Salvare le impostazioni o rifare il test riuscito azzera l'allarme. Nell'elenco portali dell'amministratore compare il badge rosso «Email ferme» accanto allo stato.

**In più.** Il `send()` duplicato in `BookingMail` e `WaitingListOpeningMail` è finito nel trait, e il taglio del messaggio di errore (prima riga, 300 caratteri, per non rimandare a schermo la conversazione SMTP con le credenziali) sta ora in un punto solo.

**Verifica.** Cinque test nuovi in `TenantMailerTest`, incluso uno che passa da `SendQueuedMailable` per fissare il contratto con la coda. `php artisan test --compact` → **465 test, 463 passati, 2 saltati**. Pint pulito, PHPStan 0 errori, `lang/it.json` a **887 chiavi**, 0 mancanti.

### 2026-08-31 — Le email non partivano: l'indirizzo nel campo del server

**Sintomo.** Parametri SMTP compilati, nessuna email in arrivo.

**Causa.** Nel campo «Server SMTP» c'era l'indirizzo della casella (`lorenzo.dintino@itlab-group.it`) invece del server del provider. Symfony provava a risolverlo come nome di macchina: dieci job in `failed_jobs`, tutti con `getaddrinfo ... failed`. Coda e worker a posto (`jobs` vuota), configurazione unica responsabile. Il dominio è su Register.it (`MX = mail.register.it`), quindi il server giusto è quello indicato nel pannello del provider, con l'indirizzo email nel campo Nome utente.

**Correzione.** Il campo host non accetta più un indirizzo email, uno schema, una porta o uno spazio, e l'errore dice dove va messo l'indirizzo invece di parlare di DNS. Sotto il campo ora c'è la descrizione di cosa ci va. Due test nuovi in `TenantMailerTest`.

**Da tenere presente.** Quando l'SMTP del portale è rotto, le prenotazioni continuano ad essere accettate e le email finiscono in `failed_jobs` senza che il gestore lo veda: `tenant.mail_error` viene aggiornato solo dal pulsante di prova. Gli amministratori di piattaforma ricevono comunque il riepilogo orario di `jobs:report-failures`. Riportare l'errore di un invio reale sulla scheda del portale resta da fare.

**Verifica.** `php artisan test --compact` → **460 test, 458 passati, 2 saltati**. Pint pulito, PHPStan 0 errori, `lang/it.json` a **884 chiavi**, 0 mancanti.

### 2026-08-31 — Ritorni dal collaudo: il calendario alla fine del mese

**Da dove è saltato fuori.** La suite completa, girata il 31, ha bocciato cinque test che il giorno prima passavano. Non erano test fragili soltanto: sotto c'era un difetto vero del prodotto.

1. **Il calendario pubblico mostrava giorni che non erano mai prenotabili.** La griglia di un mese disegna sempre qualche giorno dei mesi vicini, ma gli orari venivano cercati solo fra il primo e l'ultimo del mese: il 1° settembre si vedeva nella griglia di agosto, grigio e morto. Il 31 agosto — ultimo giorno del mese, con «domani» già nel mese dopo — chi apriva il portale trovava un calendario senza niente di libero. Ora la ricerca copre tutta la griglia disegnata, dalla prima all'ultima casella (`drawnRange()` in `⚡service.blade.php`, stessa correzione nel calendario di spostamento in `⚡booking.blade.php`).
2. **Il report «mese scorso» sbagliava mese negli ultimi giorni.** `subMonth()` su una data del 31 non torna indietro di un mese: il 31 marzo meno un mese è il 3 marzo, e il report del mese scorso finiva per disegnare quello corrente. Ora la data viene normalizzata prima (`startOfMonth()->subMonth()`).
3. **Un test si appoggiava alla stessa aritmetica.** `now()->addMonth()` il 31 agosto dà il 1° ottobre, quindi la prenotazione «del mese dopo» finiva due mesi avanti. Corretto lì e ovunque, con la regola scritta in `.ai/rules/general.md`.

**Nuovi test.** Tre, tutti ancorati a una data precisa invece che a «oggi»: i giorni di settembre disegnati nella griglia di agosto sono prenotabili sul portale e sulla pagina di spostamento, e la finestra del mese scorso resta febbraio anche guardandola dal 31 marzo. Verificati anche al contrario: rimettendo il codice vecchio falliscono.

**Regole registrate.** `startOfMonth` prima di `addMonth`/`subMonth` (generale) e «il calendario copre la griglia, non il mese» (portale).

**Verifica.** `php artisan test --compact` → **458 test, 456 passati, 2 saltati**. `vendor/bin/pint --dirty` pulito, `vendor/bin/phpstan --memory-limit=1G` **0 errori**, `npm run build` ok.

### 2026-08-30 — Giro di controllo su tutta la Fase 6, con occhio alla sicurezza

**Obiettivo.** Rileggere tutto ciò che è stato aggiunto nelle ultime fasi cercando errori veri, con attenzione particolare a permessi, isolamento fra portali e dati personali.

**Trovati e corretti — sei problemi, tutti reali, ciascuno con il suo test.**

1. **Una classe di gruppo aggirava le regole del servizio.** Una sessione già aperta veniva offerta senza controllare preavviso minimo, finestra di prenotazione e chiusure del portale: si poteva prendere posto in una lezione di ieri, o un'ora prima nonostante il servizio chiedesse due giorni di preavviso. `GroupSlotFinder::isStillBookable()` applica ora le stesse regole di tutti, e `CreateBooking` ricontrolla prima di scrivere.
2. **Le chiusure del portale valevano solo per un finder su due.** La logica è passata su `PortalClosure::closedDates()`, letta sia da `SlotFinder` sia da `GroupSlotFinder`: una regola scritta due volte è una regola che prima o poi diverge.
3. **Iniezione di formule nei CSV.** Un cliente che si chiama `=cmd|…` diventava una formula eseguita da Excel all'apertura dell'esportazione. `Csv` antepone ora un apostrofo ai valori che iniziano per `=`, `+`, `-`, `@` o tabulazione: la cella si legge uguale, il foglio non la esegue.
4. **La lista d'attesa si abbandonava al solo caricamento della pagina.** Gli scanner di posta e le anteprime dei link aprono ogni indirizzo di un messaggio: chi non aveva cliccato niente spariva dalla lista. Ora la pagina chiede conferma e solo il pulsante cancella.
5. **Una data storta nell'agenda restituiva un errore 500.** `showDayView()` passava la stringa a Carbon senza guardarla; ora la valida e rifiuta con 422.
6. **Cambiando lingua durante l'impersonificazione si cambiava la lingua del cliente.** `SetLocale` non scrive più sull'account mentre qualcuno sta al posto di qualcun altro.

**Verificato e trovato a posto.** Isolamento fra portali su agenda, calendario, registro, clienti e lista d'attesa (anche forzando gli id o i filtri); permessi di rotta e di componente su tutte le schermate nuove; `#[Locked]` sugli id usati dalle azioni; scoping dell'operatore su agenda, report ed esportazioni; token non indovinabili per prenotazioni e liste d'attesa; nessun dato personale nel registro; consenso privacy anche sulla lista d'attesa; cancellazione che porta via prenotazioni, risposte, anagrafica e attese.

**Rimasto aperto, da fare in fase di deploy.** L'indirizzo IP registrato è quello che arriva all'applicazione: dietro un proxy o un CDN serve configurare i proxy fidati, altrimenti nel registro finisce sempre l'indirizzo del proxy. Segnato in [Prima del deploy](#prima-del-deploy).

**Verifica.** `php artisan test --compact` → **455 test, 453 passati, 2 saltati** (15 nuovi in `tests/Feature/SecurityAuditTest.php`, scritti prima delle correzioni: tutti fallivano). `vendor/bin/pint --dirty` pulito, `vendor/bin/phpstan --memory-limit=1G` **0 errori**, `npm run build` ok. `lang/it.json` a **880 chiavi**, 0 mancanti e 0 inutilizzate. `migrate:status` senza migrazioni in sospeso, `schedule:list` con tutte e sei le voci pianificate.

**Coda — primo ritorno dal collaudo.** Nelle impostazioni SMTP l'icona dell'occhio della password compariva **sotto** il campo, fuori dal riquadro. Non era l'icona: in una griglia a due colonne i campi di una riga vengono stirati all'altezza del più alto, e la regola `[data-flux-field] { grid }` in `resources/css/app.css` faceva crescere con loro anche l'ultima riga della griglia interna, cioè il contenitore dell'input. L'input manteneva la sua altezza, ma le icone in coda — l'occhio della password, il pulsante copia — sono posizionate rispetto al contenitore, e finivano più in basso. Aggiunto `content-start`: le righe del campo non si stirano più. Vale per tutti i campi con icona in coda, non solo per quello della password.

**Aperto.** Il collaudo manuale in browser resta da fare: i test coprono il comportamento, non l'aspetto.

### 2026-08-30 — Fase 6.8: gli avvisi sui job falliti

**Obiettivo.** Una email fallita finiva in `failed_jobs` e non lo sapeva nessuno: il portale credeva di aver scritto al cliente e non era vero.

**Fatto.**

- Listener su `JobFailed`: ogni fallimento diventa una riga `job.failed` nel registro, con il nome dell'operazione, la coda e la **prima riga** dell'errore. Quando il job porta con sé una prenotazione o una richiesta in lista d'attesa, la riga sa anche **di quale portale** si trattava.
- `jobs:report-failures` ogni ora: se nell'ultima ora è fallito qualcosa, manda **un solo riepilogo** agli amministratori attivi, raggruppato per portale, e lo annota nel registro.
- Pagina **Coda** nel pannello admin: gli ultimi cento fallimenti con motivo leggibile, *Riprova* singola, *Riprova tutte* e rimozione definitiva. In testa, quanti ne sono falliti nelle ultime 24 ore.

**Decisioni.**

- **Un riepilogo l'ora, non un messaggio per errore**: un portale con credenziali SMTP sbagliate fallisce *ogni* email che prova a mandare, e un avviso per fallimento seppellirebbe l'unica riga che conta.
- **L'avviso non passa mai dal mailer di un portale**: se è proprio quel server ad essere rotto, scrivere di lui usando lui non funzionerebbe. E non è in coda, perché è la coda ad avere problemi.
- **Nel registro solo la prima riga dell'errore**: lo stack trace intero è già nel file di log e nel database annegherebbe tutto il resto.
- **Il portale si ricava dal job**, provando a deserializzarlo dentro un `try`: un job che parlava di una prenotazione poi cancellata semplicemente non dice niente, e la riga resta senza portale invece di far fallire il listener.

**Verifica.** `php artisan test --compact` → **440 test, 438 passati, 2 saltati** (7 nuovi in `tests/Feature/FailedJobAlertTest.php`: riga nel registro, portale ricavato dal job, payload illeggibile che non rompe niente, riepilogo orario agli amministratori, ora tranquilla che non manda nulla, pagina Coda con rimozione, manager escluso). `vendor/bin/pint --dirty` pulito, `vendor/bin/phpstan --memory-limit=1G` **0 errori**, `npm run build` ok. `lang/it.json` a **876 chiavi**.

**Aperto.** **Con questo la Fase 6 è chiusa**: esportazioni e report, servizi di gruppo, riordino, avatar, lista d'attesa, impersonificazione, registro e avvisi sulla coda. Restano i punti di [6.9](#69-rimandati-da-riprendere-dopo).

### 2026-08-30 — Fase 6.6: l'impersonificazione

**Obiettivo.** Assistere un cliente vedendo quello che vede lui, senza che nessuno si scambi password. Rimandata a lungo perché senza registro non era accettabile: ora il registro c'è.

**Fatto.**

- `App\Services\Impersonation`: `start()` mette in sessione l'amministratore e la scadenza, poi autentica il manager; `stop()` restituisce la sessione; `stopIfExpired()` la lascia cadere da sola.
- Pulsante **Entra come questo manager** nel dettaglio portale, con sotto la riga che dice per quanto dura e che tutto finisce nel registro.
- **Avviso giallo su ogni schermata** del back-office, con l'ora in cui finisce e il collegamento *Torna al mio account*.
- Middleware `StopExpiredImpersonation` nel gruppo web: la sessione vive nel browser, e l'unico momento in cui possiamo spegnerla è quando quel browser torna.
- Tre righe nuove nel registro — iniziata, terminata, scaduta — e soprattutto: **ogni riga scritta durante l'impersonificazione porta `impersonated_by`** con il nome dell'amministratore.

**Decisioni.**

- **Trenta minuti**, non una sessione permanente. Un'assistenza dura una telefonata.
- **Mai su un altro amministratore, mai annidata**: entrambe renderebbero il registro impossibile da rileggere.
- **Mai su un account sospeso** o senza portale.
- **Si può anche scrivere**, non è sola lettura: assistere significa spesso sistemare al posto del cliente. Ciò che rende la cosa accettabile è che il registro dica sempre chi c'era davvero dietro.
- **Attribuzione**: i campi di dominio come `cancelled_by_id` restano del manager — è l'account che ha agito — mentre il registro porta il nome dell'amministratore. Riscrivere i campi di dominio avrebbe significato mentire su chi risulta responsabile verso il cliente finale.
- **La via d'uscita è una rotta**, non un'azione di componente: l'avviso deve funzionare da qualunque schermata, comprese quelle dove l'amministratore non avrebbe motivo di essere.

**Problemi e soluzioni.** Un test dava per scontato che il componente admin rifiutasse un manager: le schermate admin sono chiuse **dalla rotta** (`role:admin`), non dal componente, e in un test Livewire il middleware non gira. Il test ora bussa alla porta vera, cioè l'URL.

**Verifica.** `php artisan test --compact` → **433 test, 431 passati, 2 saltati** (9 nuovi in `tests/Feature/ImpersonationTest.php`: ingresso con riga nel registro, avviso su ogni schermata, uscita che restituisce l'account, azioni che portano il nome dell'admin, scadenza automatica, niente admin su admin, niente annidamento, manager senza accesso, account sospeso). `vendor/bin/pint --dirty` pulito, `vendor/bin/phpstan --memory-limit=1G` **0 errori**, `npm run build` ok. `lang/it.json` a **855 chiavi**.

**Aperto.** Resta **6.8 avviso sui job email falliti**, ultimo punto della Fase 6.

### 2026-08-30 — Fase 6.7: il registro delle azioni

**Obiettivo.** Sapere chi ha fatto cosa. È anche il presupposto dell'impersonificazione (6.6): senza registro non sarebbe accettabile.

**Fatto.**

- `activity_logs`: portale, attore (id **più** nome e ruolo copiati), azione in forma `soggetto.verbo`, soggetto polimorfo con una etichetta leggibile, contesto JSON, indirizzo IP, e un solo `created_at` — una riga scritta una volta non ha niente da aggiornare.
- `App\Support\Activity::log()` chiamato dalle azioni di dominio: prenotazioni create, confermate, annullate, spostate, esiti, cancellazione dei dati di un cliente, chiusure del portale, servizi e piani creati/modificati/eliminati, impostazioni del portale, eliminazioni di staff e account.
- Accessi: un listener su `Login`, `Logout` e `Failed`.
- Pagina **Registro**: un solo componente montato su due rotte — `/admin/activity` per l'admin (tutti i portali, con filtro portale) e `/app/activity` per il manager (solo il suo). Filtri per tipo, persona, intervallo di date e ricerca libera.
- `activity:prune` ogni lunedì notte tiene un anno di storico.

**Decisioni.**

- **Il registro non è un secondo archivio di dati personali.** Nel contesto vanno riferimenti e numeri; l'etichetta di una prenotazione è il suo `uid`, non il nome del cliente. Altrimenti la cancellazione dei dati (5.1) svuoterebbe una tabella mentre un'altra continua a raccontare tutto.
- **Un'eccezione voluta**: `auth.failed` conserva l'email digitata. Senza, la riga direbbe che qualcuno, da qualche parte, ha sbagliato password — informazione su cui non si può fare niente.
- **Nome e ruolo dell'attore sono copiati**: eliminare un account non deve trasformare la sua storia in righe vuote.
- **Un componente, due rotte**: la differenza fra admin e manager è il perimetro, non la schermata.
- **L'operatore non ha registro**: la sua agenda sì, il chi-ha-fatto-cosa dell'attività no.

**Problemi e soluzioni.**

- Ogni accesso finiva **due volte** nel registro: Laravel auto-registra i listener in `app/Listeners`, e io lo avevo anche dichiarato a mano nel provider. Tolta la dichiarazione.
- L'attore veniva letto solo dalla sessione, quindi un annullamento fatto da un'azione chiamata direttamente risultava del «sistema». Ora le azioni che girano anche senza sessione passano l'attore esplicito.
- PHPStan sul `?->` a sinistra di `??`: in PHP il null-coalescing già copre l'accesso su null, quindi il nullsafe lì è ridondante. Corretto, non silenziato.
- Due test dell'agenda si sono rotti **per via della data**: scritti quando «domani» cadeva comodo, oggi 30 agosto quel domani finisce anche nella griglia di settembre e nella settimana successiva. Ora fissano i propri giorni invece di appoggiarsi al calendario reale.

**Verifica.** `php artisan test --compact` → **424 test, 422 passati, 2 saltati** (11 nuovi in `tests/Feature/ActivityLogTest.php`: riga alla prenotazione, nessun dato personale nel contesto, distinzione cliente/portale, accessi riusciti e falliti, modifica di un servizio con l'autore, perimetro del manager, vista completa e filtro portale per l'admin, filtri per tipo/persona/data, operatore escluso, potatura, storia leggibile dopo l'eliminazione dell'account). `vendor/bin/pint --dirty` pulito, `vendor/bin/phpstan --memory-limit=1G` **0 errori**, `npm run build` ok. `lang/it.json` a **847 chiavi**.

**Aperto.** Regola registrata in `.ai/rules/support.md`. Ora si può fare **6.6 impersonificazione**, che senza registro non stava in piedi.

### 2026-08-28 — Fase 6.5: la lista d'attesa

**Obiettivo.** Un cliente che non trova posto oggi se ne va e non torna. Ora lascia un periodo e viene richiamato quando qualcosa si libera.

**Fatto.**

- `waiting_list_entries`: servizio, recapiti, **periodo voluto** (dal…al, estremi compresi), consenso privacy con il documento accettato, e un `uid` non indovinabile per il link che toglie dalla lista.
- Modulo sulla pagina pubblica sotto il calendario — *«Non trovi un orario che ti va bene?»* — con date precompilate dal mese a schermo, consenso obbligatorio e lo stesso rate limit della prenotazione.
- `NotifyWaitingList`, chiamata da **annullamento** e da **spostamento** (l'ora lasciata libera vale quanto una disdetta): scrive a tutti quelli il cui periodo copre quel giorno.
- Email `WaitingListOpeningMail` con l'orario, il pulsante che apre la pagina già sul giorno giusto, la frase che dice chiaramente che il posto non è riservato, e il link per uscire dalla lista.
- Pagina **Lista d'attesa** nel back-office (solo manager): chi aspetta, per cosa, in che periodo, quando è stato avvisato l'ultima volta, e rimozione.
- `waiting-list:prune` ogni notte alle 03:15 cancella le richieste il cui periodo è passato da più di una settimana.

**Decisioni.**

- **Avviso a tutti, chi prenota per primo si prende il posto** (scelta dell'utente). Riservare il posto al primo della lista lo terrebbe vuoto per ore ogni volta che quella persona non legge l'email — l'opposto di ciò che serve.
- **Dodici ore di silenzio** fra due avvisi alla stessa persona (`WaitingListEntry::QUIET_HOURS`): una giornata di disdette non deve diventare una giornata di email.
- **Una richiesta per servizio ed email**: rifarla sposta le date invece di mettersi in coda due volte.
- **Il link toglie dalla lista al primo clic**, senza conferma: arriva da una email che ha ricevuto solo quella persona, e chiedere due volte lascia iscritto chi voleva uscire.
- **È dato personale come il resto**: consenso all'iscrizione, cancellazione insieme al cliente (5.1), potatura automatica quando il periodo è passato.

**Verifica.** `php artisan test --compact` → **413 test, 411 passati, 2 saltati** (11 nuovi in `tests/Feature/WaitingListTest.php`: iscrizione, consenso obbligatorio, seconda richiesta che sposta le date, avviso a chi copre quel giorno e non agli altri, silenzio di dodici ore, link che cancella, entry di un altro portale irraggiungibile, gestione dal back-office, operatore escluso, potatura, cancellazione del cliente che porta via anche l'attesa). `vendor/bin/pint --dirty` pulito, `vendor/bin/phpstan --memory-limit=1G` **0 errori**, `npm run build` ok. `lang/it.json` a **822 chiavi**.

**Aperto.** Per i servizi di gruppo la lista si iscrive al servizio, non alla singola classe piena: chi aspetta viene avvisato quando si libera un posto in **qualunque** sessione dentro il suo periodo. Prossimo punto: **6.6 impersonificazione**, che dipende da 6.7 — quindi si fa prima il registro.

### 2026-08-28 — Fase 6.4: gli avatar degli operatori

**Obiettivo.** `users.avatar_path` esisteva dall'inizio e non la usava nessuno: sulla pagina pubblica il cliente sceglie una persona vedendo due iniziali.

**Fatto.** Componente `<x-user-avatar>` che mostra la foto se c'è e le iniziali colorate se no, e che ha sostituito **ogni** `flux:avatar` sparso per le schermate — pagina pubblica, staff, servizi, portali admin, elenco account. Caricamento da due punti: il **profilo** (ognuno la propria) e la **scheda staff** (il manager per i suoi operatori, perché metà di loro non entrerà mai nel gestionale). Stesse regole del logo: JPG, PNG o WebP fino a 1 MB, salvataggio immediato alla scelta del file, vecchio file cancellato quando viene sostituito o rimosso.

**Decisioni.** La foto si carica **solo su un operatore già creato**: nella schermata di creazione il campo non compare, perché non ci sarebbe un id a cui legare il file. Niente SVG, per la stessa ragione del logo: servito dal nostro dominio sarebbe uno script eseguibile.

**Verifica.** `php artisan test --compact` → **402 test, 400 passati, 2 saltati** (7 nuovi in `tests/Feature/OperatorAvatarTest.php`: caricamento dal profilo, sostituzione che cancella la vecchia, rimozione, solo immagini, manager che carica per il suo operatore, manager fermato su un estraneo, foto visibile sulla pagina pubblica). `vendor/bin/pint --dirty` pulito, `vendor/bin/phpstan --memory-limit=1G` **0 errori**, `npm run build` ok. `lang/it.json` a **801 chiavi**.

**Aperto.** Prossimo punto: **6.5 lista d'attesa**.

### 2026-08-28 — Fase 6.3: i servizi si trascinano

**Obiettivo.** La colonna `position` c'era dall'inizio e non la muoveva nessuno: l'ordine dei servizi sulla pagina pubblica era quello di creazione.

**Fatto.** Griglia dei servizi con `wire:sort` (che Livewire appoggia su `x-sort` di Alpine, già nel bundle: nessuna dipendenza nuova), card con `wire:sort:item` e metodo `reorder($serviceId, $position)` che riscrive le posizioni in transazione. L'ordine vale subito sulla pagina pubblica, che già leggeva `position`.

**Problemi e soluzioni.** Primo tentativo silenziosamente inefficace: `Collection::splice()` **restituisce ciò che ha rimosso**, non la collezione. Concatenandolo (`->values()->splice(...)`) mi ritrovavo in mano una collezione vuota e il ciclo non aggiornava niente — nessun errore, semplicemente non succedeva nulla. I test lo hanno preso al primo giro; adesso `splice()` è chiamato su una variabile, con un commento che spiega perché.

**Verifica.** `php artisan test --compact` → **395 test, 393 passati, 2 saltati** (3 nuovi: trascinamento che riscrive l'ordine, pagina pubblica che lo rispetta, servizio di un altro portale non trascinabile). `vendor/bin/pint --dirty` pulito, `vendor/bin/phpstan --memory-limit=1G` **0 errori**, `npm run build` ok. `lang/it.json` a **795 chiavi**.

**Aperto.** Prossimo punto: **6.4 avatar degli operatori**.

### 2026-08-28 — Fase 6.2: i servizi di gruppo

**Obiettivo.** Corsi e lezioni: più persone sullo stesso orario, con più operatori a tenerli. È il punto che tocca il cuore del motore.

**Fatto.**

- `services.capacity` (1 = individuale, come prima). Oltre 1 il servizio diventa di gruppo e l'appuntamento non è più fra un cliente e un operatore: è una **sessione** (`service_sessions`) con i suoi posti e il suo staff (`service_session_user`), e ogni prenotazione è un posto su quella sessione (`bookings.service_session_id`).
- `App\Services\GroupSlotFinder`: una sessione viva con posti liberi si offre così com'è; una piena toglie quell'orario dall'elenco; dove non c'è ancora nessuna sessione basta **un** operatore libero per poterla aprire.
- La sessione nasce alla prima prenotazione e prende **tutti** gli operatori liberi in quel momento. Dall'agenda il manager li aggiunge e li toglie, con il controllo che siano davvero liberi e senza poter lasciare la sessione senza nessuno.
- `SlotFinder::busyPeriods()` conta anche le sessioni: chi tiene una lezione è occupato per qualsiasi altro servizio.
- Pagina pubblica: niente scelta della persona per un servizio di gruppo, e accanto a ogni orario i **posti liberi**.

**Decisioni.**

- **Basta un operatore libero** per aprire la classe (scelta dell'utente): con cinque persone assegnate, pretenderle tutte libere avrebbe azzerato gli orari disponibili.
- **Assegnazione automatica, poi modificabile**: alla nascita entrano tutti i liberi, poi il manager corregge.
- **Niente `AgendaLock` per i gruppi.** Qui i due clienti si contendono l'**ultimo posto**, che è una riga già esistente: si blocca quella (`lockForUpdate`). La prima prenotazione in assoluto è serializzata dall'unico `unique(service_id, starts_at)`, e chi perde la corsa rilegge la sessione appena aperta invece di fallire.
- **`bookings.user_id` resta valorizzato** con l'operatore primario della sessione: agenda, email e `.ics` continuano a funzionare senza sapere niente di gruppi. Togliendo quell'operatore dalla sessione, le sue prenotazioni passano a chi resta.
- **La capienza è fotografata sulla sessione**: alzarla sul servizio non aggiunge posti a una classe già aperta.
- **Un posto non si sposta**: si annulla e si riprenota. Spostare un cliente da una classe a un'altra è una funzione diversa, e per ora `RescheduleBooking` rifiuta con 422.

**Verifica.** `php artisan test --compact` → **392 test, 390 passati, 2 saltati** (13 nuovi in `tests/Feature/GroupServiceTest.php`: apertura della sessione con chi è libero, posti che si riempiono, sessione piena che sparisce dagli orari, posto liberato che ritorna, docenti occupati per gli altri servizi, classe senza nessuno libero, un solo docente libero che basta, posto non spostabile, capienza dal form, servizio individuale immutato, aggiunta e rimozione di docenti con passaggio delle prenotazioni, ultimo docente non rimovibile, operatore senza permessi). `vendor/bin/pint --dirty` pulito, `vendor/bin/phpstan --memory-limit=1G` **0 errori**, `npm run build` ok. `lang/it.json` a **794 chiavi**, 0 mancanti e 0 inutilizzate.

**Aperto.** Regola registrata in `.ai/rules/services.md`. Restano da valutare: spostare un posto da una sessione all'altra, e una lista d'attesa quando la classe è piena (→ [6.5](#65-lista-dattesa)). Prossimo punto: **6.3 riordino dei servizi drag & drop**.

### 2026-08-28 — Fase 6.1: esportazioni e report

**Obiettivo.** Primo punto della Fase 6. Portare fuori i dati e dare un quadro di come sta andando il portale.

**Fatto.**

- `App\Support\Csv`: scrive il file **in streaming**, con separatore `;` e BOM UTF-8. Non è un vezzo — con la virgola Excel italiano incolonna tutto in una cella sola e senza BOM mangia gli accenti.
- **Esportazione dell'agenda** che rispetta i filtri attivi, comprese le viste calendario (esporta il periodo mostrato). Colonne: riferimento, data, orari, servizio, operatore, cliente, recapiti, stato, prezzo, note e risposte alle domande.
- **Esportazione dell'anagrafica clienti**, ricerca compresa, con conteggi, assenze, ultima prenotazione e nota interna.
- Pagina **Report** (`/app/reports`): periodo a scelta fra mese corrente, mese scorso, ultimi 90 giorni e anno; quattro numeri in testa (prenotazioni, completate, assenze con tasso, valore completato) e due tabelle, **per servizio** e **per operatore**.

**Decisioni.**

- **Il tasso di assenza si calcola sugli appuntamenti arrivati a scadenza** (completate + assenze), non su tutte le prenotazioni del periodo: contare anche quelle future diluirebbe il numero fino a renderlo inutile.
- **Il valore conta solo le completate** e solo i servizi con un prezzo: è quello che il portale ha davvero erogato, non quello che ha in agenda.
- **Il report lo vede anche l'operatore**, ma solo sul proprio lavoro: stessa regola dell'agenda.
- Un cliente cancellato (5.1) resta cancellato anche nell'esportazione: esce *Cliente cancellato*, non il nome.

**Problemi e soluzioni.** In test una `StreamedResponse` restituita da un metodo Livewire non si legge da `->response`: Livewire la trasforma in un **effetto `download`** con il contenuto in base64. Scritto un helper `downloadedCsv()` che usa `assertFileDownloaded()` e decodifica. Sorpresa minore: `fputcsv` di PHP mette le virgolette a **qualsiasi campo con uno spazio**, quindi le asserzioni sul CSV vanno scritte tenendone conto. PHPStan ha poi giustamente segnalato che `fopen()` può restituire `false`: ora l'export solleva un'eccezione invece di scrivere nel nulla.

**Verifica.** `php artisan test --compact` → **379 test, 377 passati, 2 saltati** (7 nuovi in `tests/Feature/ReportingTest.php`: conteggi e tasso di assenza, divisione per servizio e operatore, cambio periodo, operatore che vede solo il suo, export che rispetta i filtri, cliente cancellato che resta cancellato, export dell'anagrafica). `vendor/bin/pint --dirty` pulito, `vendor/bin/phpstan --memory-limit=1G` **0 errori**, `npm run build` ok. `lang/it.json` a **784 chiavi**, 0 mancanti e 0 inutilizzate.

**Aperto.** Prossimo punto: **6.2 servizi di gruppo**, il più delicato della fase perché tocca il motore degli slot.

### 2026-08-28 — Fase 5.6: le domande personalizzate

**Obiettivo.** Ultimo punto della lista. Il form pubblico chiedeva nome, email, telefono e una nota libera: un dentista vuole sapere «prima visita?», un consulente la partita IVA.

**Fatto.**

- `service_questions`: etichetta, tipo, opzioni, obbligatoria, posizione. Quattro tipi in `App\Enums\QuestionType` — risposta breve, risposta lunga, scelta singola, scelta multipla. Tetto di **10 domande** per servizio.
- `booking_answers`: **etichetta e valore copiati** al momento della prenotazione, con `service_question_id` in `nullOnDelete`.
- Editor dentro il form del servizio: aggiungi, riordina per posizione, elimina; le opzioni si scrivono una per riga e compaiono solo per i tipi che ne hanno bisogno.
- Form pubblico: campi generati dai dati, validazione costruita al volo (obbligatorietà, valore dentro le opzioni, lunghezze per tipo) con i messaggi che chiamano la domanda per nome.
- Le risposte compaiono nel **modale dei dettagli** in agenda e nel riepilogo delle **email**.

**Decisioni.**

- **La risposta porta con sé la domanda.** Riformulare o cancellare una domanda non riscrive lo storico: la prenotazione continua a dire cosa era stato chiesto quel giorno, e la risposta resta anche quando la domanda non c'è più.
- **Scelta multipla salvata come una riga leggibile** (`"Tasse, Altro"`): serve a essere letta in agenda e nelle email, non a essere interrogata. Se un giorno servirà filtrarci sopra, si aggiunge una colonna, non si riscrive la storia.
- **Una risposta vuota non crea riga**: la prenotazione porta ciò che è stato detto, non il silenzio.
- **Le risposte sono dati del cliente**: la cancellazione di 5.1 le porta via insieme al resto.
- **Dieci domande** sono il tetto: oltre, la prenotazione smette di essere una prenotazione e diventa un modulo.

**Problemi e soluzioni.** Livewire **non** chiama i metodi di un Form object dal markup (`wire:click="form.addQuestion"` → *Public method not found*). Aggiunti `addQuestion()` e `removeQuestion()` sulla pagina, che li inoltrano al form. PHPStan ha poi bocciato `tap($relation->findOrFail(...))->update(...)`, perché su una relazione `findOrFail()` può restituire anche una collezione: sostituito con `where('id', …)->firstOrFail()`, che ha un tipo solo.

**Verifica.** `php artisan test --compact` → **372 test, 370 passati, 2 saltati** (11 nuovi in `tests/Feature/ServiceQuestionTest.php`: creazione dei quattro tipi, etichetta obbligatoria, tetto di dieci, domanda obbligatoria che blocca la prenotazione, valore fuori dalle opzioni rifiutato, risposte salvate con l'etichetta del giorno, scelta multipla su una riga, risposta vuota che non lascia righe, domanda cancellata che non riscrive le risposte, risposte visibili in agenda, cancellazione del cliente che le porta via). `vendor/bin/pint --dirty` pulito, `vendor/bin/phpstan --memory-limit=1G` **0 errori**, `npm run build` ok. `lang/it.json` a **763 chiavi**, 0 mancanti e 0 inutilizzate.

**Aperto.** Regola registrata in `.ai/rules/models.md`. **Con questo la lista di priorità della Fase 5 è chiusa**: restano i punti di [5.7](#57-non-prioritarie-ora-ma-sul-tavolo) e l'applicazione vera dei limiti dei piani.

### 2026-08-28 — Fase 5.5: il preavviso per annullare o spostare

**Obiettivo.** Il cliente poteva disdire anche cinque minuti prima dell'appuntamento.

**Fatto.**

- `services.cancellation_notice_hours`, **default 48**, impostabile servizio per servizio (0 = nessun vincolo). Campo nel form con la riga di aiuto, e paragrafo nuovo nella scheda *Come funziona un servizio*.
- `Booking::isCancellable()` spezzato in due domande diverse: quella di prima resta **la regola del portale**, e la nuova `isCancellableByCustomer()` aggiunge la soglia. `customerChangeDeadline()` dice l'istante oltre il quale solo il portale può intervenire.
- Pagina di gestione del cliente: entro la soglia i pulsanti ci sono, con sotto la riga *«Puoi modificarlo da solo fino al …»*; oltre la soglia spariscono e al loro posto compare la spiegazione con **telefono ed email del portale**. Il modale di annullamento non viene nemmeno renderizzato: nessuna finestra per un'azione che la pagina rifiuterebbe.
- La **email di conferma** dice fino a quando il cliente può fare da solo, così la promessa viaggia insieme al link.

**Decisioni.**

- **La soglia vale per il cliente, non per il portale**: manager e operatore annullano e spostano sempre, anche un'ora prima. È il mestiere del banco, non un'eccezione.
- **48 ore di default** e non zero: un servizio nuovo nasce protetto, e chi vuole la libertà totale la sceglie di proposito.
- Il tetto è **720 ore** (30 giorni): oltre non è più un preavviso, è un divieto.

**Problemi e soluzioni.** Il default in migrazione ha colpito di nuovo (`.ai/rules/database.md`, quarta volta): `ServiceFactory` non passava la colonna, quindi i servizi dei test nascevano con `cancellation_notice_hours` null e `setService()` esplodeva assegnando null a una proprietà `int`. Aggiunta esplicitamente a factory e seeder. Sono anche caduti cinque test pubblici che prenotavano per domani: con 48 ore di preavviso quel cliente non poteva più annullare. Corretti mettendo `cancellation_notice_hours => 0` nei portali di quelle suite, che parlano d'altro — la soglia ha la sua.

**Verifica.** `php artisan test --compact` → **361 test, 359 passati, 2 saltati** (8 nuovi in `tests/Feature/CancellationNoticeTest.php`: default a 48, impostazione per servizio, valore negativo rifiutato, annullamento entro la soglia, 403 oltre la soglia su annulla e sposta, pagina che spiega e offre i contatti, portale che annulla comunque, soglia 0 che lascia liberi fino all'ultimo). `vendor/bin/pint --dirty` pulito, `vendor/bin/phpstan --memory-limit=1G` **0 errori**, `npm run build` ok. `lang/it.json` a **747 chiavi**, 0 mancanti e 0 inutilizzate.

**Aperto.** Prossimo e ultimo punto della lista: **5.6 domande personalizzate nel form di prenotazione**.

### 2026-08-28 — Fase 5.4: le chiusure del portale

**Obiettivo.** Ferie e giorni festivi si potevano dichiarare solo **operatore per operatore**: per Natale bisognava passare da tutti.

**Fatto.**

- Tabella `portal_closures`: portale, primo e ultimo giorno (estremi compresi, letti nel fuso del portale) e motivo.
- `SlotFinder::closedDates()` salta quei giorni **prima** di guardare gli orari del singolo: la chiusura vale per tutti gli operatori insieme, e `canBookAt()` la rispetta, quindi non si aggira nemmeno colpendo lo slot direttamente.
- `Actions\Availability\CloseThePortal` rifiuta la chiusura se dentro c'è una prenotazione bloccante, dicendo quante sono (`PeriodHasBookingsException`, la stessa di `BlockTime`).
- Nuova sezione **Chiusure del portale** in Disponibilità, visibile **solo al manager**: elenco delle chiusure future con la durata in giorni, aggiunta di un periodo con motivo, rimozione.

**Decisioni.**

- **Rifiutare invece di avvisare**: una chiusura che ingoia prenotazioni attive lascerebbe l'agenda a contraddirsi. Prima si annullano, poi si chiude — la stessa regola dei blocchi orari.
- **Niente `AgendaLock`**: qui non si sottrae uno slot a una persona, si spengono giornate intere per l'intero portale. Se qualcuno prenota l'ultimo minuto libero mentre il manager salva, la chiusura fallisce e si riprova; nessuna prenotazione può infilarsi dentro una chiusura già scritta.
- **Tre concetti distinti**, e vanno tenuti tali: eccezione di data = una persona un giorno; blocco = una persona una fascia; chiusura = tutta l'attività, giornate intere.
- Le chiusure passate restano nel database ma spariscono dall'elenco: è storico, non roba da gestire.

**Problemi e soluzioni.** PHPStan ha bocciato `days()`: `diffInDays()` in Carbon 3 restituisce un `float`. Sostituito con `daysUntil()->count()`, che conta i giorni compresi entrambi gli estremi e restituisce un intero — anche più diretto da leggere.

**Verifica.** `php artisan test --compact` → **353 test, 351 passati, 2 saltati** (8 nuovi in `tests/Feature/PortalClosureTest.php`: giorno azzerato per tutti gli operatori, chiusura di un altro portale ininfluente, slot non prenotabile nemmeno colpendolo diretto, creazione di un periodo lungo, rifiuto con prenotazioni dentro, ultimo giorno prima del primo, riapertura, operatore senza permessi). `vendor/bin/pint --dirty` pulito, `vendor/bin/phpstan --memory-limit=1G` **0 errori**, `npm run build` ok. `lang/it.json` a **737 chiavi**, 0 mancanti e 0 inutilizzate.

**Aperto.** Regola registrata in `.ai/rules/models.md`. In Backlog è finita anche la discussione sulla **cifratura** dei dati dei clienti, valutata oggi e rimandata: cifrare tutto romperebbe ricerca, unicità dell'email e collegamento con l'anagrafica; cifrare le sole note costa poco e si farà. Prossimo punto: **5.5 preavviso per annullare o spostare**.

### 2026-08-28 — Fase 5.3: l'anagrafica clienti

**Obiettivo.** Il cliente era tre stringhe copiate su ogni prenotazione: niente storico, niente «questo è già venuto cinque volte, due non si è presentato».

**Fatto.**

- Tabella `customers` per portale: nome, email, telefono, fuso, lingua e una **nota interna** che scrive il portale. Chiave `unique(tenant_id, email)` con l'email normalizzata (trim + minuscolo).
- `bookings.customer_id` con `nullOnDelete`, e una **migrazione di backfill** che ha costruito l'anagrafica dalle prenotazioni già presenti: raggruppate per portale ed email, con nome e telefono presi dalla prenotazione più recente.
- `CreateBooking::resolveCustomer()` collega o crea il cliente dentro la stessa transazione della prenotazione.
- Pagina **Clienti** (`/app/customers`, voce in sidebar): ricerca su nome, email e telefono, conteggio prenotazioni, badge delle assenze, quante ne ha in arrivo, data dell'ultima. Aprendo la scheda: storico completo con servizio, operatore e stato, più la nota interna modificabile.

**Decisioni.**

- **La prenotazione resta una fotografia.** `customer_name` e `customer_email` continuano a vivere sulla riga della prenotazione: sono ciò che il cliente ha dichiarato quel giorno. Se cambia cognome, le prenotazioni vecchie non si riscrivono da sole.
- **Anagrafiche separate per portale**: la stessa email in due portali è due clienti distinti, perché i due portali sono titolari del trattamento diversi.
- **Il nome segue l'ultima prenotazione**, la nota interna no: quella è del portale e nessun modulo pubblico la tocca.
- **Solo il manager** vede l'elenco. L'operatore continua a vedere i clienti attraverso la propria agenda: l'anagrafica di tutta l'attività non è roba sua.
- **La cancellazione dei dati elimina anche la riga anagrafica** (5.1): tenere nome e recapiti di chi ha chiesto l'oblio vanificherebbe l'operazione.

**Verifica.** `php artisan test --compact` → **345 test, 343 passati, 2 saltati** (8 nuovi in `tests/Feature/CustomerDirectoryTest.php`: due prenotazioni stessa email = un cliente anche con maiuscole diverse, aggiornamento di nome e telefono senza toccare la nota, stessa email in un altro portale = altro cliente, elenco e scheda con storico, nota interna, cliente di un altro portale irraggiungibile, operatore fuori, cancellazione che porta via anche l'anagrafica). `vendor/bin/pint --dirty` pulito, `vendor/bin/phpstan --memory-limit=1G` **0 errori**, `npm run build` ok. `lang/it.json` a **723 chiavi**, 0 mancanti e 0 inutilizzate.

**Aperto.** Regola registrata in `.ai/rules/models.md`. Da valutare più avanti: unire due schede quando lo stesso cliente ha usato due email diverse. Prossimo punto: **5.4 chiusure del portale**.

### 2026-08-28 — Fase 5.2: i piani esistono, i limiti non mordono

**Obiettivo.** Definire e vedere i piani commerciali, senza applicarli. Applicare limiti non ancora decisi significherebbe scrivere due volte i controlli.

**Fatto.**

- Tabella `plans`: nome, descrizione, prezzo mensile indicativo, e i limiti come colonne — `max_services`, `max_operators`, `max_bookings_per_month`, `allows_custom_smtp`. **Null su un limite significa «senza tetto»**.
- `tenants.plan_id` nullable con `nullOnDelete`: un piano eliminato non porta via il portale.
- Pagina **Piani** nel pannello admin (`/admin/plans`): elenco con il numero di portali su ciascun piano, creazione e modifica, *Ritira* per toglierlo dal listino senza toccare chi ci sta sopra, eliminazione solo se non lo usa nessuno.
- Il piano si assegna dal form del portale (admin); il dettaglio portale mostra piano e limiti **in sola lettura**, con la riga che dice a chiare lettere che non bloccano niente.
- Seed con tre piani di partenza — Base 19 €, Pro 39 €, Studio 79 € senza limiti — e il portale demo su *Studio*.

**Decisioni.**

- **Nessun controllo, per ora**: un portale oltre il limite continua a funzionare. Il test `the plan limits block nothing yet` fissa questa scelta e sarà il primo a cambiare quando i controlli arriveranno.
- **Ritirare invece di eliminare**: un piano con portali sopra non si cancella (`PlanPolicy::delete`), altrimenti sparirebbe da sotto i piedi di chi lo paga.
- Il prezzo è **indicativo e visibile al solo admin**: la fatturazione è un lavoro a parte e non legge questa colonna.

**Problemi e soluzioni.** Per verificare il seeder ho lanciato `migrate:fresh --seed`, che ha **azzerato il database di sviluppo**: le prenotazioni di prova create a mano nella sessione non ci sono più, al loro posto ci sono di nuovo quelle del seed. Per le prossime verifiche del seeder conviene una copia del database, non quella di lavoro.

**Verifica.** `php artisan test --compact` → **337 test, 335 passati, 2 saltati** (8 nuovi in `tests/Feature/PlanManagementTest.php`: creazione con limiti, limite vuoto = illimitato, validazione, ritiro con portali sopra ed eliminazione rifiutata, eliminazione di un piano libero, assegnazione a un portale, **i limiti non bloccano**, manager fuori dalla pagina). `vendor/bin/pint --dirty` pulito, `vendor/bin/phpstan --memory-limit=1G` **0 errori**, `npm run build` ok. `lang/it.json` a **710 chiavi**, 0 mancanti e 0 inutilizzate.

**Aperto.** Rimandato al giro dei controlli: blocchi alla creazione, avvisi di soglia, conteggio delle prenotazioni del mese, sospensione a scadenza, fatturazione. Prossimo punto: **5.3 anagrafica clienti**.

### 2026-08-28 — Fase 5.1: privacy, consenso e cancellazione dei dati

**Obiettivo.** Primo punto della Fase 5. Il form pubblico raccoglieva dati personali senza informativa, senza consenso e senza una durata di conservazione: il buco che espone di più l'utente nel momento in cui inizia a vendere.

**Fatto.**

- **Portale**: `privacy_policy_url`, `terms_url`, `data_controller`, `data_retention_days`. Nuova sezione *Privacy e termini* nelle impostazioni, con un avviso quando l'informativa non è collegata.
- **Consenso**: spunta obbligatoria nel form di prenotazione, con i link che si aprono in una scheda nuova. Senza spunta la prenotazione non parte (`accepted`).
- **Prova del consenso** sulla prenotazione: `privacy_accepted_at` e `privacy_accepted_url`, cioè *quale* documento è stato accettato. Un portale che cambia informativa domani non riscrive i consensi di ieri.
- **Link** a informativa e termini nel piè di pagina pubblico e nel piede di tutte le email.
- **Conservazione**: `bookings:anonymise-old`, pianificato alle 03:30, svuota nome, email, telefono e note delle prenotazioni più vecchie della soglia scelta dal portale. Null = nessuna scadenza.
- **Cancellazione su richiesta**: voce *Cancella i dati del cliente* nel menu dell'agenda, riservata al manager, che passa da `Actions\Bookings\AnonymiseCustomer` e ripulisce **tutte** le prenotazioni di quella email nel portale.

**Decisioni.**

- **La riga della prenotazione non si cancella mai**: sparisce la persona, resta l'appuntamento. Cancellare le righe farebbe mentire storico, conteggi e fatturato.
- **Un cliente con prenotazioni future non si cancella**: prima si annullano. Altrimenti resterebbe un appuntamento che nessuno può più contattare.
- **URL e non testi** per informativa e termini: i documenti li pubblica il portale sul proprio sito, la piattaforma non fornisce testi legali e non se ne assume la responsabilità.
- **Nessuna retention di default**: quanto tenere i dati lo decide ogni portale. Minimo 30 giorni, massimo 10 anni.
- Il consenso viene chiesto **anche se il portale non ha ancora collegato l'informativa**: i dati li trattiamo comunque, e l'avviso nelle impostazioni spinge il manager a rimediare.

**Problemi e soluzioni.** Quattro test pubblici già esistenti hanno smesso di passare appena il consenso è diventato obbligatorio — che è esattamente il segnale giusto. Aggiornati aggiungendo la spunta, non allentando la regola.

**Verifica.** `php artisan test --compact` → **329 test, 327 passati, 2 saltati** (14 nuovi in `tests/Feature/PrivacyTest.php`: prenotazione rifiutata senza consenso, consenso registrato con il documento, link pubblici, salvataggio e validazione delle impostazioni, retention che cancella il vecchio e lascia il recente, portale senza retention, cancellazione manuale su tutte le prenotazioni del cliente, rifiuto con prenotazioni future, operatore 403, più i quattro sul caricamento PDF: caricamento e precedenza sul link, rifiuto di ciò che non è PDF, rimozione con ritorno al link, documento caricato visibile sulla pagina pubblica). `vendor/bin/pint --dirty` pulito, `vendor/bin/phpstan --memory-limit=1G` **0 errori**, `npm run build` ok. `lang/it.json` a **668 chiavi**, 0 mancanti e 0 inutilizzate. `php artisan schedule:list` mostra le tre voci: promemoria, chiusura appuntamenti, anonimizzazione.

**Coda — i documenti si possono anche caricare.** Non tutte le attività hanno un sito su cui pubblicare l'informativa. Ora accanto ai due link ci sono due caricamenti PDF (`privacy_policy_path`, `terms_path`, disco `public`, massimo 4 MB, `mimes:pdf`). `Tenant::privacyPolicyLink()` e `termsLink()` risolvono la precedenza — **file caricato prima, link poi** — e tutto il resto (piè di pagina pubblico, spunta di consenso, piede delle email, `privacy_accepted_url` sulla prenotazione) legge da lì, non più dalla colonna URL.

Solo PDF di proposito: è il formato che si vede uguale a tutti e, a differenza di un HTML o di un SVG, non è eseguibile nel browser una volta servito dal nostro dominio. Rimuovendo il file si ricade sul link, se c'è.

**Aperto.** I campi privacy li imposta il manager; il form di creazione portale dell'admin non li espone ancora. Regola registrata in `.ai/rules/bookings.md`. Prossimo punto: **5.2 piani e limiti**, solo la pagina.

### 2026-08-28 — Cosa manca per vendere: Fase 5 messa a piano

**Obiettivo.** Nessun codice: lettura d'insieme di cosa manca al prodotto per stare davanti a un cliente pagante, e piano di lavoro concordato.

**Fatto.** Aperta la **Fase 5** nel Backlog con sei punti scelti dall'utente, in ordine di esecuzione: privacy e consenso, piani e limiti (solo la pagina), anagrafica clienti, chiusure del portale, preavviso di annullamento, domande personalizzate. Ognuno con schema, decisioni aperte e confini di cosa entra e cosa no. In coda, `5.7`, tutto ciò che è emerso nella stessa lettura e **non** è stato scelto ora — sincronizzazione calendari, videochiamata, servizi di gruppo, lista d'attesa, SMS, report ed esportazioni, sedi e risorse, pagamenti, registro azioni, lavori di produzione — così fra un mese non lo si riscopre da capo.

**Decisioni.**

- **Privacy per prima**: oggi il form pubblico raccoglie dati personali senza informativa né consenso né durata di conservazione. È l'unico punto che espone davvero l'utente nel momento in cui vende.
- **Piani senza controlli**: si definiscono e si vedono, non si applicano. Applicarli senza averli decisi bene significherebbe riscrivere due volte i blocchi.
- **I testi legali li mette ogni portale**, non la piattaforma: noi diamo i campi, i link, la checkbox e il registro del consenso.
- **Preavviso di annullamento**: 48 ore come default, ma per servizio. Il portale resta sempre libero di annullare o spostare: la soglia vale per il cliente.
- **Un punto alla volta**, con il suo giro di test e la sua voce nel Diario. Niente lavori paralleli su file che si toccano.

**Verifica.** Nessuna modifica al codice in questa voce: solo `LOG.md`.

**Aperto.** Si parte da 5.1 quando l'utente dà il via. Le decisioni segnate come *da decidere* dentro ogni punto vanno chiuse all'inizio del punto, non prima.

### 2026-08-28 — Agenda a due viste: tabella e calendario

**Obiettivo.** Due tab nella pagina Prenotazioni: la tabella con i filtri com'era, e una vista a calendario.

**Fatto.**

- Tab come due pulsanti con `#[Url(except: 'table')] $view`: la scelta sta nell'URL, quindi sopravvive al refresh e si può mandare a un collega. Flux free non ha né tabs né calendario, e nessuna dipendenza è stata aggiunta.
- I filtri (cerca, stato, servizio, operatore) sono **condivisi**: la query comune è passata in `filteredQuery()`, usata sia dalla tabella paginata sia dal calendario. Il filtro *In arrivo / Passate / Oggi* compare solo in tabella — nel calendario il periodo lo decide il mese.
- Griglia del mese da `Week::monthMatrix()`, la stessa del calendario pubblico. Ogni cella elenca fino a **tre** prenotazioni (pallino nel colore del servizio, orario, cliente); oltre le tre, un *altre N* apre il modale del giorno. Le annullate sono barrate, oggi è evidenziato.
- Click su una prenotazione → modale con dettagli (servizio, operatore, recapiti, nota) **e le stesse azioni della tabella**: il menu è stato estratto in `partials/booking-actions.blade.php` e incluso nei due punti, così Conferma / Esito / Annulla restano una sola implementazione.
- La finestra interrogata è la **griglia**, non il mese: le celle di coda dei mesi vicini mostrano le loro prenotazioni invece di restare vuote per finta.

**Decisioni.** Vista mensile e non settimanale a fasce orarie: dà il carico del mese a colpo d'occhio e riusa codice già collaudato, mentre la settimana con posizionamento proporzionale e sovrapposizioni è il doppio del lavoro. La struttura resta pronta ad aggiungerla come terza modalità.

`detailsId` e `dayDate` sono `#[Locked]` e `showBooking()` passa da `authorize('view', …)`: un operatore non apre la prenotazione di un collega nemmeno forzando la richiesta.

**Verifica.** `php artisan test --compact` → **313 test, 311 passati, 2 saltati** (12 nuovi: mese disegnato e navigazione, filtri condivisi, scoping dell'operatore, giorno affollato, 403 sui dettagli altrui, passo della settimana, passo del giorno, vista sconosciuta nell'URL, e i quattro sulla chiusura automatica). `vendor/bin/pint --dirty` pulito, `vendor/bin/phpstan --memory-limit=1G` **0 errori**, `npm run build` ok. `lang/it.json` a **636 chiavi**, 0 mancanti e 0 inutilizzate.

**Coda della sessione — mese, settimana, giorno.** Il calendario ha ora tre modalità, e le tab della pagina sono quattro: *Tabella · Mese · Settimana · Giorno*. Tutte e tre leggono dallo stesso ancoraggio `#[Url(as: 'd')] $focus`, quindi passare da una all'altra non fa perdere il punto in cui si stava; frecce avanti/indietro e *Oggi* si adattano al passo della vista (mese, settimana, giorno). Aggiunto un selettore di data nativo (`type="date"`) per saltare dove si vuole: Flux free non ha un date picker.

- **Settimana**: sette colonne, ogni giorno elenca *tutte* le sue prenotazioni (nessun "+N"), con il bordo colorato dal servizio.
- **Giorno**: elenco cronologico con orario di inizio e fine, servizio, operatore, stato e le stesse azioni della tabella. Ci si arriva cliccando il **numero del giorno** nel calendario mensile, dal *altre N* di una cella affollata, oppure scegliendo la data.
- Il modale "giorno affollato" introdotto poco prima è stato **rimosso**: la vista giornaliera fa la stessa cosa meglio.
- `Week::daysOf()` restituisce i sette giorni della settimana di una data, Lun→Dom, accanto a `monthMatrix()`.
- Modale dei dettagli: il badge dello stato finiva **sotto la X** di chiusura. Ora sta su una riga sua sotto il nome, e l'intestazione ha il margine destro che tiene libero l'angolo.

**Coda della sessione — chiusura automatica degli appuntamenti.** Fino a ora un appuntamento passato restava *Confermata* per sempre, se nessuno scriveva l'esito: gli stati *Completata* e *Assente* esistevano solo come voci di menu. Ora `bookings:complete-past` (orario, in `routes/console.php`) porta a **Completata** le prenotazioni **solo `Confirmed`** finite da più di 24 ore (`CompletePastBookings::GRACE_HOURS`).

- Le *In attesa* restano tali: non sono mai state accettate, quindi non sono avvenute.
- Le 24 ore di grazia servono a poter marcare *Assente* a mano prima che parli l'automatismo; dopo, i due esiti restano intercambiabili dal menu, perché `acceptsOutcome()` guarda solo "finito e non annullato".
- Aggiornamento in blocco, niente email e niente lock: lo slot è nel passato e al cliente non serve sapere altro.
- Come per i promemoria, il comando chiede *tutto ciò che è già dovuto*: una esecuzione saltata ritarda il cambio, non lo perde. Regola in `.ai/rules/console.md`.

**Coda della sessione — la guida ai parametri del servizio.** Durata, intervallo, pause, preavviso e finestra di prenotazione sono la parte del prodotto che un cliente nuovo sbaglia per primo. Ora accanto a *Nuovo servizio* e dentro il form c'è un pulsante **? Aiuto** che apre `partials/service-help.blade.php`: una scheda in linguaggio piano, un paragrafo per parametro, con gli esempi numerici reali (fascia 09:00–12:00 con durata 30 e intervallo 15 → 09:00, 09:15 … 11:30; appuntamento 10:00–10:30 con pause 15 e 10 → agenda occupata 09:45–10:40) e un esempio completo di configurazione in fondo.

Le 24 stringhe nuove sono passate da `__()` e tradotte in `lang/it.json` come tutto il resto: la scheda si legge in italiano e in inglese.

**Aperto.** Collaudo manuale delle tre viste. La settimana è a colonne, non a fasce orarie proporzionali: quella resta in Backlog.

### 2026-08-28 — Giro di perlustrazione: cascate, throttle, contrasto

**Obiettivo.** Su richiesta dell'utente: cercare bug e cose da sistemare in tutta l'applicazione, elencarli, decidere insieme e poi correggerli. Scelta finale: tutto.

**Fatto.**

**Cascate di eliminazione.** `bookings` ha `cascadeOnDelete` su `tenant_id`, `service_id` e `user_id`: eliminare un servizio o un account cancellava le prenotazioni future in silenzio. La pagina Staff aveva già la guardia, servizi e account admin no. Ora `Tenant`, `Service` e `User` espongono `upcomingBookingsCount()`; servizi e account rifiutano l'eliminazione finché è > 0, il portale (che l'admin elimina di proposito) avvisa soltanto. Tutti e tre i modali mostrano il numero e disabilitano il pulsante.

**Rate limit sul form pubblico.** `portal.service::book()` non aveva alcun limite: login e 2FA sì, la prenotazione no. Ora due bucket per IP e servizio, 5 al minuto e 20 all'ora, con errore sullo slot invece di un 429.

**Portale sospeso (deciso con l'utente).** La pagina pubblica continua a fare 404, ma il link di gestione del cliente **resta aperto**: deve poter annullare. Lo spostamento invece è vietato (403 su `startReschedule` e `selectSlot`) e un avviso lo dice.

**Contrasto del brand.** `Tenant::brandForeground()` sceglie bianco o quasi-nero dalla luminanza WCAG. Le pagine pubbliche dichiarano `--portal-brand`, `--portal-brand-contrast` e i tre `--color-accent*` di Flux: da qui il bottone primario, il giorno selezionato e l'hover degli slot prendono il colore del portale. Prima il brand si vedeva solo sulle barrette delle card.

**Iniziali invisibili.** `x-portal-logo` coloriva lo sfondo con `var(--portal-brand)`, che nel back-office non esiste: in *Impostazioni portale*, senza logo, restava un quadrato trasparente con testo bianco. Ora il componente legge il portale direttamente.

**Ricerche.** `%` e `_` digitati dall'utente finivano dentro `LIKE` come jolly (cercare `%` restituiva tutto). Nuovo `App\Support\Search::contains()`, usato da agenda, account admin e portali admin.

**Note del cliente.** La riga delle note in agenda ereditava `whitespace-nowrap` dalla tabella Flux: una nota lunga (fino a 1000 caratteri) allargava la pagina. Prima corretta con `whitespace-normal`, poi sostituita del tutto dalla colonna *Note* descritta in coda a questa voce.

**Decisioni.** L'escape delle ricerche usa `!` e non il backslash: `ESCAPE '\\'` non è portabile, MySQL lo mangia dentro il letterale stringa. Il contrasto è calcolato e non configurabile: una seconda scelta di colore per ogni portale è una domanda in più senza guadagno.

**Problemi e soluzioni.**

- Il test `ServiceColorTest` sul colore proprio è diventato falso-negativo: ora il brand compare anche nello stile del logo, quindi `assertDontSee('background-color: #ff00f7')` scattava sul logo invece che sulla card. Asserzioni ancorate al markup della barretta (`top-0 h-1" style="background-color: …`).
- PHPStan ha rifiutato `orWhereRaw()` con SQL costruito da `$grammar->wrap($column)`: vuole `literal-string`. Risolto tipizzando le colonne come `array<int, literal-string>` — sono comunque letterali nel codice, mai valori della richiesta.

**Verifica.** `php artisan test --compact` → **294 test, 292 passati, 2 saltati** (11 nuovi: eliminazioni guardate, rate limit, portale sospeso, ricerca con jolly, contrasto del brand). `vendor/bin/pint --dirty` pulito, `vendor/bin/phpstan --memory-limit=1G` **0 errori**, `npm run build` ok. `lang/it.json` a **599 chiavi**, 0 mancanti e 0 inutilizzate.

**Coda della sessione — quattro pulsanti calendario.** La pagina di conferma aveva Google, Outlook e un unico *"Apple e altri (.ics)"*. Ora sono quattro: Google Calendar, Outlook, **Apple Calendar** e *Altri calendari (.ics)*. Apple e generico servono lo stesso file: la differenza è il `Content-Disposition`. `BookingCalendarController` accetta `?inline=1` e in quel caso risponde `inline` invece di `attachment`, perché iOS passa una risposta `text/calendar` inline direttamente all'app Calendario, mentre un allegato finisce in File e va aperto a mano. Resta comunque una conferma dell'utente: nessun calendario aggiunge un evento senza che lo si tocchi.

**Coda della sessione — il cambio lingua rispondeva 405.** Sintomo: dalla pagina di conferma, cambiando lingua, `MethodNotAllowedHttpException` su `GET /livewire-0ceb9b3f/update`. Causa: `<x-locale-switcher>` costruiva gli href con `request()->fullUrlWithQuery(['lang' => …])`. Durante un update Livewire la richiesta in corso **è** l'endpoint interno di Livewire, quindi dopo una qualunque azione sulla pagina (cambio fuso, *Sposta*) il DOM veniva ripatchato con link a `/livewire-<hash>/update?lang=it`; cliccandoli il browser faceva GET su una rotta che accetta solo POST. Ora la base dell'URL viene da `Livewire::originalUrl()` quando siamo dentro un update, e un handler Alpine ricostruisce comunque l'URL da `window.location` al click, così sopravvivono anche i filtri che `wire:model.url` ha spinto in barra (`?q=`, `?d=`, …).

Il test (`tests/Feature/LocaleSwitcherTest.php`) fa un **vero POST** all'endpoint di update con lo snapshot preso dalla pagina: `Livewire::test()` non serviva, il suo path è finto (`/livewire-unit-test-endpoint/…`) e il bug non si vedeva. Verificato che fallisce rimettendo il vecchio codice.

Nota di metodo: durante quella verifica il test è sembrato passare con il codice rotto — era la **view Blade compilata rimasta in cache**. `php artisan view:clear` prima di fidarsi di un test su un componente Blade appena modificato.

**Coda della sessione — le note del cliente in agenda.** La nota occupava una **seconda riga** sotto la prenotazione, che spezzava la lettura della tabella. Ora è una colonna stretta *Note* in fondo: se la nota c'è, un pulsante con l'icona del fumetto e i primi 18 caratteri; cliccandolo si apre un modale con il testo intero (`whitespace-pre-line`, quindi gli a capo del cliente restano). Riga senza nota, cella vuota. `notesId` è `#[Locked]` e `showNotes()` passa da `authorize('view', …)`: un operatore non legge la nota di un collega.

**Verifica finale della sessione.** `php artisan test --compact` → **300 test, 298 passati, 2 saltati**. `vendor/bin/pint --dirty` pulito, `vendor/bin/phpstan --memory-limit=1G` **0 errori**, `npm run build` ok. `lang/it.json` a **600 chiavi**, 0 mancanti e 0 inutilizzate.

**Aperto.** Regole registrate in `.ai/rules/models.md`, `support.md`, `resources.md` e nel nuovo `portal.md`. Non toccati, restano in Backlog: SMTP di piattaforma, avviso sui job email falliti, avatar operatori, riordino servizi drag & drop.

### 2026-08-28 — Il colore dei servizi segue il brand del portale

**Obiettivo.** L'utente cambia il colore del brand nelle impostazioni portale (verde → fucsia) e la pagina pubblica resta verde. Capire perché e sistemare.

**Fatto.** La barra colorata delle card era `services.color`, una colonna **separata** dal brand: veniva solo *inizializzata* col brand alla creazione del servizio, quindi cambiare il brand dopo non toccava niente (i tre servizi del seed erano fermi sul vecchio `#0f766e`).

Ora `services.color` è nullable e **null significa "segui il brand"**:

- migrazione `2026_08_28_094952_make_services_color_nullable` — colonna nullable senza default, e i colori esistenti azzerati (erano tutti copie automatiche del brand, non scelte deliberate);
- `Service::displayColor()` restituisce `color ?? tenant->brand_color`; tutti i template pubblici e di back-office ora chiamano quello e non più `$service->color`;
- form servizi: switch *"Usa il colore del brand del portale"*, acceso di default; spegnendolo compare il color picker e l'hex diventa obbligatorio;
- eager load `tenant` / `service.tenant` sulle query che alimentano liste di servizi e prenotazioni, perché `displayColor()` legge la relazione.

**Decisioni.** Scartate le altre due strade proposte all'utente: cambiare i colori a mano servizio per servizio (non risolve, si ripresenta al prossimo cambio di brand) e propagare a cascata il nuovo brand sui servizi al salvataggio (sovrascriverebbe anche i colori scelti apposta). Il fallback è l'unico che distingue *"non l'ho mai deciso"* da *"l'ho deciso io"*.

Azzerare i colori esistenti in migrazione è una perdita di dati accettabile qui: finora nessun colore poteva essere una scelta consapevole diversa dal brand del momento. Il `down()` li riscrive col brand del portale.

**Problemi e soluzioni.** I primi test asserivano il colore sulla pagina pubblica e fallivano: `portal.show` mostra solo i servizi con **almeno un operatore prenotabile e attivo**, e i servizi creati nel test non ne avevano. Aggiunto l'operatore nei test invece di allentare l'asserzione.

**Verifica.** `php artisan test --compact` → **283 test, 281 passati, 2 saltati** (5 nuovi in `tests/Feature/ServiceColorTest.php`). `vendor/bin/pint --dirty` pulito, `vendor/bin/phpstan --memory-limit=1G` **0 errori**. `lang/it.json` a **594 chiavi**. Migrazione applicata sul DB locale: i tre servizi del seed hanno `color = NULL` e la pagina pubblica ora è fucsia.

**Coda della sessione — padding delle tabelle.** Flux azzera il padding esterno della prima e dell'ultima colonna (`first:ps-0 last:pe-0`, pensato per tabelle a filo pagina): dentro le card bordate del back-office il testo toccava il bordo. Regola unica in `resources/css/app.css` su `[data-flux-table]` (specificità più alta delle utility di Flux), così vale per tutte e sette le tabelle senza toccare i template. `npm run build` ok.

**Aperto.** Regola registrata in `.ai/rules/models.md`. Resta il collaudo manuale insieme al resto.

### 2026-08-27 — Round robin, logo del portale, esiti degli appuntamenti

**Obiettivo.** Tre voci brevi rimaste dal giro precedente, scelte dall'utente al posto di aprire la Fase 4: *"operatore indifferente"* sulla pagina pubblica, il logo del portale (la colonna esisteva ma non la usava nessuno) e gli stati `completed` / `no_show` che l'enum prevedeva e l'interfaccia no. Il link Jitsi è stato messo da parte su sua indicazione.

**Fatto.**

**Round robin.** Quando un servizio ha più di una persona, la pagina pubblica ora parte senza nessuno scelto e mostra il pulsante *"Operatore indifferente"*. Il calendario in quel caso **unisce** gli slot di tutti: un orario compare se almeno una persona può prenderlo. `CreateBooking` accetta `?User $operator = null` e, quando è null, prova i candidati **in ordine di carico** — meno appuntamenti futuri per primo, `users.id` a parità — passando al successivo se uno perde la corsa sul lock. Con un solo operatore la scelta sparisce e il comportamento resta quello di prima.

**Logo del portale.** `php artisan storage:link`, `Tenant::logoUrl()`, e il componente `<x-portal-logo>` che mostra l'immagine se c'è e le iniziali colorate se no. Caricamento dalle impostazioni portale del manager: JPG, PNG o WebP fino a 1 MB, salvataggio al momento della scelta del file, il vecchio file cancellato quando viene sostituito o rimosso. Niente SVG di proposito: servito dallo stesso dominio sarebbe uno script eseguibile nel contesto del portale.

**Esiti degli appuntamenti.** Menu *"Segna come completato"* / *"Segna come assente"* nell'agenda, tramite `Actions\Bookings\RecordOutcome`, aperto a chi vede la prenotazione. Compare **solo quando l'appuntamento è finito** (`Booking::acceptsOutcome()`): prima non si può dire come è andato. Una prenotazione annullata non ha esito, e un esito sbagliato si corregge scegliendo l'altro. Nessuna email: è contabilità interna, il cliente non ha più niente da sapere. Nota di comportamento: `no_show` **non** è fra gli stati bloccanti, quindi marcare un assente libera lo slot — irrilevante nel passato, ma va saputo.

**Problemi e soluzioni.**

- **`UploadedFile::fake()->image()` non funziona qui:** *"GD extension is not installed."* Questo PHP è una build statica di herd-lite senza cartella `ext`, GD non c'è e non si aggiunge modificando `php.ini`. I test costruiscono i file con `createWithContent()` a partire dai byte di un PNG di un pixel, riempito con NUL fino al peso voluto: `finfo` legge i magic bytes iniziali e le regole `image` e `mimes` passano lo stesso. Registrato in `.ai/rules/tests.md`.

- **Bug trovato di striscio: i pulsanti del back-office che passavano un `id` a un metodo con model binding erano rotti.** `Booking` è associato per `uid`, `Tenant` e `Service` per `slug`. `wire:click="confirm({{ $booking->id }})"` faceva cercare `uid = 3`, non trovava nulla e lanciava 404. Erano così il pulsante **Conferma** dell'agenda, **Modifica** e **Attiva/Sospendi** dei servizi e dei portali: cinque voci di menu che in produzione non avrebbero fatto niente. I test non lo vedevano perché passavano il **modello** al posto dell'identificativo, e Livewire in quel caso lo serializza da solo con la chiave giusta. Corretti i template passando la route key, e aggiunto `tests/Feature/ModelBindingTest.php` che chiama i componenti **come fa il markup**. Regola registrata in `.ai/rules/pages.md`.

**Verifica.** `php artisan test --compact` → **278 test, 276 passati, 2 saltati**. `vendor/bin/pint --dirty` pulito, `vendor/bin/phpstan --memory-limit=1G` **0 errori**, `npm run build` ok. `lang/it.json` a **592 chiavi**.

**Aperto.** Il collaudo manuale di queste tre cose non è stato fatto: l'utente lo farà insieme al resto. Il link automatico per le videochiamate resta in [4.1](#41-link-della-call-generato-automaticamente).

### 2026-08-27 — Fase 3.4: il file .ics e i pulsanti calendario

**Obiettivo.** Far finire l'appuntamento nel calendario del cliente con un clic, e tenercelo aggiornato.

**Fatto.**

- **`App\Support\IcsEvent`** genera il file a mano: il formato è piccolo e una dipendenza non si sarebbe ripagata.
- **`bookings.ics_sequence`**, incrementata a ogni spostamento e a ogni annullamento.
- File **allegato** alle email di conferma, spostamento e annullamento. Non a quella di richiesta ricevuta: finché nessuno ha confermato non c'è niente da mettere in calendario.
- **Rotta di download** `/p/{portale}/b/{token}/calendar.ics`, protetta dallo stesso token della pagina.
- **Tre pulsanti** sulla pagina di conferma: Google Calendar, Outlook, e il file per Apple e desktop.

**Decisioni.**

*UID stabile, sequenza crescente.* Sono le due cose che fanno funzionare il resto. L'UID deriva dal token della prenotazione e non cambia mai, così il calendario riconosce un file successivo come **lo stesso appuntamento**. La sequenza cresce a ogni modifica, ed è ciò che dice al calendario che la nuova versione sostituisce quella che ha già. Senza, chi sposta due volte si ritrova tre eventi.

*Annullamento come `METHOD:CANCEL`.* Il file rimuove l'evento invece di lasciarlo lì con una data vecchia.

*Pulsanti diversi per motivi diversi.* Google e Outlook accettano un URL; Apple e i client desktop no, e per loro l'unica strada è il file. Tre pulsanti perché sono tre meccanismi, non per abbondanza.

**Problemi e soluzioni.**

1. **`SEQUENCE:` usciva vuoto.** Terza volta che la stessa trappola morde: `ics_sequence` ha il default nella migrazione, quindi sull'istanza appena creata era `null`. Un valore nullo lì produce un file **malformato in silenzio**, che alcuni calendari rifiutano e altri leggono male.

   Corretto su tre fronti: valore esplicito in `CreateBooking`, valore esplicito nella factory, e coercizione a intero in `IcsEvent` perché un file rotto non deve mai poter uscire. La regola in `.ai/rules/database.md` è stata rafforzata con l'elenco dei tre casi.

2. **Il tipo di `location()` mentiva.** Dichiarato `?string` ma non poteva mai restituire null, perché il tipo di luogo dice sempre qualcosa. Segnalato da PHPStan, semplificato togliendo la guardia inutile.

**Verifica.**

```
vendor/bin/pint --format agent        → passed
vendor/bin/phpstan --memory-limit=1G  → passed, 0 errori
php artisan test --compact            → 249 test, 247 passati, 2 saltati
```

`CalendarFileTest` è nuovo, 14 test. I quattro che presidiano i dettagli formali, quelli che rompono i calendari senza dare errori:

- ogni riga termina con `CRLF`, mai con un `
` solo
- virgole, punti e virgola e ritorni a capo nei testi vengono **protetti**, così il valore non finisce prima del previsto
- nessuna riga supera i **75 ottetti**, e le continuazioni iniziano con uno spazio
- le lettere accentate non vengono **tagliate a metà** dal ripiegamento

Più: UID invariato dopo uno spostamento, sequenza che cresce, due spostamenti che non lasciano tre eventi, annullamento come `CANCEL`.

Smoke test reale: download con `Content-Type: text/calendar` e nome file corretto, file valido, e i tre pulsanti presenti sulla pagina.

**Nota emersa dallo smoke test.** I pulsanti compaiono solo su prenotazioni **attive e future**: su una passata o annullata il riquadro non c'è. È voluto — non ha senso mettere in calendario ciò che è già avvenuto — ma sul momento sembra un difetto.

**Aperto.** La Fase 3 è completa. Restano le integrazioni della Fase 4 e il collaudo manuale delle sezioni non ancora percorse.

---

### 2026-08-27 — Fase 3.3: i promemoria automatici

**Obiettivo.** Ricordare al cliente l'appuntamento poco prima, per tagliare i mancati arrivi.

**Fatto.**

- **`tenants.reminder_hours_before`** (default 24, vuoto = promemoria spenti per quel portale) e **`bookings.reminder_sent_at`**, unica difesa contro il doppio invio.
- **`bookings:send-reminders`**, pianificato **ogni quarto d'ora** in `routes/console.php` con `withoutOverlapping()`.
- **`Customer\BookingReminderMail`**, stesso layout e stesso mailer del portale delle altre.
- **`schedule:work` aggiunto a `composer run dev`**: prima non c'era, quindi in sviluppo il comando non sarebbe mai partito da solo. Ora i processi sono quattro: server, coda, cron, vite.
- Campo dell'anticipo nelle impostazioni portale, accanto alla copia al manager.
- Uno **spostamento azzera `reminder_sent_at`**: il nuovo orario merita un nuovo promemoria.

**Decisioni.**

*Niente finestra temporale.* Il piano parlava di interrogare "le prenotazioni fra now+H e now+H+15min". Ho scartato quell'approccio: basta un'esecuzione saltata, una coda lenta o un riavvio perché una prenotazione cada fra due giri e non venga ricordata mai più. Il comando chiede invece **tutto ciò che è già dentro la finestra e non è ancora stato ricordato**, il che si auto-ripara: un giro in ritardo semplicemente recupera. C'è un test dedicato a questo scenario.

*Solo le confermate.* Una prenotazione ancora in attesa di approvazione non viene ricordata: dirle "ci vediamo domani" quando nessuno l'ha confermata sarebbe fuorviante. Che una richiesta resti in sospeso fino al giorno prima è un problema del portale, non del cliente, e semmai andrà affrontato separatamente.

*Chi prenota dentro la finestra non viene ricordato.* Prenotare alle 10:00 per le 12:00 con un anticipo di 24 ore avrebbe fatto partire un promemoria immediato di ciò che si è appena fatto. La prenotazione viene comunque marcata, così non viene riconsiderata a ogni giro.

**Problemi e soluzioni.**

**Ho inviato una email vera per sbaglio.** Per la prova su dati reali ho creato una prenotazione con un indirizzo inesistente, dimenticando che il portale demo ha ormai un **SMTP reale** configurato: il promemoria è partito davvero attraverso Register, verso un indirizzo che rimbalzerà. Nessun danno, ma la lezione vale: da qui in poi le prove su dati reali vanno fatte su un portale senza SMTP proprio, oppure rendendo il messaggio senza inviarlo. Il contenuto l'ho poi verificato con `render()`, senza spedire nulla.

**Verifica.**

```
vendor/bin/pint --format agent        → passed
vendor/bin/phpstan --memory-limit=1G  → passed, 0 errori
php artisan test --compact            → 235 test, 233 passati, 2 saltati
php artisan schedule:list             → */15 * * * * bookings:send-reminders
```

`BookingReminderTest` è nuovo, 12 test: invio singolo, nessun doppione eseguendo due volte, prenotazione ancora fuori finestra, prenotazione già iniziata, annullate e in attesa ignorate, portale con promemoria spenti, portale sospeso, anticipo diverso per portale, prenotazione presa dentro la finestra, **giro in ritardo che recupera**, spostamento che fa ripartire il promemoria, e lingua e fuso del cliente rispettati.

Prova su dati reali: prenotazione fra 20 ore con anticipo 24 → *"Sent 1 reminders"*; secondo giro → *"No reminder was due"*.

**Aperto.**

- In produzione serve una voce cron che chiami `schedule:run` ogni minuto. In sviluppo ci pensa `composer run dev`.
- Resta un job fallito nella coda, residuo delle prove SMTP di prima: `php artisan queue:flush` per ripulirlo.
- Manca 3.4: `.ics` e pulsanti calendario.

---

### 2026-08-27 — L'errore di validazione restava a schermo dopo la correzione

**Segnalazione.** Con orari settimanali sovrapposti compare correttamente l'errore. Correggendoli, il salvataggio riesce ma **il messaggio precedente resta visibile**, facendo credere che il problema persista.

**Diagnosi.** Confermato con un test, e il confronto è stato illuminante: **il blocco orario non aveva il difetto, gli orari settimanali sì**.

La differenza sta nel modo in cui viene segnalato l'errore:

| Metodo | Azzera gli errori precedenti |
|---|---|
| `$this->validate([...])` | sì, in automatico |
| `throw ValidationException::withMessages([...])` | **no** |

`saveSchedule()` controlla sovrapposizioni e orari invertiti a mano, perché le chiavi degli errori sono percorsi tipo `schedule.1.ranges.1.starts_at` che una regola di validazione non saprebbe costruire. Lanciando l'eccezione direttamente, però, saltava anche l'azzeramento che `validate()` fa da sé.

**Fatto.** `resetValidation()` all'inizio di `saveSchedule()`.

**Trovato lo stesso difetto altrove.** Un controllo sugli altri punti che usano `addError()` ha mostrato che `sendTest()` nel componente SMTP ha lo stesso problema: valida **solo** il destinatario, quindi un errore riportato in precedenza su `mail_host` sarebbe rimasto anche dopo un tentativo riuscito. Corretto allo stesso modo.

Gli altri usi di `addError()` sono preceduti da un `validate()` che copre lo stesso campo, quindi si azzerano da soli.

**Problemi e soluzioni.** Il primo test scritto per il caso SMTP era **saltato**, perché sembrava servire un server raggiungibile. Un test permanentemente saltato è peso morto: non verifica niente e dà una falsa impressione di copertura. Riscritto puntando a `127.0.0.1:1`, che rifiuta la connessione all'istante: il tentativo fallisce comunque, ma con l'errore del trasporto invece del vecchio messaggio. Gira in meno di un secondo.

**Verifica.**

```
vendor/bin/pint --format agent        → passed
vendor/bin/phpstan --memory-limit=1G  → passed, 0 errori
php artisan test --compact            → 223 test, 221 passati, 2 saltati
```

Tre test nuovi: orari corretti che azzerano l'errore, blocco orario corretto che fa lo stesso (era già a posto, ora è presidiato), e secondo tentativo SMTP che sostituisce il messaggio invece di conservarlo.

**Nota sul collaudo.** L'utente ha fatto un primo giro veloce, da rifare con calma. Questo difetto è emerso da lì: è il quinto trovato a mano e non dai test.

---

### 2026-08-27 — Livello 3 superato, e l'avviso sul mittente

**Esito del collaudo con SMTP reale (Register).** Riuscito su tutta la linea:

- l'email arriva in **posta in arrivo**, non in spam
- **rispondendo**, la risposta arriva all'indirizzo previsto

È la prova che mancava: mittente per portale, autenticazione, `Reply-To` e recapito funzionano insieme su un server vero.

**Richiesta dell'utente.** Scrivere esplicitamente che il mittente deve essere l'indirizzo dell'account SMTP configurato — cioè l'errore in cui era caduto lui stesso, autenticandosi come `lorenzo@digithub.it` e provando a spedire come `lorenzo.dintino@itlab-group.it`.

**Fatto.**

- Descrizione del campo riscritta: *"Chi vede il cliente. Deve essere un indirizzo che l'account SMTP è autorizzato a usare: di solito il nome utente stesso."*
- **Avviso dal vivo** quando il dominio del mittente non coincide con quello del nome utente:

  > ⚠️ **Il mittente appartiene a un altro dominio**
  > Ti autentichi come lorenzo@digithub.it ma spedisci come lorenzo.dintino@itlab-group.it. Quasi tutti i server lo rifiutano, e l'errore che restituiscono spiega poco. Usa lo stesso indirizzo, oppure lascia il campo vuoto.

- Precisato anche il nome utente: *"L'indirizzo email completo, non solo la parte prima della chiocciola."*

**Decisioni.**

*Avviso, non divieto.* Alcuni account sono legittimamente autorizzati a spedire come alias su un altro dominio — Google Workspace lo permette con "Invia messaggio come". Bloccare avrebbe impedito una configurazione valida. Stesso criterio già adottato per gli orari settimanali: si salva e si avvisa.

*Confronto sul dominio, non sull'indirizzo.* `prenotazioni@digithub.it` con nome utente `lorenzo@digithub.it` è quasi sempre legittimo: stesso dominio, stesso account di posta. È il cambio di dominio a essere sospetto.

**Verifica.**

```
vendor/bin/pint --format agent        → passed
vendor/bin/phpstan --memory-limit=1G  → passed, 0 errori
php artisan test --compact            → 220 test, 218 passati, 2 saltati
Traduzioni                            → 572 usate, 572 tradotte
```

Tre test nuovi: dominio diverso avvisa **e salva comunque**, stesso dominio non avvisa, campo vuoto non avvisa.

**Aperto.** Il collaudo delle email è concluso su tutti e tre i livelli. Restano da percorrere le altre sezioni della checklist, e poi 3.3 e 3.4.

---

### 2026-08-27 — Quattro campi email che nessuno capiva

**Segnalazione.** Con l'SMTP finalmente funzionante, l'utente non riusciva a capire a cosa servissero i vari campi email. Parole sue: *"non ci sto capendo niente"*.

**Diagnosi.** Aveva ragione. C'erano **quattro campi email** divisi fra due riquadri diversi, con relazioni implicite e mai dichiarate:

| Campo | Riquadro | Cosa fa |
|---|---|---|
| Nome utente | Invio delle email | credenziale SMTP, non compare in nessuna email |
| Indirizzo mittente | Invio delle email | l'header `From:` |
| Risposte alle notifiche | Dati dell'attività | l'header `Reply-To:` |
| Email di contatto | Dati dell'attività | riserva del precedente |

Due campi che compongono le intestazioni della stessa email stavano in **riquadri diversi**, e uno faceva da riserva all'altro senza che nulla lo dicesse.

Nel controllare è emersa anche un'etichetta che mentiva: **"Email di contatto" non compare da nessuna parte sulla pagina pubblica**. Serve solo come riserva del Reply-To e come riferimento nel pannello admin.

**Fatto.**

- **"Risposte alle notifiche" spostata** dentro "Invio delle email", accanto all'indirizzo mittente. I due campi che formano le intestazioni ora stanno insieme. Rinominata *Indirizzo per le risposte*.
- **Anteprima dal vivo** sotto i campi, che si aggiorna mentre si scrive:

  ```
  Da:         Studio Rossi <lorenzo@digithub.it>
  Rispondi a: info@studiorossi.test
  ```

- Descrizioni riscritte in termini di conseguenza e non di definizione: *"Chi vede il cliente"*, *"Dove arriva la risposta del cliente"*.
- "Email di contatto" ora dichiara il proprio vero ruolo: riserva per l'indirizzo delle risposte.

**Decisioni.**

*Mostrare il risultato invece di spiegare i campi.* Quattro campi che producono due intestazioni non si spiegano campo per campo: si spiegano facendo vedere l'email che ne esce. L'anteprima elimina la domanda invece di rispondervi.

*Il campo si è spostato di riquadro, non di modello.* `reply_to_email` resta una colonna di `tenants`: è cambiato solo dove lo si compila. Anche l'admin lo trova nel dettaglio portale, perché il componente è lo stesso.

**Verifica.**

```
vendor/bin/pint --format agent        → passed
vendor/bin/phpstan --memory-limit=1G  → passed, 0 errori
php artisan test --compact            → 217 test, 215 passati, 2 saltati
Traduzioni                            → 569 usate, 569 tradotte
```

Tre test nuovi: l'anteprima con e senza indirizzi espliciti, la ricaduta sul mittente della piattaforma quando manca l'SMTP, e il salvataggio dell'indirizzo per le risposte dal nuovo riquadro.

**Nota.** L'SMTP reale di Register **funziona**: il collaudo di Livello 3 è riuscito.

**Aperto.** Restano da verificare a mano: che l'email arrivi in posta in arrivo e non in spam, e che rispondendo la risposta finisca dove previsto.

---

### 2026-08-27 — Uno spazio incollato bloccava la configurazione SMTP

**Segnalazione.** Provando un SMTP reale di Register, il pulsante "Invia prova" restituiva:

```
getaddrinfo for authsmtp.securemail.pro failed: Host sconosciuto
```

**Diagnosi.** Il nome del server si risolve benissimo (`81.88.48.66`), verificato dalla stessa macchina. Il valore salvato però era:

```
mail_host = [ authsmtp.securemail.pro]   ← 24 caratteri invece di 23
```

Uno **spazio iniziale**, finito lì incollando dalla pagina del provider. PHP cercava un host che si chiama letteralmente "spazio-authsmtp…" e giustamente non lo trovava.

L'indizio era nel messaggio d'errore stesso, `host "ssl:// authsmtp…"`, ma è il tipo di dettaglio che si legge come impaginazione.

**Il difetto era dell'applicazione.** Un campo che accetta uno spazio incollato e poi fallisce con un errore incomprensibile è un difetto, non una svista dell'utente.

**Fatto.**

- `save()` fa **trim** di server, nome utente, password e indirizzo mittente **prima** di validare.
- Nota nel campo Cifratura: *"La porta 465 usa sempre SSL, a prescindere da questa scelta."*

**Problemi e soluzioni.**

1. **Il trim andava messo prima della validazione, non dopo.** Il primo tentativo puliva i valori già validati: un'email incollata con uno spazio veniva rifiutata dalla regola `email` prima ancora di arrivare al trim. Scoperto dal test, non ragionandoci.

   La causa profonda è che **Livewire non passa dal middleware `TrimStrings`**: un form classico avrebbe ricevuto valori già puliti, un aggiornamento di proprietà Livewire no. È una differenza che vale la pena ricordare per ogni campo dove si incolla qualcosa.

2. **Il menu Cifratura era fuorviante.** Laravel sceglie il protocollo dalla **porta**, non da quel menu:

   ```php
   $scheme = ($config['port'] == 465) ? 'smtps' : 'smtp';
   ```

   Con 465 usa SSL comunque. Il menu resta perché serve sulle altre porte, ma ora lo dichiara.

3. **Terzo problema, nei dati dell'utente e non nel codice**: indirizzo mittente su un dominio diverso da quello autenticato (`lorenzo@digithub.it` come utente, `lorenzo.dintino@itlab-group.it` come mittente). Quasi tutti i server rifiutano di spedire a nome di un dominio che l'account non possiede. Segnalato, non corretto d'ufficio.

**Verifica.**

```
vendor/bin/pint --format agent        → passed
vendor/bin/phpstan --memory-limit=1G  → passed, 0 errori
php artisan test --compact            → 214 test, 212 passati, 2 saltati
Traduzioni                            → 563 usate, 563 tradotte
```

Un test nuovo in `TenantMailerTest`: server, utente, password e mittente incollati con spazi ai bordi vengono salvati puliti.

Ripulito anche il valore già salvato nel portale demo.

**Aperto.** L'invio reale con Register non è ancora riuscito: restano da correggere porta, cifratura e indirizzo mittente nei dati dell'utente.

---

### 2026-08-27 — Allineamento del diario dopo le prime prove manuali

**Obiettivo.** Su richiesta dell'utente: portare `LOG.md` al passo con tutto, e promuovere l'impersonificazione da nota di margine a voce vera del Backlog.

**Fatto.**

- **Impersonificazione** ora è la sezione **4.1b**, con i punti che contano davvero: sessione a scadenza, banner sempre visibile durante l'impersonificazione, registro di chi ha impersonato chi e quando, divieto di annidamento, e la domanda ancora aperta se debba essere in sola lettura. Il registro è annotato come cosa da scrivere **per prima**: è ciò che rende la funzione accettabile rispetto alla privacy, non un'aggiunta successiva.
- **Problemi trovati durante il collaudo** non è più una sezione vuota: contiene i due difetti reali emersi dalle prove dell'utente, entrambi risolti.
- Stato della checklist manuale corretto: non "non ancora percorsa" ma **iniziata e non finita**, con le voci sulle email già verificate spuntate.
- Conteggio dei test allineato a 213, mappa del codice completata con le pagine di errore.

**Osservazione che vale la pena tenere.** Due sessioni di collaudo manuale hanno prodotto due difetti che 213 test automatici non avevano colto. Non perché i test siano scritti male: nessuno dei due era un errore di logica. Uno era un link che portava nel posto sbagliato ma esistente, l'altro una schermata di errore corretta e incomprensibile. Sono esattamente le cose che si vedono solo usando il prodotto.

**Verifica.** Nessuna modifica al codice: solo documentazione.

**Aperto.** Il resto della checklist di collaudo, e poi 3.3 e 3.4.

---

### 2026-08-27 — Deciso: l'admin non entra nell'agenda dei portali

**Decisione dell'utente.** `/app/*` resta chiuso all'amministratore di piattaforma. I dati personali dei clienti di un'attività — nomi, email, telefoni, note — non passano sotto gli occhi di chi gestisce la piattaforma.

Per l'assistenza il giro sarà: farsi lasciare le credenziali dal proprietario e accedere con il suo account.

**Nessuna modifica al codice.** Il comportamento attuale è già quello voluto: il 403 con la spiegazione, sistemato poco fa, è la risposta corretta e ora anche comprensibile.

**Annotato nel Backlog** un dettaglio pratico che va tenuto a mente: il pulsante *"Genera nuova password"* nel dettaglio portale **estromette il manager**, perché invalida la sua. Per assistere serve che sia lui a comunicare le credenziali, non che l'admin se le generi da sé.

Se in futuro il giro dovesse risultare scomodo, l'alternativa standard è l'**impersonificazione** dal pannello admin: accesso temporaneo e tracciato, senza scambio di password. Segnata come possibilità, non come lavoro previsto.

---

### 2026-08-27 — Il 403 dell'admin, e le pagine di errore che non spiegavano nulla

**Segnalazione.** Il pulsante "Apri la tua agenda" restituiva **403 Forbidden** su una pagina bianca, senza spiegazioni.

**Diagnosi.** Riprodotto per ruolo:

| Utente | `/app/bookings` |
|---|---|
| `admin@digithub.test` | **403** |
| `manager@digithub.test` | 200 |
| `giulia@digithub.test` | 200 |

L'utente stava collaudando **collegato come admin**, e `/app/*` è riservato a `role:manager,default`. Il permesso funziona come progettato: l'admin gestisce i portali, non ci lavora dentro.

**Il difetto vero era la comunicazione.** Il progetto non aveva pagine di errore proprie, quindi usava quelle spoglie del framework: un "403 Forbidden" al centro di una pagina bianca, senza dire perché né offrire una via d'uscita. Chiunque ci finisca conclude che il software sia rotto.

**Fatto.** Quattro pagine di errore, con un guscio condiviso:

- **403** — dice che la pagina esiste ma il ruolo non vi ha accesso, mostra **con quale account si è collegati** e offre due vie: dashboard, oppure accedi con un altro account. Se chi la vede è l'admin, aggiunge la spiegazione specifica del suo caso
- **404** — link sbagliato o contenuto rimosso
- **419** — sessione scaduta, ricarica, non hai perso nulla
- **500** — problema registrato, riprova

**Decisioni.**

*Pagine autosufficienti.* Niente `@vite`, niente Flux, niente layout dell'applicazione: solo CSS inline. Una pagina di errore che dipende dal manifest degli asset può fallire a sua volta, e allora al posto della spiegazione arriva uno stack trace. Sono anche adatte al tema chiaro e scuro tramite `prefers-color-scheme`.

*Mostrare l'identità.* Il 403 dice "Sei collegato come Platform Admin (Amministratore)". È l'informazione che mancava: senza, non c'è modo di capire che il problema è **con quale account** stai guardando.

**Verifica.**

```
vendor/bin/pint --format agent        → passed
vendor/bin/phpstan --memory-limit=1G  → passed, 0 errori
php artisan test --compact            → 213 test, 211 passati, 2 saltati
Traduzioni                            → 562 usate, 562 tradotte
```

`ErrorPagesTest` è nuovo, 6 test, fra cui: l'admin vede la spiegazione a lui dedicata, l'operatore che finisce su una schermata da manager vede quella generica e **non** quella dell'admin, e un visitatore anonimo non si vede mai il riquadro "sei collegato come".

Reso davvero dall'applicazione, come admin:

> **403** — Questa pagina non è per il tuo account
> La pagina esiste, ma il tuo ruolo non vi ha accesso.
> L'area di gestione dei portali è di manager e operatori. Come amministratore della piattaforma gestisci i portali dall'area admin.
> *Sei collegato come Platform Admin (Amministratore).*
> [Vai alla dashboard] [Accedi con un altro account]

**Aperto.** Resta una domanda di prodotto, non un difetto: **l'admin dovrebbe poter vedere l'agenda di un portale?** Oggi no, e quando un cliente segnala un problema l'admin non può guardare. Aggiungerlo però significa mostrare al gestore della piattaforma i dati personali dei clienti delle attività, che è una scelta con implicazioni di privacy. Da decidere con l'utente, aggiunta al Backlog.

---

### 2026-08-27 — Il pulsante "Apri la tua agenda" sembrava rotto

**Segnalazione.** Durante il collaudo con Mailpit (Livello 2, funzionante), il pulsante nella email all'operatore sembrava non funzionare, mentre quello del cliente apriva correttamente la prenotazione.

**Diagnosi.** Non era rotto. Il percorso completo, tracciato via HTTP:

```
1) clic da sloggato    → 302 verso /login
2) pagina di login     → 200
3) accesso come Giulia → redirect a /app/bookings ✓
```

L'agenda contiene dati privati e richiede l'accesso, giustamente; il link del cliente invece porta un token nell'URL e non ne ha bisogno. Chi clicca senza sessione attiva vede un form di login **senza alcuna spiegazione**, e conclude che il pulsante non funzioni.

**Il difetto vero era un altro.** Due, in realtà, ed erano di esperienza d'uso, non di funzionamento:

1. Il link portava all'**agenda intera**. Una email che parla di un singolo appuntamento scaricava l'operatore su una lista in cui doveva cercarlo.
2. Nulla avvisava che sarebbe servito l'accesso.

**Fatto.**

- I tre link nelle email all'operatore ora puntano a `route('app.bookings', ['q' => customer_email])`, quindi la lista arriva già filtrata su quella prenotazione.
- Riga aggiunta sotto il pulsante: *"Ti verrà chiesto di accedere, poi arriverai direttamente qui."*

**Decisioni.** Nessun token di accesso diretto per l'agenda: sono dati del portale, l'autenticazione ci vuole. Il filtro usa l'email del cliente, che è comunque già scritta nel corpo dell'email che l'operatore sta leggendo.

**Verifica.**

```
vendor/bin/pint --format agent        → passed
vendor/bin/phpstan --memory-limit=1G  → passed, 0 errori
php artisan test --compact            → 207 test, 205 passati, 2 saltati
Traduzioni                            → 547 usate, 547 tradotte
```

Due test nuovi in `BookingMailTest`: il link contiene l'email del cliente codificata, e l'avviso sull'accesso è presente.

Nel correggere il secondo test è emersa una sottigliezza: `Mailable::render()` usa la lingua **del messaggio**, non quella dell'applicazione. Cercare una stringa inglese in una email destinata a un operatore italiano fallisce sempre. Va fissata la lingua con `->locale('en')` prima di rendere, come già faceva un test precedente.

**Aperto.** Il collaudo manuale prosegue. Il Livello 3, con SMTP reale, non è ancora stato provato dall'utente.

---

### 2026-08-26 — Consolidamento del diario e checklist di collaudo

**Obiettivo.** Su richiesta dell'utente: allineare `LOG.md` a tutto il lavoro fatto, dettagliare i passi 3.3 e 3.4, e mettere nero su bianco che il **collaudo manuale non è ancora stato fatto**.

**Fatto.**

- Nuova sezione **"Cosa fa oggi il prodotto"**: fotografia funzionale per ruolo, così chi apre il file capisce lo stato senza leggere dieci voci di diario.
- Nuova sezione **"Da collaudare a mano"**: circa novanta verifiche divise per ruolo — admin, manager, operatore, cliente — più email e una parte finale su ciò che i test non possono giudicare (testi che suonano male, flussi scomodi, schermate poco chiare). Con uno spazio in fondo per annotare i problemi trovati.
- Avviso in cima allo **Stato del progetto**: i 205 test verdi dicono che il codice fa quello che ho scritto io, non che sia quello che serve.
- **3.3 e 3.4** dettagliati fino al livello di cosa implementare, quali insidie evitare e quali test scrivere. Le insidie annotate sono quelle che si pagano care: la finestra di interrogazione dei promemoria che deve coprire l'intervallo fra due esecuzioni e non un istante, il `reminder_sent_at` da riazzerare dopo uno spostamento, la sequenza `.ics` senza la quale due spostamenti generano tre eventi in calendario, l'escape e il ripiegamento delle righe nel file `.ics`.
- Intestazione della Fase 3 riscritta: diceva ancora "non iniziata" e riportava la vecchia regola sul mittente, superata dall'SMTP per portale.
- Mappa del codice allineata: `Actions/Availability`, `Actions/Tenants`, `Mail/`, `WorkingHours`, `QrCode`, componente `smtp-settings`.

**Decisioni.** La checklist è divisa **per ruolo** e non per funzionalità: si collauda impersonando qualcuno, non spuntando una lista di campi. L'ultima parte esiste perché è quella che nessun test automatico coprirà mai, ed è dove di solito si nascondono i problemi veri.

**Verifica.** Nessuna modifica al codice: solo documentazione. `LOG.md` passa da 858 a 1065 righe.

**Aperto.** Il collaudo vero e proprio, che spetta all'utente.

---

### 2026-08-26 — SMTP per portale: ogni attività scrive davvero ai propri clienti

**Obiettivo.** Su richiesta dell'utente: ogni attività configura il proprio server SMTP, così le email partono dal suo indirizzo e non da quello della piattaforma. Configurabile dal manager per il proprio portale e dall'admin per tutti. Più l'interruttore per la copia al manager.

**Perché migliora il disegno precedente.** Nella sessione prima avevo imposto `From:` su un dominio nostro, perché inviare come `info@studiorossi.it` dai nostri server fallisce SPF e DMARC. Con SMTP proprio quel vincolo **sparisce**: è davvero il server dell'attività, quindi il `From` con il suo indirizzo è legittimo e autenticato. La proposta dell'utente non aggira il problema, lo risolve alla radice.

Le due modalità convivono:

| Portale | Trasporto | `From:` |
|---|---|---|
| SMTP proprio | il suo server | il suo indirizzo |
| nessun SMTP | mailer della piattaforma | indirizzo nostro, nome del portale come display name |

**Fatto.**

- **Nove colonne su `tenants`**: host, porta, utente, password, cifratura, indirizzo mittente, più esito e data dell'ultimo test e l'interruttore `notify_manager_on_booking`.
- **`Tenant::hasCustomMailer()`, `mailerConfig()`, `mailerName()`, `mailFromAddress()`** raccolgono tutta la logica in un posto solo.
- **`BookingMail::send()`** registra il mailer del portale e lo seleziona. Gira **dentro il job in coda**: è l'unico momento in cui il worker sa a quale portale appartiene il messaggio.
- **Componente Livewire `smtp-settings`** riusato da entrambe le schermate — impostazioni portale del manager e dettaglio portale dell'admin. Autorizza con `TenantPolicy::update`, non con il ruolo, quindi vale per tutti e due senza duplicare nulla.
- **`Actions\Tenants\TestMailer`**: invio di prova **sincrono**, esito salvato sul portale e mostrato in pagina.
- **Copia al manager** delle nuove prenotazioni, saltata quando è lui stesso l'operatore assegnato.

**Decisioni.**

*Password mai rimandata al browser.* Cast `encrypted` a riposo, campo vuoto nel form, e un valore vuoto al salvataggio significa "tieni quella che c'è". Cancellare il server cancella anche la password. Un test verifica che la stringa non compaia mai nell'HTML.

*Ricaduta sul mailer della piattaforma.* Un portale appena creato non ha SMTP: continua a inviare tramite la piattaforma invece di restare muto. La schermata dice esplicitamente in quale delle due modalità si trova.

*Test di connessione obbligatorio nei fatti.* Senza, una password sbagliata farebbe fallire le email in silenzio per settimane. Il pulsante invia davvero, cattura l'errore del trasporto e ne salva la prima riga: il resto della conversazione SMTP è rumore e può riecheggiare le credenziali.

**Problemi e soluzioni.**

1. **`Mail::build()` non è intercettato da `Mail::fake()`.** Era la via più diretta per un mailer al volo, ma `MailFake` non implementa `build()`, quindi avrebbe bucato il fake e rotto i 15 test già scritti. Risolto registrando la configurazione in `config('mail.mailers.tenant.{id}')` e usando `Mail::mailer($name)`, che il fake **sì** intercetta. Nessun test da riscrivere.

2. **Il mailer scelto non sopravvive alla coda.** Registrare la config al dispatch non serve: il worker è un altro processo. Per questo l'aggancio sta in `BookingMail::send()`, che Laravel chiama dentro `SendQueuedMailable`.

3. **Di nuovo i default di colonna.** `notify_manager_on_booking` arrivava `null` sulle istanze create dalla factory, e il componente rifiutava di assegnarlo a una proprietà `bool`. È la trappola già registrata in `.ai/rules/database.md`. Corretta due volte: coercizione nel componente e valore esplicito nella factory.

**Verifica.**

```
vendor/bin/pint --format agent        → passed
vendor/bin/phpstan --memory-limit=1G  → passed, 0 errori
php artisan test --compact            → 205 test, 203 passati, 2 saltati
npm run build                         → ok
Traduzioni                            → 546 usate, 546 tradotte
```

`TenantMailerTest` è nuovo, 18 test: rilevamento della configurazione completa e parziale, password cifrata a riposo, mittente con e senza indirizzo esplicito, nome del mailer univoco per portale, registrazione effettiva della config all'invio, copia al manager solo se richiesta e mai duplicata, permessi (operatore no, manager solo il proprio portale, admin tutti), password mai renderizzata, test rifiutato se la configurazione è incompleta.

Prova su dati reali: portale configurato con SMTP finto, `hasCustomMailer` vero, mittente `prenotazioni@studiorossi.test`, password illeggibile nel database, prenotazione che mette **3 job** in coda (cliente, operatore, copia al manager). Il test di connessione ha restituito l'errore atteso:

```
Connection could not be established with host "smtp.studiorossi.test:587":
getaddrinfo for smtp.studiorossi.test failed: Host sconosciuto
```

salvato sul portale e mostrato in pagina. Dati di prova rimossi e portale riportato allo stato iniziale.

**Aperto.**

- Le email di un portale con SMTP rotto finiscono in `failed_jobs` senza che nessuno lo sappia. Il test manuale aiuta, ma servirebbe un avviso quando i job falliscono ripetutamente.
- `APP_KEY` diventa critica: cambiarla rende illeggibili tutte le password SMTP salvate. Da annotare nella procedura di deploy.
- Restano 3.3 (promemoria) e 3.4 (`.ics` e pulsanti calendario).

---

### 2026-08-26 — Fase 3.1 e 3.2: le email di prenotazione

**Obiettivo.** Chiudere il buco più grosso rimasto: il sistema raccoglieva l'email del cliente e non gliela usava mai. Chi chiudeva la scheda di conferma perdeva la prenotazione per sempre.

**Fatto.**

- **Sette mailable** sotto `app/Mail/`, tutte figlie di `BookingMail` che costruisce l'envelope una volta sola. Al cliente: conferma, richiesta ricevuta, annullamento, spostamento. All'operatore: nuova prenotazione, annullamento, spostamento.
- **`App\Services\BookingNotifier`**: unico punto che decide chi riceve cosa. Le azioni lo chiamano, non toccano `Mail::` direttamente.
- **`tenants.reply_to_email`** (ricade su `contact_email`) e campo relativo nelle impostazioni portale.
- **`bookings.customer_locale`**: catturata al momento della prenotazione, così ogni email successiva raggiunge il cliente nella lingua in cui aveva prenotato.
- **Layout email** con il colore del portale, stili inline, coerente con la pagina pubblica.
- `.env` e `.env.example` documentano il passaggio a SMTP in produzione; `MAIL_FROM_ADDRESS` non è più `hello@example.com`.

**Decisioni.**

*Mittente.* `From:` resta su un dominio nostro, con il **nome del portale come display name** e la sua email in `Reply-To`. Il cliente legge "Studio Rossi" e rispondendo scrive all'attività, ma il recapito passa da un dominio che possiamo autenticare. Mettere `info@studiorossi.it` nel `From` avrebbe fatto fallire SPF e DMARC del dominio del cliente: spam o rifiuto.

*Destinatari.* Email separate invece del CC proposto inizialmente. All'operatore serve sapere chi è il cliente, come contattarlo e cosa ha scritto nelle note; al cliente serve il link. Un solo messaggio non può fare bene entrambe le cose, e il CC esporrebbe l'indirizzo dell'operatore. Un test verifica esplicitamente che l'operatore non compaia in copia.

*Invio fuori dal lock.* `AgendaLock` tiene bloccata la riga dell'operatore: nessuno deve aspettare la coda email mentre quel lock è preso. `CreateBooking` notifica dopo la transazione.

**Problemi e soluzioni.**

1. **`x-mail::layout` non risolveva.** Il namespace `mail::` appartiene già al framework, che lo punta alle proprie view. I componenti anonimi ci finivano dentro. Risolto passando a template inheritance (`@extends('mail.layout')` più `@include` per i partial): nessuna collisione, e nessun namespace da registrare.

2. **Il seeder avrebbe inviato email.** Ora chiama `Mail::fake()` prima di creare le prenotazioni demo, così `migrate:fresh --seed` non sporca il log.

3. **PHPStan sul tipo del mailable.** Avevo tipizzato `object $mailable` in `BookingNotifier`: `PendingMail::send()` vuole il contratto. Stretto a `BookingMail`, che è più preciso e più corretto.

**Verifica.**

```
vendor/bin/pint --format agent        → passed
vendor/bin/phpstan --memory-limit=1G  → passed, 0 errori
php artisan test --compact            → 187 test, 185 passati, 2 saltati
npm run build                         → ok
Traduzioni                            → 512 usate, 512 tradotte
```

`BookingMailTest` è nuovo, 15 test: destinatari corretti per ognuno dei sei scenari, operatore mai in copia, `Reply-To` con e senza indirizzo esplicito, portale senza alcun indirizzo, lingua del cliente e dell'operatore, link di gestione presente solo nella email al cliente, recapiti e note presenti solo in quella all'operatore, e tutto messo in coda anziché inviato inline.

Prova end-to-end reale: prenotazione creata via azione, `queue:work` che processa le due email, e ispezione degli header nel log.

```
From:     Studio Rossi <notifiche@digithub.test>
To:       Prova Cliente <prova@example.com>
Reply-To: Studio Rossi <prenotazioni@studiorossi.test>
Subject:  Prenotazione confermata: Prima consulenza
```

Verificato nel corpo: colore del portale presente, link di gestione **solo** nella email al cliente, recapiti e note **solo** in quella all'operatore. Dati di prova rimossi e `reply_to_email` riazzerato al termine.

**Aperto.**

- Nessun **provider SMTP** ancora configurato: in sviluppo si resta su `log`. Prima della produzione servono credenziali e i record SPF/DKIM sul dominio mittente.
- Il **manager non riceve copia** delle nuove prenotazioni se non è lui l'operatore assegnato. Su portali piccoli potrebbe volerla: da valutare come interruttore per portale.
- Restano 3.3 (promemoria) e 3.4 (`.ics` e pulsanti calendario).

---

### 2026-08-26 — Orari settimanali: salva e avvisa (strada A)

**Obiettivo.** Chiudere l'ultimo dei tre modi di togliere disponibilità. Su scelta dell'utente, strada **A**: il salvataggio non viene mai rifiutato, ma segnala le prenotazioni rimaste fuori copertura.

**Fatto.**

- **`App\Services\WorkingHours`** (estratto da `SlotFinder`): risponde a una sola domanda, quando è aperto questo operatore in un dato giorno. Regole settimanali come base, eccezione di data che le sostituisce per quel singolo giorno. In ingresso il fuso del portale, in uscita UTC. Espone `windowsFor()` e `cover()`.
- **`SlotFinder` rifattorizzato** per delegargli tutto ciò che riguarda gli orari. Perde `weeklyRules()`, `exceptionsBetween()`, `windowsFor()` e `localInstant()`, e resta con il proprio mestiere: tagliare le finestre in slot ed escludere ciò che è occupato.
- **`App\Actions\Availability\SaveWeeklySchedule`**: salva sotto `AgendaLock` e **restituisce** le prenotazioni future non più coperte. `uncoveredBookings()` è pubblico, così serve anche al caricamento della pagina.
- **Avviso persistente** nella pagina Disponibilità: callout con il conteggio, le prime cinque prenotazioni scoperte e un pulsante verso l'agenda. Non un toast: il problema resta visibile finché non lo si risolve. Al salvataggio, toast informativo se qualcosa è rimasto fuori.

**Decisioni.**

*Perché qui si avvisa e altrove si rifiuta.* Blocco orario e chiusura di un giorno sono decisioni puntuali su una data precisa: chi le prende sa cosa c'è in agenda quel giorno, e un rifiuto è utile. Gli orari settimanali sono una **configurazione permanente** che vale per tutti i giorni futuri: rifiutare l'intero salvataggio perché una prenotazione fra tre settimane cade fuori renderebbe la schermata inusabile. La differenza è deliberata ed è scritta in `.ai/rules/availability.md`, perché sembra un'incoerenza e non lo è.

*Perché estrarre `WorkingHours`.* La regola "quali orari valgono oggi" serviva ora in due posti. Duplicarla era la strada breve e quella che diverge per prima: bastava aggiungere un tipo di eccezione in futuro e i due controlli avrebbero risposto cose diverse.

*Avviso persistente invece che solo al salvataggio.* Come effetto collaterale emergono anche gli scoperti creati in passato, non solo quelli appena provocati.

**Problemi e soluzioni.** Nessun intoppo. Il refactor di `SlotFinder` era la parte a rischio, coperta dai 55 test già esistenti fra motore, azioni, blocchi ed eccezioni: verdi al primo colpo.

**Verifica.**

```
vendor/bin/pint --format agent        → passed
vendor/bin/phpstan --memory-limit=1G  → passed, 0 errori
php artisan test --compact            → 172 test, 170 passati, 2 saltati
```

Tre test nuovi in `AvailabilityTest`: orari ridotti che salvano e segnalano, orari che coprono tutto e non segnalano nulla, e una prenotazione in un giorno con eccezione propria che **non** viene segnalata perché gli orari settimanali non la governano.

Provato sui dati reali: riducendo gli orari di Giulia a 14:00–18:00 il sistema ha segnalato correttamente 4 prenotazioni scoperte, incluse le tre di seed e una creata a mano durante le prove.

**Aperto.**

- Durante la verifica ho riscritto gli orari settimanali di Giulia con quelli del seeder (Lun–Ven 09:00–13:00 e 14:00–18:00). Se erano stati modificati durante le prove, quelle modifiche non ci sono più.
- Tutti e tre i percorsi che tolgono disponibilità sono ora presidiati. Restano i punti noti: nessuna email, niente round robin, stati `completed`/`no_show` senza interfaccia.

---

### 2026-08-26 — Le eccezioni di calendario non controllavano le prenotazioni

**Obiettivo.** Segnalazione dell'utente durante la prova: il blocco orario avvisa correttamente quando la fascia contiene prenotazioni, l'eccezione di calendario no. Confermato: era un buco reale, lo stesso identificato per i blocchi ma lasciato scoperto sull'altra strada.

**Fatto.**

- **`App\Actions\Availability\SetDateOverride`**, gemella di `BlockTime`: stesso `AgendaLock`, stesso rifiuto con `PeriodHasBookingsException`.
- La pagina Disponibilità ora passa da lì e mostra il conflitto come errore sul campo data, invece di salvare in silenzio.
- Messaggio distinto per i due casi, perché la causa è diversa: *"In quel periodo ci sono N prenotazioni"* per la chiusura totale, *"Ci sono N prenotazioni fuori dai nuovi orari"* per gli orari ridotti.

**Decisioni.**

Il controllo non è lo stesso nei due casi, e non poteva esserlo:

| Caso | Cosa conta come conflitto |
|---|---|
| Giornata chiusa | qualunque prenotazione attiva in quel giorno |
| Orari custom | solo le prenotazioni che finirebbero **fuori** dalla nuova finestra |

Ridurre l'orario da 09:00–17:00 a 09:00–13:00 con un appuntamento alle 10:00 deve passare: la prenotazione è ancora coperta. Rifiutare qualunque modifica sarebbe stato più semplice da scrivere e sbagliato da usare.

Per il confronto si usano `starts_at`/`ends_at` della prenotazione e **non** la finestra con i buffer: i buffer possono legittimamente sbordare oltre l'orario di apertura, esattamente come quando lo slot era stato generato.

**Problemi e soluzioni.** Nessun intoppo tecnico: il pattern era già stabilito da `BlockTime`, si trattava di applicarlo dove mancava. La segnalazione arriva da una prova manuale dell'utente, non dai test — che infatti non coprivano quel percorso.

**Verifica.**

```
vendor/bin/pint --format agent        → passed
vendor/bin/phpstan --memory-limit=1G  → passed, 0 errori
php artisan test --compact            → 169 test, 167 passati, 2 saltati
npm run build                         → ok
```

`DateOverrideTest` è nuovo, 12 test. Fra questi i due confini che contano: chiudere alle 10:15 una prenotazione che finisce alle 10:30 viene **rifiutato**, chiudere esattamente alle 10:30 viene **accettato**.

Provato anche sui dati di seed reali: chiusura rifiutata (3 prenotazioni), orario 14:00–18:00 rifiutato (3 fuori dai nuovi orari), orario 09:00–13:00 accettato.

**Aperto.**

- **Resta scoperto il salvataggio degli orari settimanali** (`saveSchedule`). È il terzo modo di togliere disponibilità e non fa ancora alcun controllo. Trattarlo come gli altri due sarebbe però sbagliato: rifiutare l'intero salvataggio perché una prenotazione fra tre settimane cade fuori dai nuovi orari renderebbe la schermata inutilizzabile. Serve una decisione: avvisare senza bloccare, oppure bloccare solo su una finestra breve.
- Durante la verifica sui dati di seed ho ripulito la tabella `availability_exceptions`: se avevi lasciato lì eccezioni di prova, non ci sono più.

---

### 2026-08-26 — Serializzazione di tutta l'agenda e download del QR

**Obiettivo.** Su richiesta dell'utente: estendere la serializzazione a **ogni** scrittura sull'agenda, non solo alle prenotazioni, e aggiungere un pulsante per scaricare il QR come immagine.

**Fatto.**

- **`App\Services\AgendaLock`** centralizza il pattern: apre la transazione, prende `lockForUpdate()` sulla riga dell'operatore, esegue il callback. Chi scrive nell'agenda non deve più ricordarsi di farlo a mano.
- `CreateBooking` e `RescheduleBooking` rifattorizzate per usarlo.
- **`App\Actions\Availability\BlockTime`**: nuova azione per i blocchi orari. Prende lo stesso lock e **rifiuta** se il periodo contiene prenotazioni attive, lanciando `PeriodHasBookingsException` con il conteggio. La pagina Disponibilità mostra il messaggio invece di salvare.
- **Pulsante "Scarica QR"**: converte l'SVG inline in PNG lato browser (canvas a 4×, fondo bianco esplicito) e lo scarica con un nome che identifica la prenotazione, tipo `studio-rossi-prima-consulenza-<token>.png`. Nessuna rotta e nessuna dipendenza: il PNG lato server avrebbe richiesto `ext-imagick`.

**Problemi e soluzioni.**

**Due buchi trovati rileggendo il percorso di scrittura**, entrambi fuori dalle prenotazioni:

1. **I blocchi orari erano scritti senza lock e senza controllo.** Un operatore poteva bloccare 14–16 mentre un cliente prenotava le 14:30: nessuna eccezione, nessun errore, e l'agenda finiva in uno stato che contraddice sé stessa. Ora il blocco passa da `AgendaLock` e verifica le prenotazioni attive nel periodo prima di scrivere.

2. **Il confine fra blocco e prenotazione.** Il controllo usa disuguaglianze strette, quindi un blocco che *tocca* una prenotazione senza sovrapporsi (prenotazione 10:00–10:30, blocco dalle 10:30) è permesso. C'è un test dedicato, perché è il tipo di cosa che si rompe in silenzio in una rifattorizzazione.

**Cosa resta volutamente senza lock.** `CancelBooking` e `ConfirmBooking`. Il primo **libera** tempo, il secondo non cambia l'occupazione: nel peggiore dei casi un altro scrittore vede lo slot ancora occupato e riprova. Prendere il lock lì aggiungerebbe contesa senza togliere rischi.

**Verifica.**

```
vendor/bin/pint --format agent        → passed
vendor/bin/phpstan --memory-limit=1G  → passed, 0 errori
php artisan test --compact            → 157 test, 155 passati, 2 saltati
npm run build                         → ok
```

`TimeBlockTest` è nuovo: 10 test su blocco di un periodo libero, rifiuto con prenotazioni dentro, nessun residuo dopo un rifiuto, prenotazione annullata che non ostacola, blocco che tocca senza sovrapporsi, conflitto riportato a schermo, manager che blocca per un operatore, estremi invertiti, e slot resi non prenotabili dal blocco.

Smoke test HTTP: la pagina di una prenotazione di seed contiene pulsante, riferimento `x-ref="qr"` e nome file corretto.

**Aperto.**

- Il lock resta verificato per costruzione e non da un test di concorrenza reale: SQLite in memoria non permette due transazioni parallele. Da riverificare col passaggio a MySQL/Postgres, che è il momento in cui il problema diventerebbe visibile.
- Salvare gli orari settimanali o un'eccezione di data può ancora togliere copertura a prenotazioni già prese: non crea sovrapposizioni, ma lascia appuntamenti fuori orario. Da valutare se avvisare.

---

### 2026-08-26 — QR della prenotazione e lock contro le sovrapposizioni

**Obiettivo.** Due richieste emerse provando il prodotto: dare al cliente un modo rapido di conservare il link della prenotazione, e verificare che l'algoritmo non possa produrre appuntamenti sovrapposti.

**Fatto.**

- **Card "Salva la tua prenotazione"** sulla pagina di conferma: link in sola lettura con pulsante di copia (`flux:input copyable`) e **QR code** accanto. Compare solo su prenotazioni ancora attive, sparisce su quelle annullate.
- **`App\Support\QrCode`** genera SVG inline con `bacon/bacon-qr-code`, **già presente** come dipendenza di Fortify per il QR della 2FA: nessun pacchetto aggiunto.
- **Lock pessimistico sull'operatore** in `CreateBooking` e `RescheduleBooking`.

**Decisioni.**

*Cosa contiene il QR.* Codifica il **link di gestione**, non i dati dell'appuntamento. Proposta dell'utente, ed è quella giusta: il token nell'URL è stabile, quindi spostare o annullare la prenotazione non invalida il codice, e chi lo inquadra vede sempre lo stato attuale. Codificare data e ora avrebbe prodotto un QR da rigenerare a ogni modifica e, peggio, copie stampate che mentono.

*Il lock.* Alternativa scartata: indice unico su `(user_id, starts_at)`. Impedirebbe di riprenotare uno slot dopo un annullamento, perché la riga annullata resta.

**Problemi e soluzioni.**

1. **Sovrapposizioni possibili in produzione.** Alla domanda dell'utente ho riletto il percorso di scrittura e ho trovato un buco reale: `CreateBooking` ricontrollava la disponibilità dentro la transazione, ma **senza lock**. Due richieste possono entrambe leggere lo slot come libero e inserire, perché non esiste una riga su cui il database possa andare in conflitto — è un phantom read, permesso anche a REPEATABLE READ. Su SQLite non si manifesta (le scritture sono già serializzate), su MySQL o Postgres sì. Risolto prendendo `lockForUpdate()` sulla riga dell'operatore prima del ricontrollo, in entrambe le azioni. Registrato in `.ai/rules/bookings.md`.

2. **Test sul QR troppo largo.** `assertDontSee('<svg')` falliva sulla prenotazione annullata perché le icone di Flux sono anch'esse SVG. Sostituito con l'etichetta della card, che è ciò che il test voleva davvero verificare.

**Verifica.**

```
vendor/bin/pint --format agent        → passed
vendor/bin/phpstan --memory-limit=1G  → passed, 0 errori
php artisan test --compact            → 146 test, 144 passati, 2 saltati
npm run build                         → ok
```

Tre test nuovi in `PublicBookingTest`: la card mostra link e QR; **link e QR restano identici dopo uno spostamento**; su una prenotazione annullata la card non compare.

Smoke test HTTP: la pagina di una prenotazione di seed risponde 200 e contiene il QR, l'etichetta e l'URL corretto.

**Aperto.**

- Il lock è verificato per costruzione ma non da un test di concorrenza: SQLite in memoria non permette di simulare due transazioni parallele. Da riverificare al passaggio a MySQL/Postgres.
- Restano i punti già noti: nessuna email, niente round robin, stati `completed`/`no_show` senza interfaccia.

---

### 2026-08-26 — Fase 2: motore degli slot e prenotazioni end-to-end

**Obiettivo.** Rendere il portale realmente prenotabile: generare gli slot dagli orari, farli scegliere al visitatore, salvare la prenotazione, e darne il governo a manager e operatori.

**Decisione di partenza.** Gli orari settimanali si interpretano **nel fuso del portale** (opzione A, confermata dall'utente). Il fuso dell'operatore serve solo a mostrargli la propria agenda; quello del visitatore solo a rendere gli orari a schermo. Alternativa scartata: interpretare gli orari nel fuso di ciascun operatore — corretto per team distribuiti, ma costringe il manager a ragionare in fusi diversi mentre compila l'agenda altrui.

**Fatto.**

- **`App\Services\SlotFinder`** — il cuore. Per ogni giorno prende le finestre di apertura (regole settimanali, oppure l'eccezione di quella data se esiste), le converte in UTC usando il fuso del portale, le taglia a passi di `slot_interval_minutes`, e scarta i candidati che escono dall'orario, cadono prima del preavviso minimo, oltre la finestra massima, o si sovrappongono a qualcosa di già occupato.
- **`bookings`** — `starts_at`/`ends_at` più `blocked_starts_at`/`blocked_ends_at`, cioè la finestra con i buffer applicati. È uno **snapshot**: cambiare i buffer di un servizio non sposta le prenotazioni già prese. Salvati anche prezzo e valuta al momento della prenotazione.
- **`time_blocks`** — tabella dedicata ai blocchi ad hoc, distinta da `availability_exceptions`: l'eccezione *sostituisce* gli orari di un giorno, il blocco ne *sottrae* una fascia lasciando aperto il resto.
- **Azioni** in `app/Actions/Bookings/`: `CreateBooking`, `CancelBooking`, `RescheduleBooking`, `ConfirmBooking`. La creazione ricontrolla la disponibilità **dentro la transazione** e lancia `SlotUnavailableException` se qualcuno ha preso lo slot nel frattempo.
- **Pagina pubblica di prenotazione**: calendario mensile costruito a mano (Flux free non ha date-picker), giorni con slot liberi segnati da un pallino, elenco orari nel fuso del visitatore rilevato dal browser, poi form. Tre partial condivisi (`booking-calendar`, `booking-slots`, `booking-form`) riusati anche dallo spostamento.
- **Pagina di gestione** `/p/{portale}/b/{token}`: token di 32 caratteri non indovinabile, mostra conferma o richiesta in attesa, e permette al cliente di annullare o spostare. Lo spostamento aggiorna la prenotazione **in place**, così il link già in mano al cliente continua a funzionare; `original_starts_at` e `rescheduled_at` ricordano che è successo.
- **Agenda nel back-office** `/app/bookings`: filtri per periodo, stato, servizio e operatore, contatore delle richieste in attesa, conferma e annullamento con motivo obbligatorio. Il manager vede tutto il portale, l'operatore solo le proprie.
- **Blocchi orari** nella pagina Disponibilità, e **guardia sulla cancellazione operatori**: chi ha prenotazioni future non si può eliminare, perché la cascade le porterebbe via in silenzio.
- Dashboard aggiornata con i contatori delle prenotazioni; seeder con tre prenotazioni demo.

**Problemi e soluzioni.**

1. **Le eccezioni di calendario non venivano mai trovate.** Le giornate marcate come chiuse continuavano a offrire slot. Causa: `availability_exceptions.date` viene salvata come `Y-m-d H:i:s`, quindi il `whereBetween` con stringhe `Y-m-d` la escludeva sempre per confronto lessicale. Risolto con `whereDate()` su entrambi gli estremi.

2. **Il seeder creava zero prenotazioni, senza errori.** `SlotFinder` restituiva un array vuoto. Causa: `Service::create()` non rilegge la riga, quindi le colonne con default definito in migrazione (`is_active`, `slot_interval_minutes`, …) restavano `null` sull'istanza in memoria; `is_active` null faceva uscire subito il motore. Risolto con `->refresh()` prima dell'uso. È una trappola generale, registrata in `.ai/rules/database.md`.

3. **`Illuminate\Support\CarbonImmutable` non esiste.** Va importato `Carbon\CarbonImmutable`. Sei file corretti.

4. **PHPStan sui generics annidati.** `Eloquent\Collection` ha un template vincolato a `Model`, quindi una collection di collection non è tipizzabile. Invece di forzare l'annotazione ho semplificato: `weeklyRules()` ora raggruppa in array PHP puri. Codice più semplice e zero errori.

5. **Due test di Fase 1 rotti dalla nuova pagina servizio.** Uno segnalava una **regressione vera**: la biografia dell'operatore, che l'utente aveva appena ottenuto il diritto di modificare, era sparita dalla pagina pubblica. Rimessa nel riquadro dell'operatore. L'altro verificava il vecchio pannello "orari abituali", ora superato dal calendario con gli slot reali: test riscritto per verificare gli slot, non gli orari.

**Verifica.**

```
vendor/bin/pint --format agent              → passed
vendor/bin/phpstan --memory-limit=1G        → passed, 0 errori
php artisan test --compact                  → 143 test, 141 passati, 2 saltati
npm run build                               → ok
```

I nuovi test sono 56: `SlotFinderTest` (18, il motore in isolamento: durata, intervallo, buffer, eccezioni, blocchi, preavviso, finestra, fusi, spostamento), `BookingActionsTest` (15), `PublicBookingTest` (11), `BookingAgendaTest` (12).

Smoke test HTTP reale con dati di seed: pagine pubbliche di portale, servizio, embed e gestione prenotazione tutte 200; agenda e disponibilità 200 sia per il manager sia per l'operatore.

**Aperto.**

- **Nessuna email.** Il link di gestione raggiunge il cliente solo dalla pagina di conferma: se la chiude, non ha più modo di annullare. È il primo punto della Fase 3.
- Round robin ("operatore indifferente") non implementato: il visitatore sceglie sempre con chi.
- Stati `completed` e `no_show` presenti nell'enum ma senza interfaccia.
- Il progetto **non è ancora un repository Git**.

---

### 2026-08-26 — Fuso orario e lingua sull'account

**Obiettivo.** Chiudere i due punti rimasti aperti dalla sessione precedente: l'operatore non poteva impostare il proprio fuso orario, e la scelta della lingua viveva solo in sessione.

**Fatto.**

- **`App\Support\Timezones`**: unico elenco di fusi orari dell'applicazione, con `options()`, `isSupported()` e `default()`. L'elenco è curato (26 voci) e non l'intero database IANA, perché la select di Flux free non ha campo di ricerca. Le tre liste identiche cablate in `⚡portals`, `⚡portal` e `⚡staff` ora puntano tutte qui.
- **Profilo**: nuova sezione "Preferenze" con fuso orario e lingua, visibile a chiunque (anche all'admin di piattaforma, che pure ha un fuso). Validazione in `ProfileValidationRules::preferenceRules()`, con `Rule::in(Timezones::options())` così un valore inventato non passa.
- **`SetLocale`**: un `?lang=` valido, oltre a finire in sessione, viene ora salvato su `users.locale` se c'è un utente autenticato. La lingua segue l'account su ogni dispositivo.

**Problemi e soluzioni.**

- **Precedenza fra sessione e account.** La sessione batte l'account nell'ordine di risoluzione di `SetLocale`. Salvando la lingua dal profilo, una scelta più vecchia rimasta in sessione avrebbe continuato a vincere e la modifica sarebbe sembrata ignorata. Risolto allineando le due cose: `updateProfileInformation()` scrive anche `session('locale')`. C'è un test dedicato che riproduce proprio questo scenario.
- **Scritture indesiderate in middleware.** Il salvataggio su `users.locale` scatta **solo** quando `?lang=` è presente e valido, mai sulle richieste normali: nessuna query di scrittura aggiuntiva nel traffico ordinario.

**Verifica.**

```
tests/Feature/Settings/PreferencesTest.php  → 7 test, 7 passati
vendor/bin/pint --format agent              → passed
vendor/bin/phpstan --memory-limit=1G        → passed, 0 errori
php artisan test --compact                  → 87 test, 85 passati, 2 saltati
```

**Aperto.** Nulla. Regole aggiornate: `.ai/rules/resources.md` (precedenza dei locale) e nuova `.ai/rules/support.md` (liste condivise in `app/Support`).

---

### 2026-08-26 — L'operatore modifica il proprio profilo pubblico

**Obiettivo.** Colmare un buco emerso provando il prodotto: `job_title` e `bio` di un operatore compaiono sulla pagina pubblica del servizio, ma li poteva scrivere solo il manager dalla pagina Staff. L'operatore vedeva la propria biografia online senza poterla correggere.

**Decisione.** Fra le tre opzioni discusse (solo manager / anche operatore / operatore propone e manager approva) è stata scelta la seconda: **l'operatore modifica i propri dati pubblici dalla pagina Profilo**, e il manager mantiene comunque la possibilità di correggerli dalla pagina Staff. Nessun flusso di approvazione: complicherebbe senza benefici su portali piccoli.

**Fatto.**

- `App\Concerns\ProfileValidationRules`: aggiunto `publicProfileRules()` con `phone`, `job_title`, `bio` (tutti nullable, con limiti di lunghezza).
- `resources/views/pages/settings/⚡profile.blade.php`: nuova sezione "Profilo pubblico" con i tre campi, visibile **solo** se l'utente appartiene a un portale (computed `hasPublicProfile`). Le regole si aggiungono a quelle base soltanto in quel caso, così l'admin di piattaforma non le vede né le subisce.
- Le stringhe vuote vengono salvate come `null`, non come `''`.
- Avviso in pagina quando l'utente non è prenotabile: i dati esistono ma non compaiono pubblicamente, e solo il manager può cambiare quel flag.
- 4 chiavi nuove in `lang/it.json`.

**Problemi e soluzioni.** Nessun intoppo. Unica accortezza: il placeholder del ruolo era stato scritto direttamente in italiano, che avrebbe rotto la convenzione "la chiave di traduzione è il testo inglese". Corretto in `e.g. Accountant, Consultant` con la resa italiana in `it.json`.

**Verifica.**

```
tests/Feature/Settings/PublicProfileTest.php  → 6 test, 6 passati
vendor/bin/pint --format agent                → passed
vendor/bin/phpstan --memory-limit=1G          → passed, 0 errori
php artisan test --compact                    → 80 test, 78 passati, 2 saltati
```

Fra i test c'è quello end-to-end che conta: l'operatore salva ruolo e biografia, si sloggia, e i due testi compaiono davvero sulla pagina pubblica del servizio.

**Aperto.**

- ~~L'operatore non può ancora impostare il proprio fuso orario dal profilo.~~ → risolto nella sessione successiva.
- ~~Lo switch della lingua salva la scelta in sessione ma non sull'account.~~ → risolto nella sessione successiva.
- Confermati i permessi degli operatori sulle prenotazioni per la Fase 2, riportati nel Backlog. Non ancora implementati.

---

### 2026-08-26 — `composer run dev` fallisce con "PHP >= 8.4.1, you are running 8.2.12"

**Obiettivo.** Primo avvio del progetto da parte dell'utente, dal suo terminale.

**Sintomo.** `composer run dev` parte, ma i processi `server` e `queue` muoiono subito con codice 255:

```
Composer detected issues in your platform: Your Composer dependencies require
a PHP version ">= 8.4.1". You are running 8.2.12.
in vendor/composer/platform_check.php:22
```

Vite invece si avvia (è Node). Nessun processo in ascolto sulla 8000 → il browser mostra `ERR_CONNECTION_REFUSED`.

**Causa.** Sulla macchina convivono due PHP: herd-lite 8.5.0 e XAMPP 8.2.12. Il PATH salvato nel registro (`HKCU:\Environment`) contiene **solo** herd-lite, e non esistono profili PowerShell che modifichino il PATH. La finestra di PowerShell dell'utente era però aperta da prima che herd-lite entrasse nel PATH: conservava la fotografia vecchia dell'ambiente, in cui XAMPP era presente.

**Soluzione.** Chiudere la finestra e aprirne una nuova. Verificare con `php -v` che risponda 8.5.0 prima di lanciare `composer run dev`.

**Nota.** Il comando dell'utente era corretto e il progetto non era in errore. `platform_check.php` è la guardia generata da Composer e ha funzionato come previsto. Procedura di verifica aggiunta alla sezione "Avviare il progetto in locale".

**Aperto.** Nulla. Se il problema si ripresentasse in un terminale nuovo, significherebbe che XAMPP rientra nel PATH da un'altra sorgente (avvio da terminale integrato di PhpStorm, o variabile impostata a livello di sessione).

---

### 2026-08-26 — Fase 1: ruoli, portali, servizi, disponibilità, pagine pubbliche

**Obiettivo.** Partire dallo starter kit Livewire vuoto e arrivare a: utenti con tre ruoli, portali multi-tenant, catalogo servizi, disponibilità settimanale, pagina pubblica per portale. Interfaccia bilingue IT/EN.

**Decisioni prese all'avvio** (concordate con l'utente prima di scrivere codice):

| Scelta | Decisione | Perché |
|---|---|---|
| Lingua | Bilingue IT/EN | clienti italiani, ma prodotto rivendibile |
| URL pubblici | Path `/p/{slug}` | zero configurazione DNS, funziona subito in locale; sottodomini rimandati |
| Scope fase 1 | Base + servizi + disponibilità | prenotazioni vere in fase 2 |
| Manager prenotabile | Sì, opzionale (`is_bookable`) | serve ai liberi professionisti che lavorano da soli |

**Fatto.**

- **Tenancy**: singolo database, `tenants` + `tenant_id` su ogni entità. `Tenant::manager()` è `hasOne` filtrato sul ruolo → vincolo "un solo manager per portale".
- **Ruoli**: `App\Enums\UserRole` (`Admin` / `Manager` / `Default`). Colonne aggiunte a `users`: `tenant_id`, `role`, `timezone`, `locale`, `phone`, `job_title`, `bio`, `avatar_path`, `is_bookable`, `is_active`.
- **Migrazioni**: `tenants`, `services`, `availability_rules`, `availability_exceptions`, `service_user`, più l'alter su `users`.
- **Autorizzazione**: middleware `EnsureUserHasRole` (alias `role`), `EnsureUserIsActive` (nel gruppo web), policy Tenant/Service/User. `User::canAccessTenant()` è l'unico punto di controllo dello scoping.
- **Admin**: `/admin/portals` (lista + creazione portale con account manager e password generata, modifica, sospensione, eliminazione), `/admin/portals/{slug}` (dettaglio, rigenerazione password manager), `/admin/users` (tutti gli account, filtri ruolo/portale).
- **Manager**: `/app/services` (CRUD servizi con durata, buffer, intervallo slot, preavviso, finestra, prezzo, colore, tipo luogo, conferma manuale, operatori), `/app/staff` (creazione operatori con credenziali generate), `/app/availability` (griglia settimanale + eccezioni per data), `/app/portal` (branding, contatti, link pubblico, snippet iframe).
- **Operatore**: dashboard con riepilogo settimana e `/app/availability` sulla propria agenda.
- **Pubblico**: `/p/{slug}` (lista servizi prenotabili) e `/p/{slug}/{servizio}` (dettaglio, operatori, orari abituali). `?embed=1` rimuove header e footer per l'iframe. Colore brand del portale via variabile CSS `--portal-brand`.
- **i18n**: `lang/it.json` con 395 chiavi, `lang/it/validation.php` con i messaggi di validazione. Switch lingua nella sidebar e sulla pagina pubblica.
- **Registrazione pubblica disattivata** in `config/fortify.php`: gli account li creano admin e manager.
- **Seeder**: admin di piattaforma + portale demo "Studio Rossi" con manager, 2 operatori, 3 servizi, 30 fasce orarie.
- **Test**: 6 file nuovi — `RoleAccessTest`, `PortalManagementTest`, `ServiceManagementTest`, `StaffManagementTest`, `AvailabilityTest`, `PublicPortalTest`, `LocalizationTest`.

**Problemi e soluzioni.**

1. **`make:enum Enums/X` crea cartelle annidate.**
   Sintomo: il primo comando genera `app/Enums/UserRole.php` corretto, i successivi finiscono in `app/Enums/Enums/`.
   Causa: Artisan risolve il percorso relativo a `app/` ma dopo la prima creazione il namespace viene ricalcolato.
   Fix: spostati a mano e namespace corretto. Per il futuro: verificare sempre dove sono finiti i file dopo `make:enum` con sottocartella.

2. **Heredoc Bash troncato su scritture multiple e lunghe.**
   Sintomo: `unexpected EOF while looking for matching '` scrivendo 6 migrazioni in un colpo solo.
   Fix: passato al tool `Write`, un file per chiamata. Regola pratica: heredoc solo per file brevi.

3. **`MultipleRootElementsDetectedException` su tutte le pagine.** ⚠️ *La più costosa.*
   Sintomo: `/dashboard` restituiva 500 — "Livewire only supports one HTML element per component".
   Causa: le pagine erano scritte avvolgendole in `<x-layouts::app>`, ma Livewire 4 applica già il layout `layouts::app` da configurazione. Il risultato era un documento HTML completo dentro il componente.
   Fix: ogni pagina ha un solo `<div>` radice, il layout arriva automaticamente. Il titolo passa da `<x-slot:title>` posto **fuori** dalla radice (ammesso per gli slot di layout) invece che da `#[Title]`, che non può chiamare `__()`.
   → Registrato in `.ai/rules/pages.md`.

4. **Il layout pubblico aveva bisogno del `$tenant`, ma `#[Layout]` accetta solo parametri statici.**
   Fix: `layouts/portal.blade.php` è diventato un guscio HTML puro; header e footer sono componenti Blade (`x-portal-header`, `x-portal-footer`) composti **dentro** la radice della pagina, che porta anche `style="--portal-brand: …"`.

5. **Due test dello starter kit rotti dal passaggio a locale italiano.**
   Sintomo: `SecurityTest` falliva su `assertSee('No passkeys yet')`.
   Causa: `APP_LOCALE=it` traduceva le stringhe attese dai test.
   Fix: `APP_LOCALE=en` in `phpunit.xml`, e le factory User/Tenant ora usano `config('app.locale')` invece di `'it'` fisso. Nessun test è stato cancellato.

6. **Test di localizzazione con aspettativa sbagliata (mio errore).**
   Avevo scritto che un `?lang=de` non supportato ricade sul default di configurazione. In realtà `SetLocale` dà priorità a `user->locale`, che è più corretto.
   Fix: test riscritto per verificare il comportamento reale (ricaduta sulla lingua dell'account), con entrambi i casi it/en.

7. **PHPStan livello 7: 6 errori.**
   Tipi iterabili mancanti su `UserRole::assignableByManager()` e `BookingStatus::blocking()`; `?->` considerato superfluo su una relazione `hasOne`; `fake()->words(3, true)` tipizzato `array|string`.
   Fix: PHPDoc `@return array<int, self>`, accesso al manager tramite variabile con `?? ''`, generazione del nome servizio con `word()` concatenati. Zero soppressioni, zero baseline.

8. **PHPStan va in crash con il memory limit di default.**
   `PHPStan process crashed because it reached configured PHP memory limit: 128M`. Serve sempre `--memory-limit=1G`.

**Limiti noti scoperti strada facendo.**

- Flux UI **free** non include `flux:calendar` né `flux:date-picker`: il calendario della fase 2 va costruito a mano (comunque necessario, uno stile Calendly è custom).
- `vendor/bin/pint --dirty` non è utilizzabile: il progetto non è ancora un repository Git.

**Verifica.**

```
vendor/bin/pint --format agent          → passed
vendor/bin/phpstan --memory-limit=1G    → passed, 0 errori
php artisan test --compact              → 74 test, 72 passati, 2 saltati
                                          (i 2 saltati sono i test di registrazione,
                                           si auto-escludono a feature disattivata)
npm run build                           → ok, 663ms
```

Smoke test HTTP reale con server su porta 8123 e login via cookie jar:

| Utente | Esito |
|---|---|
| anonimo | `/`, `/login`, `/p/studio-rossi`, `/p/studio-rossi/prima-consulenza`, `?embed=1` → tutti 200 |
| admin | `/dashboard`, `/admin/portals`, `/admin/users`, `/admin/portals/studio-rossi` → 200 |
| manager | `/dashboard`, `/app/services`, `/app/staff`, `/app/availability`, `/app/portal` → 200 |
| operatore | `/dashboard` 200, `/app/availability` 200, `/app/services` **403**, `/admin/portals` **403** |

**Aperto.** Tutta la fase 2 (motore slot e prenotazioni). Il progetto non è sotto controllo di versione: da inizializzare prima di proseguire.

---

### 2026-08-26 — Creato questo diario di bordo

**Obiettivo.** Avere traccia scritta e continua del progetto, utilizzabile sia dalle persone sia dagli agenti AI che lavoreranno sul codice in sessioni separate.

**Fatto.** Creato `LOG.md` con: istruzioni d'uso, stato, stack, mappa del codice, comandi di verifica, credenziali di seed, backlog e diario. Registrata la regola che ne impone l'aggiornamento in `.ai/rules/`. Aggiunto un rimando in `CLAUDE.md` e `AGENTS.md`.

**Decisioni.** Responsabilità separate fra `.ai/rules` (normativo, "come si fa") e `LOG.md` (cronologico, "cosa è successo"), per evitare che i due file divergano dicendo cose diverse sulla stessa materia.

**Aperto.** Nulla.
