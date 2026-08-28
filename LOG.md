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

**Fase 3 completata il 2026-08-27.** Email di prenotazione con SMTP per portale, promemoria automatici, file `.ics` e pulsanti calendario.

**Non ancora implementato:** integrazioni della Fase 4 — link automatico per le videochiamate, sincronizzazione calendari, impersonificazione. Vedi [Backlog](#backlog).

> ### ⚠️ Il collaudo manuale non è stato fatto
>
> Tutto quanto segue è verificato da **278 test automatici** e da prove HTTP puntuali. Il collaudo manuale è **iniziato ma non finito**: percorrere la checklist in [Da collaudare a mano](#da-collaudare-a-mano) prima di considerare qualcosa concluso.
>
> Test verdi non significano prodotto giusto: dicono che il codice fa quello che ho scritto io, non che sia quello che serve.

> ⚠️ Nessun server SMTP reale configurato, né per la piattaforma né per i portali. In sviluppo le email finiscono in `storage/logs/laravel.log`. Prima della produzione servono credenziali e record SPF/DKIM sul dominio mittente della piattaforma.

---

## Cosa fa oggi il prodotto

Fotografia al 2026-08-27. Per la cronologia di come ci si è arrivati, vedi il [Diario](#diario).

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

Sette messaggi, ognuno scritto per chi lo legge. Al cliente: conferma, richiesta ricevuta, annullamento, spostamento. All'operatore: nuova prenotazione, annullamento, spostamento.

- Ogni portale può inviare dal **proprio SMTP**, quindi dal proprio indirizzo
- Chi non lo configura invia tramite la piattaforma, con il proprio nome come mittente visualizzato
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
| Livewire | 4.x | componenti single-file (`⚡nome.blade.php`) |
| Flux UI | 2.x **free** | niente `flux:calendar` / `flux:date-picker` (sono Pro) |
| Fortify | 1.x | registrazione pubblica **disattivata** |
| Tailwind CSS | 4.x | |
| Pest | 5.x | |
| DB | SQLite | `database/database.sqlite`; test in memoria |

Build frontend: `npm run build` (o `npm run dev`). Tutto insieme: `composer run dev`.

---

## Mappa del codice

```
app/
  Actions/Availability/  BlockTime, SetDateOverride, SaveWeeklySchedule
  Actions/Bookings/      CreateBooking, CancelBooking, RescheduleBooking, ConfirmBooking
  Actions/Tenants/       TestMailer
  Enums/                 UserRole, LocationType, BookingStatus
  Exceptions/            SlotUnavailableException, PeriodHasBookingsException
  Http/Middleware/       EnsureUserHasRole (alias `role`), EnsureUserIsActive, SetLocale
  Livewire/Forms/        PortalForm, ServiceForm, StaffForm
  Mail/                  BookingMail + Customer/ e Staff/ (sette email), MailerTestMail
  Models/                Tenant, User, Service, AvailabilityRule, AvailabilityException,
                         Booking, TimeBlock
  Policies/              TenantPolicy, ServicePolicy, UserPolicy, BookingPolicy
  Services/              SlotFinder (slot), WorkingHours (orari), AgendaLock (concorrenza),
                         BookingNotifier (chi riceve cosa)
  Support/               Week (giorni e griglia del mese), Timezones, QrCode

routes/
  web.php           `/` e `/dashboard`, poi require degli altri
  admin.php         /admin/*   role:admin
  app.php           /app/*     role:manager | role:manager,default
  booking.php       /p/*       pubblico
  settings.php      profilo, aspetto, sicurezza (starter kit)

resources/views/
  pages/⚡dashboard.blade.php        dashboard consapevole del ruolo
  pages/admin/⚡portals|portal|users
  pages/app/⚡bookings|services|staff|availability|portal
  pages/portal/⚡show|service|booking   pagine pubbliche
  partials/booking-calendar|booking-slots|booking-form
                                    condivisi fra prenotazione e spostamento
  layouts/app.blade.php             layout back-office (default Livewire)
  layouts/portal.blade.php          guscio html per le pagine pubbliche
  errors/403|404|419|500            pagine di errore autosufficienti, CSS inline
  components/                       page-header, stat-card, locale-switcher,
                                    portal-header, portal-footer
  components/⚡smtp-settings         componente Livewire riusato da manager e admin
  mail/                             layout, partials e i corpi delle sette email

lang/it.json                        traduzioni interfaccia (chiave = testo inglese)
lang/it/validation.php              messaggi di validazione italiani
```

---

## Convenzioni

Sono in `.ai/rules/`, lette automaticamente dagli agenti. Indice: `.ai/rules/index.md`.

| Area | File | In sintesi |
|---|---|---|
| `app/**` | `.ai/rules/app.md` | ruoli e scoping per portale |
| `resources/views/pages/**` | `.ai/rules/pages.md` | pagine Livewire: root singolo, layout via slot |
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

---

## Avviare il progetto in locale

Terminale aperto nella cartella `C:\ARCHIVIO\AI\digithub-connect`.

```bash
composer run dev
```

Avvia tre processi insieme (server web, worker delle code, Vite). Si ferma con **Ctrl+C** nella stessa finestra.

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
npm run build                                           # build asset
```

Riporta gli esiti nella voce di Diario.

Nota: `vendor/bin/pint --dirty` **non funziona** finché il progetto non è un repository Git.
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
- [ ] **Elenco dei sub-responsabili** (hosting, provider SMTP, storage, eventuale servizio SMS) con obbligo di informare il titolare quando cambia
- [ ] **Dove stanno i dati**: server in UE è la strada semplice; fuori UE servono garanzie aggiuntive
- [ ] **Misure di sicurezza** dichiarabili: cifratura in transito, backup, controllo degli accessi, credenziali mai in chiaro. Parte c'è già (password SMTP cifrate, token non indovinabili, ruoli e scoping per portale)
- [ ] **Procedura di violazione**: se succede, avvisiamo **il titolare** senza ingiustificato ritardo — l'autorità la avvisa lui. Serve un canale e una procedura scritta
- [ ] **Assistenza sui diritti degli interessati**: il titolare deve poter rispondere a una richiesta di accesso o cancellazione usando il software. La cancellazione del cliente e la conservazione automatica ([5.1](#51-privacy-termini-e-consenso--completata-il-2026-08-28)) servono a questo, ed è anche un argomento di vendita
- [ ] **Registro dei trattamenti come responsabile** (art. 30.2): categorie di trattamento svolte per conto dei titolari
- [ ] **Fine del rapporto**: restituzione o cancellazione dei dati a scelta del titolare → serve l'**esportazione completa di un portale**, oggi non c'è
- [ ] **Informativa e termini della piattaforma** (i nostri, verso le attività che comprano) sul sito commerciale
- [ ] **Pagina *Documenti legali* nel pannello admin** dove tenere DPA, elenco sub-responsabili e informativa della piattaforma, con la data dell'ultima versione accettata da ogni portale

Cosa **non** è nostro: i testi legali di ogni attività, la base giuridica che sceglie, per quanto tiene i dati dei suoi clienti.

---
## Prima del deploy

Elenco delle cose che in sviluppo funzionano da sole e in produzione **no**. Da percorrere tutto prima di mettere online il primo cliente vero.

### 1. Il cron dello scheduler ⚠️

Senza questa riga i **promemoria non partono mai**. In sviluppo ci pensa `composer run dev`; in produzione niente lo sostituisce.

```
* * * * * cd /percorso/del/progetto && php artisan schedule:run >> /dev/null 2>&1
```

Ogni minuto, non ogni quarto d'ora: è lo scheduler di Laravel a decidere cosa eseguire davvero.

Verifica dopo il deploy: `php artisan schedule:list` deve mostrare `bookings:send-reminders`.

### 2. Il worker della coda ⚠️

Tutte le email passano dalla coda. Senza un worker attivo restano nella tabella `jobs` e **non parte niente**.

Serve un supervisore che lo tenga vivo e lo riavvii se muore — Supervisor su Linux, oppure il gestore di processi dell'hosting:

```
php artisan queue:work --tries=3 --max-time=3600
```

### 3. `APP_KEY` va conservata ⚠️

Le password SMTP dei portali sono cifrate con quella chiave. **Cambiarla le rende tutte illeggibili** e ogni portale smette di inviare, senza un errore che lo spieghi.

Da salvare fuori dal server, e da non rigenerare mai su un ambiente con dati veri.

### 4. Database

Oggi è SQLite, un file in `database/`. Per la produzione serve MySQL o Postgres: più connessioni contemporanee, e soprattutto `lockForUpdate()` che su SQLite non fa nulla — è il meccanismo che impedisce le doppie prenotazioni.

È anche il momento di verificare davvero il comportamento sotto concorrenza, che oggi è garantito per costruzione ma non provato.

### 5. SMTP della piattaforma

Serve per i portali che non hanno un server proprio. Vanno configurati anche **SPF e DKIM** sul dominio mittente, altrimenti le email finiscono in spam.

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

## Da collaudare a mano

**Stato: iniziata il 2026-08-27, non finita.** Le email sono collaudate su tutti e tre i livelli, SMTP reale compreso. Restano da percorrere i sei giri qui sotto.

I 278 test automatici dicono che il codice fa quello che è stato scritto. Questa procedura serve a scoprire se è quello che serve davvero, e le cose che i test non vedono: che una schermata sia comprensibile, che un messaggio sia scritto bene, che un flusso non sia scomodo.

Che serva è già dimostrato: le prime sessioni hanno prodotto **cinque difetti reali** che nessun test aveva colto — e un sesto è saltato fuori da solo il 2026-08-27, i pulsanti del back-office che passavano un `id` dove il model binding voleva la route key.

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

Accedi con **`lollodnt98@gmail.com`** (l'operatore, password `password`).

| # | Fai questo | Deve succedere |
|---|---|---|
| 1 | Guarda la barra laterale | Solo Dashboard, Prenotazioni, Disponibilità |
| 2 | Vai a mano su `/app/services` | 403 con spiegazione, non pagina bianca |
| 3 | Apri **Prenotazioni** | Solo le tue, non quelle dei colleghi |
| 4 | Annulla una tua prenotazione | Chiede il motivo, poi il cliente riceve l'email |
| 5 | **Impostazioni → Profilo** | Compila ruolo e biografia |
| 6 | Guarda la pagina pubblica del servizio | Ruolo e biografia sono lì |
| 7 | Cambia **fuso orario** nel profilo | Gli orari dell'agenda si spostano |
| 8 | Cambia **lingua** | L'interfaccia cambia e resta cambiata al riaccesso |
| 9 | **Disponibilità** → togli un giorno | Sulla pagina pubblica quel giorno sparisce |
| 10 | Aggiungi **due fasce** nello stesso giorno | Gli slot pubblici rispettano la pausa in mezzo |
| 11 | Prova a salvare fasce **sovrapposte** | Rifiutato con messaggio chiaro |
| 11b | Correggile e salva | L'errore **sparisce** dallo schermo |
| 12 | Aggiungi un **blocco orario** su una fascia libera | Quegli slot spariscono dal pubblico |
| 13 | Aggiungi un blocco su una fascia **già prenotata** | Rifiutato, dice quante prenotazioni ci sono |
| 14 | Aggiungi una **eccezione** su un giorno prenotato | Rifiutato allo stesso modo |
| 15 | Eccezione con orari che **coprono** la prenotazione | Accettata |
| 16 | Riduci gli orari settimanali lasciando fuori una prenotazione | **Salva** e avvisa, non blocca |

### Giro 3 — Il manager

Accedi con **`manager@digithub.test`**.

| # | Fai questo | Deve succedere |
|---|---|---|
| 1 | **Servizi** → creane uno nuovo | Compare subito sulla pagina pubblica |
| 2 | Metti durata 45 e intervallo 15 | Gli slot pubblici vanno di quarto d'ora |
| 3 | Metti **preavviso** 48 ore | Domani e dopodomani spariscono dal calendario |
| 4 | Metti **buffer** 15 prima e dopo | Fra due prenotazioni restano 30 minuti |
| 5 | Attiva **conferma manuale** | Prenotando esce "Richiesta ricevuta", non "Confermata" |
| 6 | Conferma dall'agenda | Il cliente riceve la seconda email |
| 7 | Nascondi un servizio | Sparisce dal pubblico ma resta in elenco |
| 8 | **Staff** → crea un operatore | Credenziali mostrate una volta sola, e funzionano |
| 9 | Assegna e togli servizi a un operatore | La pagina pubblica si aggiorna |
| 10 | Attiva **"ricevo prenotazioni anch'io"** | Compari fra gli operatori prenotabili |
| 11 | Prova a eliminare un operatore con prenotazioni future | Rifiutato, dice quante |
| 12 | **Prenotazioni** → prova tutti i filtri | Periodo, stato, servizio, operatore |
| 13 | Annulla una prenotazione con motivo | Il cliente riceve l'email col motivo |
| 14 | **Disponibilità** → cambia operatore dalla tendina | Modifichi l'agenda di un altro |
| 15 | **Impostazioni portale** → cambia colore del brand | Cambia sulla pagina pubblica **e** nelle email |
| 16 | Copia il **link pubblico** e lo **snippet iframe** | Entrambi funzionano |
| 17 | Attiva **"mandami una copia"** | Prenotando a un operatore ricevi anche tu |
| 18 | Metti un mittente su un **altro dominio** | Compare l'avviso, ma salva comunque |

### Giro 4 — L'admin

Accedi con **`admin@digithub.test`**.

| # | Fai questo | Deve succedere |
|---|---|---|
| 1 | **Portali** → creane uno nuovo | Credenziali del manager mostrate una volta sola |
| 2 | Accedi con quelle credenziali | Funzionano, e vedi un portale vuoto |
| 3 | Torna admin, apri il **dettaglio** del portale | Servizi, operatori, contatti |
| 4 | **Rigenera la password** del manager | Nuova password mostrata; **la vecchia smette di funzionare** |
| 5 | Configura l'**SMTP** di quel portale | Stesso riquadro del manager |
| 6 | **Sospendi** un portale | La sua pagina pubblica dà 404 |
| 7 | Riattivalo | Torna raggiungibile |
| 8 | **Account** → filtra per ruolo e portale | I filtri funzionano |
| 9 | **Disabilita** un account | Al primo caricamento viene buttato fuori |
| 10 | Prova ad andare su `/app/bookings` | 403 con la spiegazione dedicata all'admin |
| 11 | **Elimina** un portale di prova | Spariscono anche utenti, servizi e prenotazioni |

### Giro 5 — Le cose che i test non possono giudicare

Da guardare mentre percorri i giri sopra.

- [ ] I testi italiani suonano naturali, o sembrano tradotti male?
- [ ] Le schermate si capiscono senza spiegazioni?
- [ ] Qualche flusso richiede troppi passaggi?
- [ ] Su schermo piccolo qualcosa si rompe o diventa scomodo?
- [ ] I messaggi d'errore dicono davvero **cosa fare**, o solo cosa è andato storto?
- [ ] C'è qualche schermata dove non capisci cosa fare dopo?

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

### Problemi trovati durante il collaudo

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

### Fase 3 — notifiche (3.1 e 3.2 fatte, 3.3 e 3.4 da fare)

**Identità del mittente — decisa e implementata.** Dipende da come è configurato il portale:

| Portale | Trasporto | `From:` |
|---|---|---|
| ha un **SMTP proprio** | il suo server | il suo indirizzo — SPF e DKIM del suo dominio tornano, perché è davvero il suo server |
| non ce l'ha | mailer della piattaforma | indirizzo nostro, con il nome del portale come display name e il suo `Reply-To` |

La seconda riga esiste perché mettere `info@studiorossi.it` nel `From` inviando dai nostri server fa fallire SPF e DMARC del suo dominio: spam o rifiuto. Con SMTP proprio quel vincolo non c'è.

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

- [x] **SMTP per portale**: ogni attività invia dal proprio server e dal proprio indirizzo, configurabile dal manager e dall'admin
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

#### 4.1b Impersonificazione dal pannello admin

Deciso il 2026-08-27 di **non** aprire l'agenda dei portali all'admin. Se il giro con le credenziali del proprietario dovesse risultare scomodo, questa è la strada corretta per sostituirlo.

Serve a: assistere un cliente guardando quello che vede lui, senza che nessuno si scambi password.

- [ ] Pulsante *"Entra come questo manager"* nel dettaglio portale, riservato all'admin
- [ ] Sessione di impersonificazione **a scadenza** (es. 30 minuti), non permanente
- [ ] **Banner sempre visibile** durante l'impersonificazione, con il pulsante per uscirne: chi impersona non deve mai dimenticare di non essere sé stesso
- [ ] **Registro tracciato**: chi ha impersonato chi, quando è iniziato e quando è finito. È il punto che rende la funzione accettabile rispetto alla privacy, e va scritto prima del resto
- [ ] Sola lettura oppure anche scrittura? Da decidere: scrivere al posto del cliente è comodo per l'assistenza e pericoloso per la responsabilità
- [ ] L'impersonificazione **non** deve poter essere annidata, né usata su un altro admin
- [ ] Le azioni compiute durante l'impersonificazione vanno attribuite all'admin nei campi tipo `cancelled_by_id`, non al manager

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
- [ ] Rimandato al giro successivo: far valere i limiti (blocchi alla creazione, avvisi di soglia, sospensione a scadenza, fatturazione)

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
- [ ] **Servizi di gruppo** con capienza maggiore di uno (corsi, lezioni): oggi ogni slot è per una persona sola
- [ ] **Lista d'attesa** quando non ci sono slot liberi
- [ ] **SMS o WhatsApp** oltre alla email per i promemoria
- [ ] **Esportazione CSV** delle prenotazioni e un vero **report**: prenotazioni per servizio e per operatore, tasso di assenza, incassato
- [ ] **Sedi e risorse** prenotabili (stanze, poltrone, attrezzature) oltre alle persone
- [ ] **Pagamenti o caparra** al momento della prenotazione
- [ ] **Registro delle azioni** (chi ha annullato cosa e quando) oltre ai campi già presenti sulle prenotazioni
- [ ] **Cifratura delle note** (`bookings.customer_notes` e `customers.notes`) con il cast `encrypted`: sono testo libero, nessuna query li filtra, quindi costa poco e toglie dal database il contenuto più delicato. Valutato il 2026-08-28 e rimandato
- [ ] **Blind index sull'email** se servirà cifrare l'anagrafica intera (richiesta di un cliente sanitario, per esempio): colonna HMAC per uguaglianza e unicità, con la ricerca parziale sul nome che però si perde
- [ ] **Requisiti di hosting**: database con encryption at rest e backup cifrati — copre il furto di backup o disco senza toccare il codice, e vale più della cifratura applicativa
- [ ] **Esportazione completa di un portale** (dati e allegati): serve alla fine del rapporto contrattuale, quando il titolare chiede restituzione o cancellazione → vedi [Il ruolo della piattaforma](#il-ruolo-della-piattaforma-e-i-suoi-obblighi)
- [ ] **Pagina *Documenti legali* nel pannello admin**: DPA, sub-responsabili, informativa della piattaforma, e la versione accettata da ogni portale
- [ ] Produzione: MySQL/Postgres, cron, worker, backup, SMTP di piattaforma con SPF e DKIM, avviso sui job email falliti → vedi [Prima del deploy](#prima-del-deploy)
### Da valutare
- [x] **L'admin può vedere l'agenda di un portale? No** — deciso il 2026-08-27. `/app/*` resta chiuso all'admin: i dati personali dei clienti di un'attività non passano sotto gli occhi del gestore della piattaforma. Per l'assistenza si accede **con le credenziali del proprietario**, fornite da lui.
  - Conseguenza da tenere presente: il pulsante *"Genera nuova password"* nel dettaglio portale **estromette il manager**, perché la sua vecchia password smette di funzionare. Per l'assistenza serve che sia lui a comunicare le proprie credenziali, non che l'admin se le generi.
  - Alternativa tenuta a mente per il futuro: **impersonificazione** dal pannello admin, dettagliata in [4.1b](#41b-impersonificazione-dal-pannello-admin).
- [x] **Round robin** — fatto il 2026-08-27: pulsante *"Operatore indifferente"*, calendario unito, assegnazione al meno carico con passaggio al successivo se perde la corsa
- [ ] Test di concorrenza reale sul lock, possibile solo su MySQL/Postgres
- [ ] Agenda: terza vista **settimanale a fasce orarie** (blocchi proporzionali alla durata, come Google Calendar) accanto a tabella e mese
- [ ] Gli indirizzi dei seed (`@digithub.test`) vengono rifiutati dall'SMTP reale con `550 recipient rejected`: ogni prova riempie il log di errori. Da decidere se usare domini validi nei seed o tornare a `MAIL_MAILER=log` in sviluppo
- [ ] Pulsante Outlook: oggi punta a `outlook.live.com` (account personali). Gli account Microsoft 365 aziendali usano `outlook.office.com` — valutare un secondo link
- [x] **Stati `completed` e `no_show`** — fatti il 2026-08-27: voci di menu nell'agenda, solo su appuntamenti finiti e non annullati
- [ ] Il calendario pubblico raggruppa i giorni nel fuso del **portale**: per un visitatore molto lontano uno slot può cadere in un altro giorno locale (oggi mostrato accanto all'orario)
- [ ] Sottodomini `{slug}.dominio.it` (oggi solo path `/p/{slug}`) — serve DNS wildcard + certificati
- [x] **Logo del portale** — fatto il 2026-08-27 (JPG/PNG/WebP fino a 1 MB, iniziali colorate come ripiego)
- [ ] Avatar degli operatori (`avatar_path` già presente, non usata)
- [ ] Riordino servizi drag & drop (colonna `position` già presente)
- [x] Domande personalizzate sul form di prenotazione → pianificate in [5.6](#56-domande-personalizzate-nel-form-di-prenotazione)
- [ ] Pagamenti → vedi [5.7](#57-non-prioritarie-ora-ma-sul-tavolo)
- [ ] Passaggio da SQLite a MySQL/Postgres prima della produzione
- [x] Git inizializzato e progetto caricato su GitHub — 2026-08-27

### Controlli ricorrenti
- [ ] `lang/it.json` copre tutte le chiavi `__()` presenti nel codice
- [ ] Ogni nuova entità di dominio ha `tenant_id` e uno scope per portale
- [ ] Ogni nuova rotta è dietro il middleware `role:` corretto
- [ ] Ogni nuova azione Livewire chiama `authorize()` o `abort_unless()`

---

## Diario

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
