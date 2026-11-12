# Prompt 2 — Collegamento API al License Manager

Leggi prima LOG.md e .ai/rules/index.md, poi le regole dei file che tocchi. Leggi per intero docs/guida-integrazione-licenze.md: è il contratto con il License Manager (sezioni 3, 6 e 9 obbligatorie). L'API ha UN SOLO endpoint, in SOLA LETTURA, `GET /api/v1/license`. Non inventare endpoint o campi.

## CONTESTO DEL PROGETTO

Il cliente del License Manager è il PORTALE (Tenant). Il tenant id inviato al License Manager (`externalId`) è l'`id` della Tenant come stringa (già mostrato e copiabile nella pagina admin del portale, dopo il lavoro sui dati di fatturazione, che esistono nella tabella tenant_billing_profiles).

Il software nel License Manager ha il codice `DH_CONNECT_001`. La configurazione è già nel .env: `LICENSE_API_URL`, `LICENSE_SOFTWARE_CODE`, `LICENSE_API_KEY`. Usa questi nomi: aggiungili a config/services.php e a .env.example (senza valori reali). Invia il codice come parametro `software` a ogni richiesta, per intercettare un errore di configurazione (`403 software_mismatch`). La chiave va nell'header `Authorization: Bearer`, mai nell'indirizzo. Non leggere il valore della chiave per mostrarlo o stamparlo.

Il License Manager gira in locale su http://localhost:3000 e il software è già registrato con i suoi piani (Base, Pro, Studio, gli stessi nomi dei piani locali). Non esiste ancora nessun tenant registrato: per la prova reale ti dirò io quale tenant creare nel pannello.

La guida mostra un modulo in TypeScript: qui siamo su Laravel/PHP, quindi va PORTATO in PHP (facade Http, Cache, config/services.php), non copiato.

## IL PUNTO DELICATO: QUI ESISTE GIÀ UN LIVELLO DI SCADENZE

Oggi il progetto ha una sua gestione: Plan (Base/Pro/Studio con limiti letti da App\Services\PlanUsage e Tenant::planAllowsCustomMailer), Tenant.paid_until impostato a mano (SetPaidUntil, PaidUntilController), tolleranza Tenant::SUBSCRIPTION_GRACE_DAYS = 7, promemoria a 14 e 3 giorni e sospensione automatica (portals:check-subscriptions alle 06:00, app/Console/Commands/CheckSubscriptions.php), SubscriptionNoticeMail, LayoutNotices, EnsureUserIsActive e le pagine pubbliche che rispondono 404 a un portale con is_active=false.

Il License Manager fa le stesse cose (scadenza, tolleranza, piano) e manda le proprie email automatiche. Va evitato il doppio governo.

La mia direzione: il License Manager diventa la fonte unica per accesso e piano; i limiti per piano restano in locale, mappati sul nome del piano; la sospensione manuale dell'admin (is_active) resta una cosa separata dalla licenza. Non applicare nulla senza avermelo proposto: nel piano dimmi come gestisci paid_until, la tolleranza, i promemoria interni (rischio email doppie con quelle del License Manager), il campo piano nel form admin e i portali non ancora registrati nel License Manager (demo, di prova).

## REQUISITI TECNICI

- Un solo modulo (es. app/Services/ o app/Support/, secondo le regole) che chiama l'API e restituisce uno stato già interpretato; il resto del codice non parla mai direttamente con il License Manager. Configurazione in config/services.php + .env.example: URL, chiave, codice software, timeout breve, durata della cache. La chiave resta solo lato server, mai nei log, nel frontend o nel repository. HTTPS in produzione.
- Si decide con `data.access.allowed` e `data.access.reason`; non ricalcolare scadenze o tolleranza. Piano da `data.license.planType.name`, confrontato normalizzato (trim + minuscole); piano sconosciuto = il più restrittivo, con avviso nei log.
- Mai una chiamata HTTP per ogni richiesta (limite 120 al minuto per chiave): cache per portale (5 minuti per le risposte 200, 60 secondi per 404 tenant_not_found / no_license), ultima risposta valida conservata nel database con checkedAt per quando il License Manager non risponde (sezione 6 della guida: un guasto del License Manager non deve bloccare tutti i portali). Aggiornamento periodico con un comando pianificato come gli altri in routes/console.php (withoutOverlapping), in modo che nelle richieste serva quasi sempre la copia locale.
- Dove si applica: accesso al back-office di manager e operatori (EnsureUserIsActive o un middleware dedicato), pagine pubbliche di prenotazione del portale, azioni create/prenotazione. Con licenza non valida: schermata chiara con motivo, scadenza e contatti, dati consultabili/esportabili dal manager; in tolleranza e in scadenza: avviso ben visibile (LayoutNotices esistente). Il pubblico di un portale sospeso vede lo stesso comportamento di oggi.
- Un interruttore da .env per attivare/disattivare l'enforcement, con modalità «osserva» (registra cosa farebbe senza bloccare nessuno), così posso accendere a freddo; portali dichiarati esenti se lo propone il piano.
- Pagina admin del portale: sezione «Licenza» con stato, scadenza, piano, ultimo controllo e pulsante «Verifica ora» che salta la cache (con limite di frequenza). Per il manager: stato della propria licenza nelle impostazioni del portale.
- Dati personali: la risposta contiene dati del cliente e pagamenti. Non nei log, non al browser se non serve. Se mostri il saldo, solo all'admin.
- Impersonificazione, operatori e ruoli: nessuna regressione (leggi tests/Feature/ImpersonationTest, RoleAccessTest).

## COME PROCEDERE

1. Esplora i file citati. Prima di scrivere codice, dimmi il piano: modulo e configurazione, dove salvi l'ultima risposta valida, comando di aggiornamento, punti di applicazione, mappatura piani, sorte di paid_until e dei promemoria interni, modalità «osserva», cosa vede ogni ruolo in ogni stato (attiva, in scadenza, tolleranza, scaduta, sospesa, revocata, tenant non registrato, servizio non raggiungibile). Aspetta il mio ok.
2. Implementa con test Pest e Http::fake (mai il License Manager vero nei test): allowed, in tolleranza, scaduta, sospesa, 401, 403 software_mismatch, 404, 429, timeout, risposta malformata, cache, fallback sull'ultima risposta valida, piano sconosciuto, portale esente, modalità osserva, permessi.
3. Prova reale: con il License Manager locale (URL e tenant di prova li decido io; leggi la chiave dal .env, non chiedermela in chat) e nel browser. Percorri la checklist della sezione 9 della guida voce per voce.
4. Esegui i comandi di verifica del LOG.md, riporta gli esiti reali, scrivi la voce nel LOG.md.

Non aggiungere dipendenze senza chiedere, non creare nuove cartelle base, non cambiare il comportamento esistente non coinvolto. Se qualcosa non torna, chiedimelo.
