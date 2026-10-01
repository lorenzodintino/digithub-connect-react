# Giro 5 — foglio dei ritrovamenti

Banco allestito il 2026-09-09 con `scratchpad/seed-giro5.php`. L'applicazione gira su
`http://127.0.0.1:8000`. Password di tutti gli account: `password`.

Non è una checklist di funzionamento: quella è nei Giri 1–4, già chiusi. Qui si giudica
**come si legge e come si usa** ciò che funziona.

## Le cinque domande

Su ogni schermata, sempre le stesse:

1. **5 secondi.** Guardi e togli lo sguardo: sai dire cos'è questa pagina e qual è l'azione principale?
2. **Testi.** Suonano scritti in italiano o tradotti dall'inglese? Gergo interno che un cliente non ha?
3. **Attrito.** Quanti passaggi per il compito principale? Ce n'è qualcuno di superfluo?
4. **390 px.** `Ctrl+Shift+M` → iPhone 12. Tabelle, barre filtri, modali, form a due colonne.
5. **Errori.** Dicono **cosa fare**, o solo cosa è andato storto?

## Come annotare

Una riga per ritrovamento, anche brutta. Servono le parole esatte che hai letto a schermo:
sono quelle che poi vanno cambiate.

| # | Schermata | Cosa aspettavi | Cosa hai visto | Gravità |
|---|---|---|---|---|
| 1 |  |  |  |  |

Gravità: `blocca` (non si capisce / non si riesce) · `stona` (si capisce ma è scritto male) · `nota` (idea per dopo).

---

## Seduta A — il cliente, passo per passo

Finestra **anonima**, nessun login. Aggiornata il 2026-09-09 con i tre interventi
della sessione: posti in esaurimento, righe che spiegano i giorni grigi, fuso orario.

### Prima: i tre controlli di stasera

**1.** `/p/studio-rossi/corso-base-di-contabilita` → freccia `›` fino a **ottobre** → il **13**.
Pallino **arancione**, legenda «Un giorno in arancione è quasi pieno», e cliccandolo
la fascia dice **«· ultimi 2 posti»** in arancione. Poi torna a **settembre, giorno 10**:
4 e 8 posti liberi, tutto grigio, nessun allarme.

**2.** `/p/studio-rossi/firma-digitale` → `›` fino a **ottobre**. Sotto il calendario due righe:
«Si prenota fino al 9 ottobre» e «Chiuso dal 14 al 20 ottobre».

**3.** *Prima consulenza* → un giorno → nella tendina **Fuso orario** metti **America/New_York**.
Gli orari scalano di sei ore, compare «Il portale lavora sull'ora di Europe/Rome», e
scegliendo uno slot il modulo mostra **due** orari: il tuo e quello del portale.
Rimetti Europe/Rome prima di continuare.

### Poi il flusso

**4.** `/p/studio-rossi` — cinque secondi, poi togli lo sguardo. Sai dire di cosa si occupa
questo studio e cosa devi fare? Cinque servizi (il sesto è nascosto): prezzi, durate e
luoghi si leggono?

**5. Prima consulenza.** Tre operatori con orari diversi, parte su «Indifferente».
La frase «Ti verrà assegnato chi è libero all'orario che scegli» ti fida o ti mette in dubbio?
Scegli **Giulia**: il calendario si restringe ai suoi orari.

**6.** Torna su «Indifferente», scegli un giorno, guarda la colonna degli orari.

**7. Il modulo** — il punto più delicato. Nome, email, telefono, note, tre domande
personalizzate («Partita IVA» facoltativa, «Come ci hai trovato?» obbligatoria a scelta,
«Di cosa vuoi parlare?» testo lungo) e il consenso privacy.
- si vede quali campi sono obbligatori **prima** di sbagliare?
- quanti campi separano il cliente dal pulsante di conferma? Sono troppi?
- il consenso privacy si capisce, o è una casella da spuntare al buio?

**8. Invia il modulo vuoto.** Gli errori dicono *cosa fare* o solo cosa manca?
Sono in cima, in fondo o accanto ai campi?

**9.** Prova **Cambia orario**: torni agli slot senza perdere il giorno?

**10.** Compila e conferma. La pagina verde contiene tutto quello che ti servirebbe se
fossi il cliente?

**11.** Dalla conferma: **copia il link** e aprilo in scheda nuova. **Scarica il QR**.
Scarica il file `.ics`.

**12. Sposta** la prenotazione dal link. Rivedi il calendario nuovo, e link e QR devono
restare **identici**.

**13. Annulla** dal link, con motivo. Pagina rossa, e lo slot torna libero sul servizio.

**14.** *Consulenza fiscale* → in fondo «Avvisami quando si libera un posto». Compila.
Ha già tre iscritti dietro le quinte: la conferma dice cosa succederà e quando?

**15.** *Apertura partita IVA* — conferma manuale. Dopo l'invio si capisce che **non** è
confermata e che aspetterai una risposta?

**16.** `/p/studio-rossi?embed=1` — spariscono intestazione e piè di pagina, il resto regge.

**17.** Lingua **inglese** dal piè di pagina, e rifai in breve il passo 7: modulo, domande
personalizzate ed errori sono tradotti o restano frasi italiane?

**18. 390 px** — `Ctrl+Shift+M`, iPhone 12. Cinque schermate: elenco servizi, pagina
servizio (calendario **e** lista orari affiancati: è il punto che rischia di rompersi),
modulo, conferma, link di gestione.

## Seduta B — l'operatore

`giulia@digithub.test`

| Schermata | Indirizzo |
|---|---|
| Dashboard | `/dashboard` |
| Prenotazioni | `/app/bookings` |
| Report | `/app/reports` |
| Disponibilità | `/app/availability` |
| Profilo | `/settings/profile` |

Giulia ha storico su due mesi, un blocco orario il **05/10** e ferie il **12/10**.

- Report: i numeri si capiscono senza spiegazioni? Si capisce che sono **suoi** e non del portale?
- Prenotazioni: fra passate, annullate, completate e assenti, il badge di stato basta a orientarsi?
- Disponibilità: la differenza fra orari settimanali, eccezioni e blocchi è chiara in pagina, o la sai solo perché l'hai scritta tu?

## Seduta C — il manager

`manager@digithub.test`

| Schermata | Indirizzo |
|---|---|
| Servizi (6, uno nascosto) | `/app/services` |
| Staff | `/app/staff` |
| Prenotazioni + esportazione | `/app/bookings` |
| Clienti (8, due con note) | `/app/customers` |
| Lista d'attesa (3) | `/app/waiting-list` |
| Report | `/app/reports` |
| Impostazioni portale | `/app/portal` |
| Registro azioni | `/app/activity` |
| Disponibilità di un altro | `/app/availability` |

È la seduta dell'attrito. Il **form di un servizio** ha durata, buffer prima e dopo, intervallo,
preavviso **in minuti**, finestra di prenotabilità, prezzo, colore, tipo di luogo, conferma
manuale, capienza e operatori: qualcuno di questi campi si spiega da sé?

- Le due **richieste da confermare** (*Apertura partita IVA*): si vedono a colpo d'occhio, o vanno cercate col filtro?
- Report: passa da «Questo mese» a «Mese scorso». I numeri si leggono? Il confronto è utile o solo presente?
- Registro azioni: una riga si capisce da sola? Le due righe `job.failed` cosa comunicano al manager?
- Impostazioni portale: il riquadro delle email dice chi manda la posta oggi?

## Seduta D — l'admin

`admin@digithub.test`

| Schermata | Indirizzo |
|---|---|
| Portali | `/admin/portals` |
| Dettaglio portale | dalla riga di *Studio Rossi* |
| Account | `/admin/users` |
| Piani | `/admin/plans` |
| Coda (2 job falliti veri) | `/admin/queue` |

- Coda: le due righe dicono quale email non è arrivata e a chi? Si capisce cosa fare, oltre a «Riprova»?
  (I payload sono autentici: «Riprova» funziona davvero e in sviluppo la mail finisce in `storage/logs/laravel.log`.)
- Piani: si capisce che i limiti **non sono ancora applicati**?
- Dettaglio portale: il riquadro SMTP è comprensibile per chi non l'ha scritto?

## Passata E — gli errori a comando

Provocali, non aspettarli. Per ognuno: la frase dice **cosa fare**?

| Fai questo | Dove |
|---|---|
| Invia il modulo pubblico vuoto | `/p/studio-rossi` → un servizio |
| Lascia in bianco la domanda obbligatoria | *Prima consulenza* |
| Salva due fasce sovrapposte | `/app/availability` |
| Elimina un operatore con prenotazioni future | `/app/staff` → Giulia |
| Carica un PDF come logo, poi un JPG da 2 MB | `/app/portal` |
| Scrivi un indirizzo email nel campo «Server SMTP» | `/admin/portals` → Studio Rossi |
| Vai su `/app/bookings` da admin | a mano nella barra indirizzi |

## Telefono vero

Vite non serve gli asset a un telefono in LAN, quindi prima `npm run build`, poi:

```powershell
php artisan serve --host=0.0.0.0 --port=8000
(Get-NetIPAddress -AddressFamily IPv4 | Where-Object InterfaceAlias -notmatch 'Loopback').IPAddress
```

Dal telefono sulla stessa Wi-Fi: `http://<IP>:8000/p/studio-rossi`. Se non risponde è il firewall
di Windows sulla porta 8000.

---

## Cosa contiene il banco di prova

| Cosa | Quanto |
|---|---|
| Prenotazioni | 33 — 13 completate, 2 assenti, 4 annullate, 2 richieste in attesa, 3 passate ancora «Confermata» |
| Storico | due mesi indietro, giorni lavorativi, su tutti e tre gli operatori |
| Servizi | 6: uno di gruppo (8 posti, 4 presi), uno con un solo operatore, uno nascosto, uno a conferma manuale |
| Domande personalizzate | 3 su *Prima consulenza*, 1 a scelta multipla sul corso |
| Clienti | 8, due con note del portale |
| Lista d'attesa | 3, una già avvisata |
| Chiusura portale | 14–20 ottobre 2026 |
| Blocchi ed eccezioni | blocco di Giulia 05/10, ferie 12/10, mezza giornata di Luca 13/10 |
| Orari diversi | Luca tiene lo sportello serale martedì e giovedì 18–20 |
| Job falliti | 2, con payload autentico |

Per rifare il banco da zero:

```powershell
php artisan migrate:fresh --seed
php artisan tinker scratchpad/seed-giro5.php
```
