# Collaudo completo — DigitHub Connect

Avvio: 2026-10-01. Ultimo aggiornamento: 2026-10-02. Responsabile esecuzione: Codex, personalmente nel browser.

## Esito alla consegna

Eseguiti percorsi reali come manager, operatori, cliente e admin in database separati. Corrette e riprovate tutte le tredici voci B01–B13, comprese B05 e il nuovo duplicato della lista d'attesa. Suite finale: **636 test: 634 passati e 2 saltati**, 1.891 asserzioni. Analisi statica senza errori, formattazione/build riuscite; audit Composer/npm senza vulnerabilità dopo gli aggiornamenti approvati.

**Il collaudo resta aperto per i casi PARZIALE/UTENTE indicati sotto.** Nessun difetto critico/alto noto rimane aperto nei percorsi locali verificati; questo non è una certificazione di sicurezza né una prova della capacità del server di produzione. Le funzionalità non implementate (sincronizzazione calendari, link video automatici, applicazione dei limiti/fatturazione dei piani) non sono dichiarate collaudate.

Matrice di **72 casi composti: 36 PASSATO, 31 PARZIALE, 5 UTENTE**. «Parziale» indica varianti coperte solo dai test o da ripetere; non significa difetto accertato né verifica completa nel browser.

## Metodo e ambiente

- Ambiente dedicato su `127.0.0.1:8010`, database `storage/app/private/collaudo-20261001.sqlite`, cookie distinto. I dati dell'applicazione su porta 8000 restano conservati.
- Dati e recapiti fittizi. Email intercettate dal server SMTP locale di prova, senza invio a persone reali. Modifiche distruttive limitate a questo ambiente.
- Percorsi completi come cliente anonimo, manager, Giulia/Luca operatori; admin per verificare i confini e la gestione della piattaforma. Secondo portale per isolamento.
- Ogni caso registra azione, risultato atteso, risultato osservato, evidenza e limite. Un controllo automatico non sostituisce un controllo nel browser.
- Stati: **DA FARE**, **PASSATO**, **DIFETTO**, **PARZIALE**, **UTENTE**. Un caso composto passa solo quando tutte le sue parti sono state verificate.
- Difetti: riproduzione, gravità, conseguenza, correzione mirata e ripetizione del percorso. Nessun esito inventato o dedotto soltanto dal codice.
- Screenshot dei risultati significativi, messaggi SMTP e verifiche dei download; nessuna password reale nelle evidenze.

## Piano dei casi

| ID | Ruolo / area | Prova e risultato atteso | Stato | Esito osservato e limite |
|---|---|---|---|---|
| A01 | Anonimo | Agenda, profilo e pagine amministrative richiedono accesso; nessun dato riservato esposto | PARZIALE | Browser: agenda anonima rinviata al login; confini delle altre pagine coperti anche dai test dei ruoli. |
| A02 | Accesso | Campi vuoti, password errata, account inesistente; messaggi comprensibili senza rivelare l'esistenza dell'account | PASSATO | Browser: vuoti, password errata e account inesistente rifiutati; risposta generica in italiano dopo B01. |
| A03 | Accesso | Tentativi ripetuti attivano il limite; accesso valido e logout; tornare indietro non consente azioni private | PASSATO | Browser: sei tentativi → 429 in italiano; accesso e logout verificati; sessione disabilitata non mantiene permessi. |
| A04 | Accesso | Registrazione pubblica disabilitata; reset password: richiesta valida, email, token errato/scaduto e riuso | UTENTE | Browser: richieste reset per account noto/ignoto identiche dopo B08; email italiana ricevuta dal SMTP locale. Registrazione disabilitata e risposta web/JSON nei test. Inserimento nuova password, token scaduto/riusato da completare (§ intervento 2). |
| A05 | Sicurezza account | Conferma password prima delle impostazioni sensibili; password, 2FA e passkey con credenziali/browser reali quando necessari | UTENTE | Browser: conferma password errata/corretta verificata. Nuova password, 2FA e passkey richiedono intervento umano (§ 2). |
| M01 | Manager / servizi | Creare, modificare, riaprire e pubblicare/nascondere un servizio; persistenza e aggiornamento pubblico | PASSATO | Browser: creazione, modifica, riapertura, pubblicazione/nascondimento e aggiornamento pubblico di servizi individuali, gruppo e pacchetto. |
| M02 | Manager / validazioni | Nome vuoto, durate/prezzi/preavvisi negativi o fuori limite, testo lungo, valuta/prezzo e gratuità | PARZIALE | Browser: prezzo negativo rifiutato, gratuità salvata e prenotata; duplicati e qualifiche rifiutati. Restanti estremi di validazione coperti da test, non tutti ripetuti a mano. |
| M03 | Manager / domande | Testo breve/lungo, singola/multipla scelta, obbligatorietà, opzioni; snapshot dopo modifica e rimozione | PARZIALE | Browser: creati quattro tipi obbligatori, risposte salvate e lette nell'agenda; messaggi duplicati corretti B09. Modifica/rimozione con conservazione storico nei test. |
| M04 | Manager / staff | Creazione con credenziali, accesso, assegnazione/togliere servizi, prenotabilità; vincoli di eliminazione con appuntamenti futuri | PASSATO | Browser: Operatore Collaudo creato, accesso con password generata riuscito, servizi/prenotabilità salvati; agenda personale con una prenotazione; eliminazione bloccata per impegno futuro. |
| M05 | Manager / disponibilità | Due fasce, sovrapposizioni, pause, eccezioni aperte/chiuse, blocchi; appuntamenti futuri protetti e avviso sugli orari settimanali | PASSATO | Browser: fasce, sovrapposizioni, protezione appuntamenti, chiusure e blocchi verificati. Apertura Giulia sabato 10 ottobre 10–12: soli slot 10:00–11:30; rimossa, giorno nuovamente chiuso. Motivo interno assente nel pubblico. |
| M06 | Manager / agenda | Giorno/settimana/mese/lista, filtri combinati, ricerca, dettagli, conferma, annullamento motivato | PASSATO | Browser: quattro viste, filtri, ricerca, dettagli e approvazione. Manager annulla Cliente guardia gruppo con motivazione: stato, pagina cliente ed email locale coerenti. Dettagli accessibili dalla tabella anche senza note dopo B12. |
| M07 | Manager / esiti | Completato/assente su appuntamenti passati, correzione esito; azioni assenti su futuri e annullati | PASSATO | Browser, copia storica: completato → assente e correzione su passato; azioni assenti sui futuri/annullati. Giulia corregge il proprio esito. |
| M08 | Manager / clienti | Anagrafica e storico, stesso indirizzo con maiuscole, nota interna, anonimizzazione e confini del portale | PARZIALE | Browser: anagrafica, storico, nota interna e anonimizzazione irreversibile autorizzata; nome/email/note rimossi, storico conservato. Normalizzazione maiuscole e confini degli identificativi nei test. |
| M09 | Manager / report | Periodi, stati, operatori; coerenza con agenda e dati esportati | PARZIALE | Browser: cambio periodo e report personali coerenti con il lavoro visibile; calcoli dettagliati/stati e aggregazioni nei test, non ricalcolati tutti manualmente. |
| M10 | Manager / esportazioni | CSV corretto e filtrato, UTF-8, virgole/virgolette/a capo; input `=`, `+`, `-`, `@` non eseguito come formula | PARZIALE | Browser: CSV scaricato realmente, formula con = neutralizzata; UTF-8, virgolette e righe multiple ispezionati nel file. Prefissi +, -, @ coperti dai test. |
| M11 | Manager / portale | Branding, contatti, policy, conservazione, copia email; anteprima/link/iframe | PASSATO | Browser: impostazioni e link verificati. Snippet su pagina ospite locale con diversa origine: elenco → Luca → 13 ottobre 10:00 → modulo → conferma dentro iframe; prenotazione coerente nell'agenda ed email locale ricevuta. Hosting reale/HTTPS resta verifica staging § 5. |
| M12 | Manager / file | Logo PNG/JPG valido, sostituzione/rimozione; PDF, file falso, SVG e dimensioni eccessive rifiutati | PARZIALE | Browser: PNG e PDF fittizi caricati con autorizzazione; falso PNG e file 3 MB rifiutati; errore tradotto B01. JPG, SVG e intero ciclo rimozione/sostituzione non tutti ripetuti nel browser. |
| M13 | Manager / registro | Azioni visibili con filtri, attribuzione corretta; assenza di password e note private dei clienti | PASSATO | Browser: registro con attribuzione, filtri e azioni realmente eseguite; dettagli senza password o note private dei clienti. |
| O01 | Operatore | Dashboard, agenda e report mostrano solo il proprio lavoro; Giulia non vede quello di Luca | PASSATO | Browser: Giulia vede agenda/report personali, Cliente solo Luca assente; nuovo operatore vede il proprio unico appuntamento. |
| O02 | Operatore | Conferma/annullamento propri; accesso diretto a servizi, risorse, staff, clienti, portale e admin rifiutato | PASSATO | Browser: conferma e annullamento motivato propri; servizi/risorse/staff/clienti/portale/registro/admin negati. Esiti propri sul database storico. |
| O03 | Operatore | Modificare proprie disponibilità; nessuna possibilità di modificare quelle dei colleghi tramite URL o identificativi | PARZIALE | Browser: disponibilità personali salvate e riflessi pubblici verificati. Alterazione identificativi dei colleghi verificata dai test, senza manipolare lo stato del browser. |
| O04 | Operatore | Profilo pubblico, biografia, telefono, fuso e lingua; persistenza al nuovo accesso | PASSATO | Browser, copia storica: telefono fittizio, biografia con script, inglese e Kolkata salvati; logout/login Giulia conserva valori e orari. Testo inerte, telefono verificato nello screenshot. Lingua/fuso/biografia infine ripristinati. |
| C01 | Cliente | Elenco, prezzi/durate/luoghi, calendario, giorni non disponibili, avanti/indietro, cambio giorno e cambio orario | PASSATO | Browser: elenco, luoghi, prezzi, durata, navigazione calendario, indisponibilità, giorni/orari e cambio selezione. |
| C02 | Cliente | Operatore indifferente, scelta specifica e servizio con un solo operatore; assegnazione corretta | PASSATO | Browser: indifferente, Giulia/Luca specifici; servizio Solo Giulia mostra un solo operatore; nuova utenza prenotabile su Prima consulenza. |
| C03 | Cliente | Modulo vuoto/email invalida/domande obbligatorie/consenso; errori vicini ai campi e nessuna prenotazione parziale | PASSATO | Browser: campi vuoti/email non valida/consenso mancante e quattro tipi di domande obbligatorie; nessuna prenotazione parziale. Multipla scelta ripetuta dopo B09. |
| C04 | Cliente | Prenotazione confermata; un appuntamento, cliente e risposte corretti; vecchio orario occupato | PASSATO | Browser: conferme, dati/risposte e agenda corrispondono; moduli obsoleti rifiutati quando lo slot è occupato. |
| C05 | Cliente | Conferma manuale: richiesta in attesa, tempo comunque occupato; approvazione dal manager e aggiornamento del link | PASSATO | Browser: pacchetto in attesa occupa e viene approvato; link aggiornato. Pending separato senza calendari né ICS, accesso diretto ICS → 404 dopo B03. |
| C06 | Cliente | Copia link, QR PNG, ICS: stesso appuntamento, date/fusi, nessuna informazione interna | PARZIALE | Browser: copia link corretta, ICS scaricato e contenuto verificato. QR cliccato due volte senza download rilevato; importazioni calendario e scansione da completare (§ 3). |
| C07 | Cliente | Spostamento: stesso token, nuovo orario occupato, vecchio libero; annullamento e doppia azione rifiutata | PASSATO | Browser: intero pacchetto spostato mantenendo token/programma storico, vecchio orario liberato; annullamento libera risorse e rende indisponibile una nuova azione sullo stesso appuntamento. |
| C08 | Cliente | Preavviso, finestra massima e chiusure; gestione aperta anche per portale sospeso, spostamento vietato | PASSATO | Browser: preavviso/finestra e chiusure limitano il calendario; portale sospeso nasconde pubblico, gestione precedente resta aperta ma spostamento vietato. |
| C09 | Cliente | Fusi Roma/New York/mezze ore e cambio legale/solare; data e ora coerenti in modulo, agenda, email e ICS | PARZIALE | Browser: Roma → New York 09:00 → 03:00; 26 ottobre dopo cambio solare, Roma 09:00 → Kolkata 13:30 e link calendari UTC 08:00. Email/ICS ispezionati su casi dedicati; stesso caso DST importato in app reale non verificato. |
| C10 | Cliente / attesa | Iscrizione, doppia iscrizione, liberazione posto e notifica, disiscrizione tramite token | PASSATO | Browser: iscrizione, liberazione posto, notifica e disiscrizione token. Maiuscole/minuscole scoprono B13: dopo fix una nuova iscrizione unica e una notifica per indirizzo; anche i duplicati precedenti ricevono un solo avviso. B07 già corretto e riprovato. |
| R01 | Risorse | Creazione/modifica di stanza, cabina e strumento; prep/riassetto, stato, note private | PASSATO | Browser: cabine, sale e strumento creati/modificati con prep/riassetto/stato/note. Strumento 5/5 minuti disattivato/riattivato; nota privata assente al cliente. Stato Inattivo tradotto. |
| R02 | Risorse | Tutti i requisiti necessari; alternative equivalenti; risorsa inattiva o senza alternative toglie disponibilità | PASSATO | Browser: servizio richiede (cabina 1 O cabina alternativa) E strumento. Strumento inattivo → nessun giorno prenotabile; riattivazione ripristina gli slot. Prima cabina occupata: Luca 6 ottobre 11:00 riceve seconda cabina e strumento insieme. |
| R03 | Risorse | Due operatori competono per la stessa risorsa: nessuna doppia occupazione; seconda alternativa usata quando possibile | PARZIALE | Browser: Giulia/Luca stessa cabina, seconda richiesta rifiutata; secondo servizio usa realmente l'alternativa con prima occupata. Simultaneità SQLite con processi paralleli nei test; contesa reale su server di staging resta § 5 (SQLite confermato il 2026-10-04). |
| R04 | Risorse | Preparazione/riassetto e manutenzione eliminano gli orari corretti; toccarsi agli estremi non è sovrapporsi | PARZIALE | Browser: manutenzione 12 ottobre 09–10 rende primo slot 10:15 perché la preparazione di quello delle 10 collide; buffer visibili nell'agenda. Confine esatto fra intervalli nei test. |
| R05 | Risorse | Indisponibilità, disattivazione e cancellazione rifiutate con occupazioni future; nomi/orari conservati nello storico | PARZIALE | Browser: disattivazione e manutenzione con occupazioni future rifiutate; risorse storiche conservate dopo annullamento. Tutte le varianti di cancellazione/rinomina nei test. |
| R06 | Risorse | Spostamento atomico e annullamento liberano risorse; errore non lascia blocchi o appuntamenti parziali | PARZIALE | Browser: spostamento e annullamento del pacchetto riassegnano/liberano; errori simulati e rollback completo nei test. |
| G01 | Gruppi | Più posti nella stessa sessione, contatore e ultimo posto; operatore e risorsa occupati una sola volta | PASSATO | Browser: capienza 2, due posti nella stessa sessione, terzo modulo obsoleto rifiutato; risorsa/operatore impegnati per sessione. |
| G02 | Gruppi | Annullare un posto non libera la sessione; ultimo annullamento libera tutto; riapertura ricontrolla disponibilità | PASSATO | Browser: primo annullamento lascia ultimo posto, ultimo libera sessione/risorsa; riapertura e aggiunta operatori ricontrollate. |
| G03 | Gruppi | Aggiungere operatore libero, rifiutare occupato; spostamento individuale del posto non disponibile | PARZIALE | Browser: aggiunta/rimozione liberi, ultimo operatore rifiutato con messaggio dentro modale dopo B05, riaggiunta Luca cancella errore; spostamento individuale assente. Operatore occupato e altre varianti nei test. |
| P01 | Pacchetti | Creazione con due servizi, riordino, pause, prezzo somma/specifico, persistenza e visibilità pubblica | PASSATO | Browser: due fasi, riordino, pausa 20 minuti; prezzo somma €80, poi €85 dopo modifica componente; prezzo specifico €70 salvato e mostrato, infine ripristinata somma €85. |
| P02 | Pacchetti | Singola fase, duplicati, gruppo, pacchetto annidato, servizio esterno, operatore non abilitato rifiutati | PARZIALE | Browser: duplicati e operatore non abilitato rifiutati, conversione componente in gruppo bloccata. Singola fase, annidamenti e identificativi esterni nei test. |
| P03 | Pacchetti | Un modulo e un appuntamento; domande contestuali, conferma dell'intero pacchetto, programma e prezzo corretti | PASSATO | Browser: modulo unico, domanda contestuale, richiesta/approvazione dell'intero pacchetto; programma e prezzo verificati nel pubblico, agenda ed email. |
| P04 | Pacchetti | Operatore occupato anche nella pausa; risorse solo per fase; cambio cabina e riuso compatibile | PARZIALE | Browser: un operatore e risorse distinte per fase, pause e finestre visibili; occupazione operatore nelle pause, cambio/riuso della stessa cabina nei test mirati. |
| P05 | Pacchetti | Sovrapposizione prep/riassetto nella sequenza: alternativa valida o intero pacchetto indisponibile | PARZIALE | Browser: prep/riassetto e sequenza cabina → sala osservati. Combinazioni con alternative e conflitto fra fasi coperte dai test. |
| P06 | Pacchetti | Modificare catalogo dopo prenotazione non riscrive nomi/prezzi/programma concordato | PASSATO | Browser: A modificato da 30 min/€30 a 45 min/€35; catalogo passa a 95 min/€85, appuntamento esistente conserva 80 min/€80 anche dopo spostamento. |
| P07 | Pacchetti | Spostamento/annullamento interi; conflitto in ultima fase non modifica parzialmente il programma | PARZIALE | Browser: spostamento e annullamento unici dell'intero programma. Conflitto in ultima fase e assenza di modifiche parziali provati nei test. |
| P08 | Pacchetti | Componente con impegni non convertibile in gruppo/eliminabile; non disponibile se componente nascosto o operatore non abilitato | PARZIALE | Browser: conversione componente in gruppo rifiutata; nascondere componente rende pacchetto 404, ripubblicare lo ripristina. Eliminazione e qualifiche dopo modifiche coperte dai test. |
| S01 | Confini | URL e token di un altro portale; dati di prenotazione/cliente/risorsa/operatori mai visibili o modificabili | PARZIALE | Browser: secondo portale isolato, impersonificazione e URL Studio Rossi rifiutati; accessi per ruolo verificati. Tutte le varianti di identificativi/token esterni nei test. |
| S02 | Input ostili | HTML e script in nomi/note/domande/risorse; visualizzazione come testo, nessuna esecuzione nel pubblico, agenda o email | PASSATO | Browser e email locali: script in nome, note, opzioni, biografia e note risorse restano testo; nessun dialogo/esecuzione, nessuna nota interna nel pubblico. |
| S03 | Ricerca | Apici, commenti SQL, `%` e `_`: nessun errore o accesso ampliato; ricerca trattata come testo | PASSATO | Browser: apici/OR/commento SQL nell'agenda senza ampliamento né errore; % e _ nel filtro Clienti non fungono da wildcard, lista vuota. Ricerca infine svuotata. |
| S04 | Dati alterati | Campi nascosti, identificativi esterni, prezzi/stati/slot non autorizzati, chiamate senza CSRF; controlli server verificati anche con test mirati | PARZIALE | Test HTTP/Livewire: ID esterni, stato/prezzo/slot falsi e autorizzazione server; POST senza CSRF → 419 anche con richiesta locale supplementare. Non descritta come manipolazione eseguita nel browser. |
| S05 | Concorrenza browser | Due tab con vecchio slot: primo invio accettato, secondo ricontrollato; nessuna mail/prenotazione duplicata | PASSATO | Browser: due moduli sullo stesso slot/resource, primo accettato e secondo rifiutato, nessun nuovo appuntamento duplicato. |
| S06 | Concorrenza gruppi | Due tab sull'ultimo posto e prenotazioni concorrenti per stessa risorsa; nessuna capienza/occupazione superata | PARZIALE | Browser: tre moduli obsoleti per due posti; richieste consecutive controllate. Processi simultanei SQLite già coperti dai test; vera contesa multiutente su server di staging da completare (§ 5; SQLite confermato il 2026-10-04). |
| S07 | Limiti abuso | Ripetere prenotazione/lista d'attesa e login; limite attivo senza errori tecnici e senza bloccare indefinitamente altri utenti | PARZIALE | Browser: limite login 429, nuovo tentativo valido con altro account; limiti prenotazione/lista d'attesa coperti dai test, non raggiunti manualmente tutti. |
| S08 | Esposizione | Errori 403/404 e token inventati; `.env`, database e file privati non scaricabili, niente stack trace pubblici | PASSATO | Browser: file .env/database/privati e URL inventati → 403/404 senza stack pubblico; ambiente APP_DEBUG=false. |
| D01 | Admin | Portale di prova e manager; account/filtri, piani e pagine piattaforma; nessun accesso involontario ad area manager | PASSATO | Browser: portale esterno e manager fittizi creati con approvazione, account/filtri/piano fittizio e pagine piattaforma. Limiti/fatturazione dei piani non implementati. |
| D02 | Admin | Sospensione/riattivazione portale, disabilitazione account e sessione già aperta | PASSATO | Browser: sospensione/riattivazione, disabilitazione di Marco con sessione aperta forza logout; riattivazione e nuovo accesso riusciti. |
| D03 | Admin | Impersonificazione, banner, uscita e scadenza; permessi coerenti col ruolo impersonato | PARZIALE | Browser: impersonificazione, banner, uscita e permessi; scadenza automatica 30 minuti nei test, non attesa a mano. |
| D04 | Admin | Eliminazione del solo portale fittizio con servizi, pacchetti, risorse e prenotazioni; nessun orfano | PARZIALE | Browser: portale esterno e manager fittizi eliminati definitivamente con approvazione; nessun orfano. Portale eliminato senza dati complessi: cascata con pacchetti/risorse/prenotazioni nei test. |
| E01 | Email | Conferma/richiesta, approvazione, spostamento, annullamento: destinatari separati, lingua, programma, token e allegati | PASSATO | Invii realmente intercettati in .eml: richiesta/conferma/approvazione/spostamento/annullamento, destinatari distinti, programma e token; pending senza ICS dopo B03. |
| E02 | Email | Copia al manager e promemoria; nessuna duplicazione, testi e orari corretti | PASSATO | Copia manager osservata; copia storica: promemoria eseguito realmente, 1 invio poi 0, orario italiano corretto; non duplicato. Fixture anticipata solo sulla copia autorizzata. |
| E03 | Email | SMTP proprio, prova, errore autenticazione/mittente/connessione, fallback piattaforma e stato nel browser | UTENTE | Pagina SMTP preparata; utente ha chiesto di lasciare istruzioni nel rapporto. Nuove credenziali SMTP richiedono inserimento umano. Trasporti/fallback coperti dai test, ciclo UI con configurazione propria non eseguito (§ 1). |
| E04 | Automazioni | Completamento appuntamenti, promemoria, anonimizzazione/retention, potature e job falliti: comandi previsti e risultato visibile | PARZIALE | Comandi reali sulla copia: chiusi 2 poi 0; anonimizzati 7 poi 0 e storico visibile; promemoria 1 poi 0. Riprova job falliti eseguita nel browser. Potature senza righe idonee: casi positivi nei test. |
| E05 | Consegna reale | Ricezione su provider reale, spam, SPF/DKIM/DMARC, apertura allegati su Outlook/Gmail e calendario reale | UTENTE | Solo SMTP locale; consegna/provider/spam, DNS SPF/DKIM/DMARC e importazione negli account reali da verificare (§ 4). |
| U01 | UX | Italiano/inglese, tema chiaro/scuro, leggibilità e testi dei messaggi in tutti i ruoli | PARZIALE | Browser: IT/EN e temi nei ruoli, titolo Sicurezza italiano, singolare gruppo, stato risorsa tradotto. Copia e comandi IT→EN→IT corretti dopo B11. B01/B05 chiusi; non revisionata ogni stringa di ogni schermata. |
| U02 | UX | Viewport 390 px: elenco, calendario, modulo, conferma, agenda e modali; assenza di overflow e controlli accessibili | PASSATO | Browser: viewport 390 px, pubblico/calendario/modulo/conferma/agenda/modali; documento 375 px, senza overflow globale. Overrides temporanei rimossi; telefono fisico non provato. |
| U03 | Accessibilità | Tastiera, focus, etichette, errori e chiusura modali; controlli principali senza mouse | PARZIALE | Browser: Tab, Escape e ritorno focus ad Azioni della prenotazione; comandi copia/visibilità/ricerca/menu/chiusura con nome. Avviso gruppo dentro modale. B05/B11 chiusi; revisione completa con lettore di schermo resta § 6, nessuna attestazione WCAG. |
| F01 | Prestazioni | Tempi osservati di elenco/calendario/apertura modulo/prenotazione; blocchi, errori console e interazioni ripetute | PARZIALE | Navigazione e interazioni ripetute senza blocchi osservati; B04 errore console corretto e percorso mensile ripetuto. Nessuna misura p95 affidabile disponibile nel browser integrato. |
| F02 | Prestazioni | Fino a dieci fasi/requisiti realistici e alternative: nessun blocco; ricerca/filtri/paginazione e riesecuzione dopo errore | PARZIALE | Browser: storico 47 appuntamenti, ricerca/filtri/paginazione. Test: pacchetto 10 fasi e 11 rifiutate, alternative senza blocco; nessun benchmark UI di 10 fasi. |
| F03 | Carico | Distinguere contesa verificata in locale da carico multiutente sul server di produzione; prove mancanti esplicite | UTENTE | Infrastruttura di produzione assente; capacità, TLS, worker/cron, database concorrente e prova di carico restano aperti (§ 5). |

## Criteri di chiusura

Nessun difetto critico/alto aperto su accessi, isolamento, sovrapposizioni, dati, prenotazioni o invii. Difetti minori descritti e tracciati. Per ogni caso: esito reale oppure limite e istruzioni precise all'utente. Suite automatica e controlli richiesti rieseguiti dopo correzioni. Questo collaudo locale non attesta capacità o sicurezza dell'infrastruttura di produzione non disponibile.

## Registro esecuzione

### Avvio e primo percorso cliente

Ambiente separato seminato; web, coda e SMTP finto attivi. La richiesta anonima a `/app/bookings` porta al login. Modulo login vuoto fermato dalla validazione del browser; account inesistente rifiutato. Sei tentativi attivano una pagina 429.

**Difetto B01 (minore, corretto):** con lingua italiana, credenziali errate mostrano `These credentials do not match our records.` e il limite mostra `Too Many Requests`, senza istruzioni sul tempo d'attesa.

Cliente: portale e calendario ottobre visibili, weekend non selezionabili, tre operatori e scelta indifferente; scelta Giulia, 6 ottobre. Cambio Roma → New York: 09:00 → 03:00, modulo con entrambi i fusi. Modulo vuoto fermato; consenso mancante rifiutato dal server. Prenotazione inviata con nome `=COLLAUDO <script>alert(1)</script>` e note HTML: conferma ottenuta, nome mostrato come testo e nessun dialogo JavaScript. Copia link corretta e download ICS riuscito. Screenshot `scratchpad/collaudo-cliente-20261001.jpg`.

Il download QR è stato cliccato, ma il browser integrato non ha restituito l'evento download entro il limite: verifica da completare, non segnato come passato.

### Manager, pacchetti, risorse, gruppi e operatore

Creati realmente dal manager: cabina con 10 minuti di preparazione e riassetto; due servizi individuali da 30 minuti, rispettivamente €30/€50; pacchetto con pausa di 20 minuti (€80, durata 80 minuti); gruppo con capienza 2 e sala dedicata. Domanda obbligatoria con un'opzione contenente script. Duplicati e operatore non abilitato a tutte le fasi rifiutati. Riordino delle fasi e salvataggio riusciti.

Pacchetto prenotato con Giulia il 6 ottobre 09:00–10:20. Conferma manuale ereditata dalla prima fase, un solo modulo e programma corretto. Approvazione manager visibile al cliente. Dettaglio agenda: cabina 08:50–09:40 per A, sala 1 09:50–10:30 per B; domanda ostile mostrata come testo. Screenshot manager e pacchetto salvati.

Due moduli già aperti su A, stesso orario 11:00 ma operatori Giulia/Luca: prima richiesta accettata, seconda rifiutata con «Quell'orario è stato appena occupato». Tre moduli già aperti per il gruppo del giorno 8 alle 09:00: due confermati nella medesima sessione con Giulia, terzo rifiutato. Dopo un annullamento resta «ultimo posto disponibile»; dopo l'ultimo tornano due posti e gli orari 09:15/09:30. Si tratta di invii consecutivi su dati obsoleti, non di un benchmark di richieste realmente simultanee.

Lista d'attesa cliente salvata e visibile al manager. Ricerca `' OR 1=1 --` nell'agenda: nessun risultato, nessun errore. CSV effettivamente scaricato in Downloads (il browser integrato non segnala l'evento): nome che inizia con `=` neutralizzato con apostrofo, note multilinea/virgolette e risposta inclusi correttamente.

Giulia: pagine servizi, risorse, clienti, staff, portale e registro negate con 403; prenotazioni e report personali disponibili. Appuntamento «Cliente solo Luca» non compare nella sua agenda. Conferma di una propria richiesta riuscita. Blocco con fine prima dell'inizio rifiutato; blocco personale 9 ottobre 09–10 salvato: Giulia inizia alle 10, Luca conserva gli orari dalle 09. Motivo riservato assente nel pubblico. Cambio lingua/fuso, conferma password errata/corretta e selezione tema riusciti; preferenze lingua/fuso ripristinate.

Disattivare cabina con prenotazioni future oppure inserire manutenzione sul suo orario viene rifiutato. Manutenzione del 12 ottobre 09–10 salvata. Nota cliente interna salvata; script in nomi, domande, note risorse e biografia sempre mostrati come testo. Registro contiene creazioni/conferme/spostamenti/annullamenti con riferimenti, senza note dei clienti.

Viewport effettivamente misurato 390 px: conferma pubblica e agenda manager, larghezza documento 375 px senza overflow globale. Screenshot conservati. Non equivale alla prova di un telefono fisico.

**Difetto B02 (minore, corretto):** composizione duplicata del pacchetto mostra identificatori tecnici e messaggi inglesi; errore stampato due volte. Anche il rifiuto di disattivare la risorsa ha messaggio duplicato.

**Difetto B03 (medio, corretto):** richiesta in attesa offriva link calendario e ICS con `STATUS:CONFIRMED`; anche una email di spostamento della richiesta allegava il file. Ora la pagina conserva link/QR di gestione ma nasconde calendari finché non approvata; download diretto pending restituisce 404; email pending non allegano ICS. Tre nuovi test hanno prima riprodotto i tre errori e poi sono passati. File mirato: 18 test, 102 asserzioni. Riesame browser completato: richiesta in attesa senza link calendario; download diretto ICS rifiutato con 404.

Suite prima della correzione: 593 totali, 591 passati, 2 saltati, 1.739 asserzioni.




### Secondo portale, eliminazione, storico e nuovi percorsi

Il secondo portale fittizio è stato creato e impersonato: agenda, risorse e clienti vuoti; URL di Studio Rossi rifiutati. La sospensione dei portali e la disabilitazione di Marco sono state provate con sessioni già aperte. Dopo riattivazione l'accesso funziona. Con approvazione dell'utente sono stati eliminati realmente il portale esterno e il relativo manager, senza orfani.

È stata anonimizzata realmente la cliente fittizia `browser.cliente@collaudo.test`: prenotazione già annullata, nome/email/note rimossi; appuntamento storico conservato. Questi interventi hanno interessato soltanto il database di collaudo.

Per gli appuntamenti passati è stata preparata, con approvazione, una seconda copia: `storage/app/private/collaudo-storico-20261001.sqlite`, porta **8011**, cookie distinto. Lo script già presente `scratchpad/seed-giro5.php` ha generato lo storico fittizio. Totale 47 appuntamenti. Provati nel browser gli esiti passati del manager e dell'operatore e l'assenza di tali azioni sui futuri/annullati. Retention impostata a 30 giorni sulla copia: comando di anonimizzazione ha trattato 7 appuntamenti, poi 0 alla ripetizione; paginazione dell'agenda mostra «Cliente cancellato» e mantiene il totale. Completamento automatico: 2, poi 0. Promemoria: 1, poi 0; messaggio intercettato e orario corretto. Per rendere idoneo il promemoria è stata anticipata la data di creazione di un solo appuntamento fittizio esistente sulla copia. Potature eseguite senza righe idonee: nessuna prova positiva nel browser. I due job falliti seminati su questa copia sono fixture, distinti dagli errori reali risolti nell'ambiente principale.

Nel database principale è stato creato con approvazione **Operatore Collaudo**, `operatore.browser@collaudo.test`, assegnato alla Prima consulenza. Accesso personale con password generata riuscito; impostata disponibilità lunedì 09–18. Cliente ha prenotato lunedì 5 ottobre 09–09:30; operatore vede il proprio appuntamento. Il manager non può eliminarlo finché ha l'impegno futuro: pulsante disabilitato e motivo visibile. La password generata non viene riportata nelle evidenze.

Creato il servizio gratuito **Solo Giulia collaudo** con quattro domande obbligatorie. Cliente prenotato per il 26 ottobre: Roma 09:00, Kolkata 13:30, URL calendario 08:00 UTC dopo il cambio solare. Risposte breve/lunga/singola/multipla lette realmente nel dettaglio agenda; script mostrato come testo. Chiusura con Escape restituisce il focus alla prenotazione. Orari sovrapposti rifiutati; modificare la settimana non altera l'appuntamento già preso e mostra l'avviso. Chiusure del portale 21–22 ottobre e chiusura personale Giulia il 20 ottobre escludono i giorni corretti, senza mostrare ragioni private.

### Ripresa: ulteriori prove browser e B05/B11–B13

Chiusi otto casi prima PARZIALE: M05/M06/O04/C10/R01/R02/S03/M11. Apertura sabato creata/rimossa senza esporre la nota; annullamento manager motivato visibile al cliente e in `scratchpad/mailbox-collaudo-20261001/20261001-213344-f01ef8.eml`. Profilo Giulia salvato, logout/login sulla copia 8011, persistenza e ripristino preferenze; ricerche %/_ letterali.

Pagina ospite `scratchpad/collaudo-embed/index.html` servita temporaneamente sulla porta 8012: snippet fornito dal manager, con titolo accessibile dell'iframe. Cliente iframe collaudo prenota Prima consulenza con Luca il 13 ottobre 10:00–10:30, restando dentro il sito ospite; conferma, agenda e mail `20261001-215954-c458b5.eml` coerenti. Console senza errori. Header/footer sono nascosti sulla prima pagina embed e visibili nelle successive; il percorso funziona interamente nel frame. Server ospite fermato a fine prova; per ripetere: `php -S 127.0.0.1:8012 -t scratchpad/collaudo-embed`.

Creati strumento (5/5 minuti), seconda cabina (10/10) e servizio con due requisiti. Strumento inattivo elimina gli slot, riattivazione li ripristina. Prima cabina occupata da Giulia: Luca 6 ottobre 11:00 ottiene cabina alternativa 10:50–11:40 e strumento 10:55–11:35; letti in agenda, note interne assenti al cliente. Questa prenotazione fittizia resta confermata.

Doppia iscrizione `duplica.attesa@collaudo.test`/maiuscole riproduce B13. Dopo fix, `NORMA.ATTESA@collaudo.test`/minuscole producono una sola nuova riga. Sessione fittizia creata/annullata per liberare posto: due email per due indirizzi distinti, una sola ai duplicati precedenti. Evidenze `20261001-213941-e867cc.eml` e `20261001-213941-ccc037.eml` nella mailbox locale. Entrambe le vecchie righe hanno stesso ultimo avviso; conservate come evidenza, non nuove duplicazioni dopo fix.

Riprovati titolo Sicurezza, conteggi e nomi dei comandi; IT→EN→IT traduce anche copia link. Escape chiude modale e riporta focus al menu riga. Nessun nuovo errore nella console manager; Boost mostra solo B04 delle 19:02 UTC, già corretto e non considerato regressione recente.

### Difetti trovati e risultato della ripetizione

| ID | Gravità / problema | Correzione e verifica |
|---|---|---|
| B01 | Minore: login/reset/limite/upload in inglese | Traduzioni italiane e istruzioni del limite; browser ripetuto e test su login, reset e upload. |
| B02 | Minore: errori duplicati e nomi tecnici nei pacchetti/risorse | Attributi leggibili, messaggio unico; test e nuovo rifiuto nel browser. |
| B03 | Media: richiesta in attesa esportata come confermata | Calendari/ICS disponibili dopo conferma; route pending 404 e nessun allegato pending. Tre regressioni e prova browser. |
| B04 | Minore: vista mese genera `ReferenceError: __ is not defined` | Tooltip tradotto dal server; test e nuova apertura mese/giorno senza il nuovo errore. Le vecchie righe console delle 19:02 UTC non sono nuovi guasti. |
| B05 | Minore, **CORRETTO**: conteggio gruppo, titolo Sicurezza, nomi dei comandi, avviso dietro modale | trans_choice, titolo tradotto, etichette per menu/rimozioni/ricerca/selettore operatore. Protezione ultimo operatore ora errore inline, azzerato al cambio azione. Browser e regressioni IT/EN riusciti; nessuna attestazione WCAG. |
| B06 | Una vulnerabilità alta, una media e una bassa nelle dipendenze | Aggiornamenti autorizzati a Laravel **13.30** e CommonMark **2.10.3**, senza altri aggiornamenti. Audit Composer e npm: zero segnalazioni; suite completa ripetuta. |
| B07 | Alta funzionale: notifica lista d'attesa fallisce in coda | Collisione fra proprietà pubblica `slot` del mailable e variabile della vista: dato passato come `openingAt`, mantenendo compatibilità dei job già serializzati. Test di serializzazione/render, coda riavviata, «Riprova» dal browser e .eml ricevuto; disiscrizione riuscita. |
| B08 | Media: risposta reset distingue email esistente/inesistente | Risposte web/JSON uniformate, mantenendo invio/token del broker. Sei casi automatici e richieste reali nel browser. Non verificata l'equivalenza dei tempi di risposta. |
| B09 | Minore: errore obbligatorietà stampato due volte nelle domande | Rimossa la seconda stampa; quattro tipi testati. Multipla scelta vuota nel browser ora mostra un solo errore; compilazione successiva riuscita. |
| B10 | Minore: admin mostra durata vecchia del pacchetto dopo modifica componente | Riepilogo usa la configurazione aggiornata; regressione e ricarica reale: 1 h 35 min, coerente con il pubblico. |
| B11 | Minore: etichette Flux congelate nella lingua della prima compilazione | Fold/memo congelavano __(); override locali di cinque template con etichette, rendering compilato conservato; nome aggiunto alla chiusura notifiche. Regressioni EN→IT prima fallite, poi suite/browser verdi. Nessun vendor o dipendenza modificato. |
| B12 | Minore: tabella senza accesso diretto ai dettagli delle prenotazioni senza note | Azione Dettagli nel menu riga, tramite showBooking autorizzato; assente nel menu del dettaglio stesso. Test e apertura reale delle allocazioni riusciti. |
| B13 | Medio: maiuscole email duplicano iscrizioni e notifiche lista d'attesa | Normalizzazione prima della validazione, riuso dei dati precedenti entro servizio/portale. Invio raggruppato per indirizzo, silenzio 12 ore condiviso. Quattro regressioni e browser: iscrizione unica, avviso unico. Vecchie righe/token conservati; nessuna bonifica automatica del database. |

Regole durevoli registrate con Boost in `.ai/rules/mail.md`, `.ai/rules/app.md`, `.ai/rules/flux.md` e `.ai/rules/portal.md`.

### Verifiche finali

- Suite: `php -d memory_limit=1G vendor/bin/pest --compact` → **634 passati, 2 saltati, 1.891 asserzioni, 38,67 secondi**. Totale 636: 43 casi in più rispetto all'avvio, 19 aggiunti in questa ripresa.
- I due saltati sono i test di registrazione pubblica, intenzionalmente disabilitata in questa applicazione; non sono due funzionalità provate con successo.
- I difetti con modifiche al comportamento sono stati prima riprodotti con test mirati, poi ricontrollati dopo la correzione.
- `php vendor/bin/pint --dirty --format agent` → riuscito.
- `php vendor/bin/phpstan analyse --memory-limit=1G` → **0 errori**.
- `npm run build` → riuscita; resta l'avviso preesistente del fallback font Fontaine opzionale.
- `composer audit` e `npm audit` → **0 vulnerabilità segnalate** dopo i due aggiornamenti approvati.
- Primo tentativo della suite completa con limite PHP predefinito 128 MB: memoria esaurita; ripetizione con 1 GB riuscita. Il limite della macchina di collaudo non è stato nascosto come esito verde del primo tentativo.
- Il SMTP finto accetta una connessione alla volta: worker persistente e invio sincrono sulla stessa porta possono contenderselo. Due errori iniziali dovuti al timeout del simulatore sono stati riprovati con successo; non attribuiti al prodotto. Questo simulatore non misura capacità/concorrenza di un provider SMTP.

## Interventi dell'utente: istruzioni operative

Usare esclusivamente gli account fittizi e le porte di collaudo. **Non eseguire reset/migrazioni distruttive sul database originale.** Le pagine e i server locali funzionano finché i processi della sessione restano attivi; se dopo un riavvio non rispondono, chiedere di riaprire l'ambiente dedicato, senza passare alla porta 8000. Segnare per ogni passo: PASSATO/DIFETTO, messaggio visto, ora e screenshot privo di password/codici.

### 1. SMTP proprio nel browser — istruzioni lasciate su richiesta

**Manca:** inserimento nuove credenziali e ciclo configurazione/prova/guasto/fallback nel browser. La policy del controllo del browser impone intervento umano prima dell'inserimento di nuove credenziali; non basta l'autorizzazione a collaudare. Nessuna credenziale è stata inserita al tuo posto. I percorsi del trasporto sono coperti dai test automatici.

1. Se intendi fare anche il reset password del § 2, fallo prima di questa configurazione: entrambe le prove possono usare il SMTP finto 1028, che gestisce una sola connessione alla volta.
2. Apri [pagina admin Studio Rossi di collaudo](http://127.0.0.1:8010/admin/portals/studio-rossi). La scheda è lasciata aperta. Se richiede accesso: `admin@digithub.test`, password di seed `password` (solo account locale fittizio).
3. Nel riquadro SMTP verifica **server 127.0.0.1, porta 1028, mittente collaudo@digithub.test**. Inserisci personalmente **nome utente e password inventati**, senza credenziali personali. Scegli **Nessuna** come cifratura: vale soltanto per il simulatore locale.
4. Premi **Salva impostazioni**. Il solo salvataggio non certifica il server: deve riuscire la prova.
5. Nel modulo di prova usa `smtp.prova@collaudo.test` e premi il comando di invio. Atteso: prova riuscita/stato verificato. Apri il nuovo file in `scratchpad/mailbox-collaudo-storico-20261001`: destinatario di prova e intestazione `X-Fake-Mail-From: collaudo@digithub.test`. Il nome della cartella dipende dall'etichetta del simulatore, non dal database del messaggio.
6. Esegui una nuova prenotazione fittizia nel portale 8010. Atteso: email del portale nella stessa cartella; nessun invio esterno. Il worker 8010 è già configurato per le email della piattaforma su porta 1027.
7. Per errore connessione cambia solo la porta a **1029**, dove non è predisposto alcun server, salva e invia una prova. Atteso: errore leggibile e SMTP proprio non verificato. Prenota nuovamente: invio tramite piattaforma nella cartella `scratchpad/mailbox-collaudo-20261001`, nessuna prenotazione persa.
8. Ripristina **1028**, salva e ripeti la prova: torna verificato e le nuove email usano il server del portale. Se il worker mantiene una connessione aperta al simulatore, fermarlo/riavviarlo prima della prova sincrona; posso predisporre questo passaggio quando riprendi.

Per autenticazione e mittente rifiutati, avvia in due terminali separati, dalla radice del progetto, i simulatori già presenti:

```powershell
php -d default_socket_timeout=3600 scratchpad/fake-smtp.php 1030 collaudo-auth --reject-auth
```

```powershell
php -d default_socket_timeout=3600 scratchpad/fake-smtp.php 1031 collaudo-mittente --reject-sender=digithub.test
```

Nel medesimo modulo cambia la porta prima a **1030**, poi a **1031**, lasciando le credenziali fittizie e il mittente precedente; salva e invia la prova ogni volta. Attesi: errore di autenticazione nel primo caso, rifiuto mittente nel secondo, server proprio non verificato. Dopo ogni fallimento controlla una nuova prenotazione e la mail di fallback su 1027. Alla fine ripristina 1028 e prova riuscita. Questi due guasti sono **da eseguire**, non risultati osservati in questa sessione.

### 2. Nuova password, reset, autenticatore e passkey

**Manca:** il ciclo che richiede una nuova credenziale, il telefono/autenticatore e il dispositivo di autenticazione. La stessa policy impone che inserimento, conferma e invio della nuova credenziale siano eseguiti da te. Usa preferibilmente la **copia storica 8011**, così il cambio password non interferisce con l'ambiente principale.

1. Apri Chrome/Edge sulla copia 8011. Accedi come `giulia@digithub.test`, password di seed `password`. Nelle impostazioni **Sicurezza** prova password attuale errata: salvataggio rifiutato; poi inserisci attuale corretta, nuova password scelta da te e conferma. Salva, esci, verifica che la vecchia venga rifiutata e la nuova accettata.
2. Esci e apri [recupero password sulla copia](http://127.0.0.1:8011/forgot-password); richiedi il reset per Giulia. Apri l'ultima email di reset in `scratchpad/mailbox-collaudo-storico-20261001`, segui il link e inserisci personalmente nuova password/conferma. Atteso: accesso con nuova password, precedente rifiutata. Non usare un vecchio file .eml per il caso positivo.
3. Riapri lo stesso link già utilizzato e prova a salvare: rifiutato, password corrente invariata. Per token errato altera un carattere del token nella barra indirizzi e invia il modulo: rifiutato. Per scadenza richiedi un nuovo link, **non usarlo**, attendi oltre **60 minuti** e prova: rifiutato. Non cambiare l'orologio del computer. Sono prove ancora da completare.
4. [Sicurezza sulla copia](http://127.0.0.1:8011/settings/security): abilita 2FA, conferma password, inquadra QR con l'autenticatore. Codice errato rifiutato, corretto conferma. Salva i codici di recupero in un luogo tuo, senza inserirli nel rapporto.
5. Esci e accedi: richiesta del secondo fattore; codice errato rifiutato, codice attuale valido accettato. Esci ancora e usa un codice di recupero: funziona una volta; al riuso deve essere rifiutato.
6. In Chrome/Edge registra una passkey seguendo il prompt del tuo dispositivo/Windows Hello, confermando tu password/PIN/biometria. Esci, prova accesso con passkey e annullamento del prompt. Poi revoca la passkey di prova e verifica che non dia più accesso. Se il browser/Windows Hello non la supporta, registra esattamente quel limite, senza dichiarare passato.
7. A fine prove conserva un modo di accesso valido alla sola utenza fittizia; ripristina le preferenze desiderate. Non inviare password, QR 2FA, PIN o codici di recupero in chat.

### 3. QR, file calendario e telefono reale

**Manca:** download QR non restituito dal browser integrato, scansione e importazione nelle applicazioni reali.

1. In Chrome/Edge apri la [prenotazione fittizia confermata](http://127.0.0.1:8010/p/studio-rossi/b/peydbozoyfrzpuoez9xuzpw3nwbvtrzx), lasciata anche nel browser integrato. È Solo Giulia, lunedì 26 ottobre, **09–09:30 Roma / 13:30–14 Kolkata**, prezzo €0.
2. Premi **Scarica QR**: atteso file PNG scaricato e apribile. Decodificalo con il telefono: deve contenere esattamente il link della prenotazione. Un telefono interpreta `127.0.0.1` come sé stesso: può confrontare il testo del link ma non aprire il sito del PC. L'apertura da telefono richiede un ambiente di staging raggiungibile; non esporre il server locale automaticamente.
3. **Altri calendari (.ics)**: apri/importa il file in un calendario di prova. Attesi servizio/operatore corretti, data 26 ottobre 2026, intervallo **08:00–08:30 UTC** (09–09:30 Roma); nessuna nota interna o risposta riservata.
4. Prova anche **Google Calendar**, **Outlook** e, se disponibile, **Apple Calendar**. Accedi tu ai tuoi account, usa un calendario di prova, senza invitare terzi. Prima di salvare verifica stesso giorno/orario/durata. Importare un evento è un'operazione volontaria: non dimostra una sincronizzazione automatica.
5. Su telefono reale nello staging prova elenco, calendario, campi, tastiera email, modulo, spostamento e annullamento, orientamenti verticale/orizzontale. Annotare dispositivo/browser. Il controllo a 390 px già svolto non sostituisce questa prova.

### 4. Email su provider reale

**Manca:** credenziali/provider/DNS e ricezione esterna. Il simulatore locale non verifica spam, TLS o autenticazione del dominio.

1. In staging configura personalmente un SMTP reale di prova e l'indirizzo mittente consentito dal provider; usa i parametri e la cifratura indicati dal suo pannello.
2. Pubblica/verifica nel DNS **SPF, DKIM e DMARC** usando i valori forniti dal provider; non copiare valori inventati.
3. Invia la prova soltanto a caselle tue; poi prenotazione confermata, richiesta/approvazione, spostamento, annullamento, promemoria e lista d'attesa. Controlla inbox/spam, mittente, Reply-To, destinatari separati, contenuto, programma e allegati.
4. Nelle intestazioni della mail verifica i risultati SPF/DKIM/DMARC; apri link e ICS in Gmail/Outlook reali. In attesa di approvazione non devono esserci allegati/link che segnano confermata.
5. Per guasto reale coordina una credenziale SMTP di prova non valida sullo staging, senza interrompere gli invii di produzione; verifica errore, fallback e ripristino. Inserimento credenziali e invii a caselle esterne restano sotto il tuo controllo.

### 5. Infrastruttura e carico

**Manca:** server di produzione (SQLite in WAL su disco locale, deciso il 2026-10-04), TLS, worker continuo/cron, misure attendibili e richieste realmente simultanee.

1. Predisporre uno staging con le stesse versioni/configurazione della produzione e soli dati/account fittizi, usando il database effettivamente previsto. Verificare HTTPS, debug disabilitato, file privati non scaricabili, worker coda e le sei attività mostrate da `php artisan schedule:list --no-interaction`.
2. Provare backup e ripristino su una copia; verificare accesso, appuntamenti, fasi e risorse ripristinati. Conservare la stessa chiave applicativa per poter leggere le credenziali SMTP cifrate.
3. Ripetere a invio simultaneo: due operatori sulla stessa cabina; due richieste sull'ultimo posto; spostamento contro prenotazione sulla medesima risorsa. Attesi al massimo una assegnazione/posto disponibile, nessun dato parziale, nessuna email duplicata.
4. Eseguire carico progressivo, ad esempio 10/25/50 clienti fittizi concorrenti, su elenco → calendario → modulo → invio; comprendere pacchetto di 10 fasi con alternative. Registrare p50/p95, errori, timeout, 429 attesi e saturazione di database/worker. Concordare il tempo massimo accettabile prima di giudicare PASSATO: non è stato definito uno SLA.
5. Non dirigere il carico verso produzione o servizi email reali. Le due/tre schede con moduli obsoleti già provate verificano il ricontrollo degli slot, non attestano questa capacità.

### 6. Ripetizioni mirate per chiudere i casi PARZIALE

Le parti non ripetute manualmente sono indicate riga per riga nella matrice; molte sono già verificate dai test. Se vuoi chiudere ogni caso anche come prova UI, usare una nuova copia dei dati e registrare le singole azioni, senza modificare i dati originali:

| Casi | Passi aggiuntivi / risultato atteso |
|---|---|
| A01, O03, S01, S04, S07 | Ripetere gli accessi anonimi/operatore a ogni pagina privata e gli URL esterni sul secondo portale: accesso negato. Payload alterati e limiti nei test; proxy solo su staging autorizzato. Ricerche S03 già chiuse nel browser. |
| M02, M03, M08 | In Servizi provare gli estremi di durata/preavviso/prezzo e valori mancanti: rifiuto senza salvataggio parziale. Prenotare con tutti i tipi di domande, poi modificare/eliminare una domanda: storico invariato. Riprenotare con la stessa email in maiuscole: stessa anagrafica, senza duplicati. |
| M05, M06, O04 | **Già ripetuti e chiusi**, non restano a carico dell'utente: apertura sabato, annullamento manager motivato, logout/login dopo modifica profilo. |
| M09, M10, M12 | Confrontare report filtrato e CSV contando appuntamenti/stati/importi noti; provare nomi con +, -, @ in calendario/export. Salvare logo JPG, sostituire/rimuovere e ricaricare; SVG/falso/oversize rifiutati. Iframe M11 già provato con prenotazione reale su pagina ospite locale. |
| R03–R06, P04, P05 | Cabine alternative e requisito cabina E strumento già provati. Restano varianti UI di manutenzione e intervalli che si toccano; pacchetto con cabine alternative fra fasi, prep/riassetto e rifiuto integrale del conflitto finale. Contesa simultanea reale nel § 5. |
| P02, P07, P08, G03 | Provare singola fase/duplicati/gruppo/pacchetto annidato: rifiuto. Operatore qualificato solo per una fase: rifiuto. Tentare spostamento verso conflitto nell'ultima fase: appuntamento precedente identico. Tentare eliminazione componente occupato e aggiunta operatore già impegnato alla sessione: protezione e messaggio visibile. |
| C09 | Importare lo stesso appuntamento DST nei calendari del § 3. C10 già ripetuto: iscrizione unica con maiuscole/minuscole e avviso unico dopo B13. |
| D03, D04 | Attendere la scadenza dell'impersonificazione: sessione torna admin, permessi coerenti. Eliminazione di portale con dati complessi richiede prima un portale fittizio dedicato e conferma puntuale: storico delle altre attività intatto, nessun orfano. |
| E04 | Su copia con fixture vecchie idonee, eseguire le potature previste e verificarne l'effetto; ripetere per idempotenza. Il giro corrente ha verificato davvero completamento, reminder, anonimizzazione e Riprova, ma non potatura positiva. |
| U01, U03, F01, F02 | B05/B11 corretti e riprovati; resta revisione completa IT/EN e percorso con lettore di schermo su form/modali/menu. Controlli nominati e raggiungibili da tastiera. Cronometrare navigazioni e pacchetto 10 fasi in staging; capacità nel § 5. |

## Evidenze e ripartenza

![Prenotazione reale dentro il sito ospite](C:/ARCHIVIO/AI/digithub-connect/scratchpad/collaudo-iframe-confermato.jpg)

![Cabina alternativa e strumento assegnati realmente](C:/ARCHIVIO/AI/digithub-connect/scratchpad/collaudo-cabina-alternativa.jpg)

![Avviso quando si tenta di togliere l'ultimo operatore](C:/ARCHIVIO/AI/digithub-connect/scratchpad/collaudo-gruppo-avviso-corretto.jpg)

- Nuove evidenze in scratchpad: `collaudo-sabato-aperto.jpg`, `collaudo-annullamento-manager.jpg`, `collaudo-profilo-persistente.jpg`, `collaudo-profilo-persistente-ripristino.jpg`.

![Prenotazione reale di collaudo confermata](C:/ARCHIVIO/AI/digithub-connect/scratchpad/collaudo-conferma-finale.jpg)

![Accesso del nuovo operatore e disponibilità personali](C:/ARCHIVIO/AI/digithub-connect/scratchpad/collaudo-operatore-accesso.jpg)

- Evidenze UI in `scratchpad/collaudo-*.jpg`: cliente, manager, pacchetto, operatore, mobile, quattro domande, anonimizzazione, eliminazione portale, esiti passati, retention e nuovo staff.
- Ultimo catalogo pacchetto: `scratchpad/collaudo-pacchetto-finale.jpg`, A 45 min + pausa 20 + B 30, totale 95 min/€85. Il prezzo specifico €70 è stato provato e poi ripristinata la somma.
- SMTP da completare: `scratchpad/collaudo-smtp-da-completare.jpg`; i campi host/porta/mittente sono preparati ma **non salvati come nuova configurazione autenticata**.
- Notifica lista d'attesa realmente ricevuta dopo correzione: `scratchpad/mailbox-collaudo-20261001/20261001-193047-ac8058.eml`. Altri messaggi nelle due cartelle mailbox; restano evidenze storiche anche quando l'appuntamento è stato successivamente annullato.
- CSV scaricato: `C:/Users/loren/Downloads/studio-rossi-prenotazioni-2026-10-01.csv`.
- Account originali di seed riportati in `LOG.md`. Il nuovo operatore e le nuove prenotazioni restano solo nel database separato 8010. Secondo portale eliminato; appuntamenti iniziali cliente/pacchetto annullati. Non usare quei link annullati per un nuovo collaudo ICS positivo.
- Schede lasciate: manager (dettaglio cabina alternativa/strumento), admin SMTP e prenotazione confermata per QR/calendari. Dimensioni normali, temporanee chiuse.
- A completamento delle prove manuali, rieseguire la suite completa: `php artisan test --compact`. Se il limite PHP predefinito di questa macchina esaurisce la memoria, usare il comando da 1 GB riportato sopra; aggiornare esiti e Diario, senza dichiarare chiusi i casi non ripetuti.
