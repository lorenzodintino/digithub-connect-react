# Guida all'integrazione con DigitHub License Manager

Questa guida è scritta per lo sviluppatore (umano o agente AI) che deve modificare un software SaaS perché funzioni con le licenze gestite da **DigitHub License Manager** (d'ora in poi «il License Manager»). Contiene tutto il necessario: come funzionano le licenze, cosa deve fare il software, il riferimento completo dell'API ed esempi pronti da copiare.

> **Per un agente AI:** leggi tutta la guida prima di modificare il progetto. Le sezioni 3 (requisiti) e 9 (checklist) sono il contratto da rispettare; la sezione 7 contiene un modulo di riferimento completo da adattare allo stack del progetto. Non inventare endpoint o campi che non sono descritti qui: l'API ha **due endpoint, entrambi in sola lettura** (`/api/v1/license` e `/api/v1/plans`).

---

## 1. Panoramica: come funzionano le licenze

### 1.1 Chi fa cosa

| | License Manager | Software integrato |
|---|---|---|
| Ruolo | Fonte unica della verità su clienti, licenze, scadenze e pagamenti. | Chiede la licenza del proprio cliente e decide cosa permettergli. |
| Chi lo usa | Solo l'amministratore, da un pannello web. | I clienti finali. |
| Cosa decide | Se la licenza è valida (`access.allowed`) e perché. | Cosa bloccare, cosa mostrare, quali funzioni abilitare in base al piano. |
| Scrive dati sulle licenze | Sì (emissione, rinnovo, sospensione, revoca, pagamenti). | **No.** L'API è in sola lettura. |

Il License Manager **restituisce dati, non impone comportamenti**: è il software che, con le informazioni ricevute, sblocca o blocca le funzioni.

### 1.2 I concetti

```
Software (codice univoco, es. CRM_CANTIERI)
 ├── Tipi di piano (es. Base, Avanzato, Pro), ciascuno con codice stabile, listino per durata e attributi
 ├── Attributi (limiti e funzioni che un piano può includere, es. max_services, resources)
 ├── Chiavi API (una o più, per il server del software)
 └── Tenant (un account cliente DENTRO il software, identificato da externalId)
        └── appartiene a un Cliente (anagrafica: nome, email, telefono…)
        └── ha al massimo UNA Licenza non revocata
               ├── Tipo di piano attuale (Base / Avanzato / Pro…)
               ├── Inizio e scadenza (o «a vita»)
               ├── Periodi: emissione e rinnovi, ognuno con il suo importo
               └── Pagamenti (anche annullati, che non contano nel saldo)
```

- **Software** — ogni prodotto dell'amministratore è registrato nel License Manager con un **codice univoco** (2–40 caratteri, maiuscole, cifre, `_` e `-`, per esempio `CRM_CANTIERI`). Ha uno stato: `DRAFT` (Bozza), `ACTIVE` (Attivo), `DISABLED` (Disattivato), `CANCELLED` (Cancellato). L'API risponde solo per software in Bozza o Attivo.
- **Chiave API** — una chiave segreta (`lk_` + 43 caratteri) creata nel pannello per **un solo software**. Identifica il software chiamante: con una chiave si leggono solo i tenant di quel software.
- **Cliente** — l'anagrafica (persona o azienda) che paga. Un cliente può avere account in più software.
- **Tenant** — l'**account del cliente dentro il tuo software** (l'organizzazione, lo spazio di lavoro, l'azienda registrata…). È identificato da un **`externalId`**: l'identificativo che **il tuo software ha già generato** per quell'account. Il License Manager **non genera** questo identificativo: l'amministratore lo copia dal tuo software e lo registra nel pannello, collegandolo al cliente. L'`externalId` è unico **per software**: lo stesso valore può esistere in due software diversi senza conflitti.
- **Licenza** — appartiene a un tenant di un software. Ha un tipo di piano, una data di inizio, una data di scadenza (oppure è a vita) e uno stato (`ACTIVE`, `SUSPENDED`, `REVOKED`). Un tenant ha **al massimo una licenza non revocata**; la revoca è definitiva.
- **Tipo di piano** — Base, Avanzato, Pro… definiti per software. Il piano attuale della licenza è quello dell'ultimo rinnovo: con un rinnovo il cliente può cambiare piano. Ha un **`code`** stabile (es. `pro`), che non cambia mai anche se il nome viene modificato: è l'identificativo da usare nel software.
- **Attributo** — un limite (numero) o una funzione (sì/no) che un piano può includere, per esempio `max_services` o `resources`. Si definiscono per software nel pannello; ogni tipo di piano ha un valore per ciascuno, oppure prende il valore predefinito. **Il License Manager è l'unica fonte dei piani**: nomi, ordine, prezzi, descrizioni e attributi si cambiano solo lì, e il software li legge (§4.6).
- **Tolleranza** — ogni software ha dei giorni di tolleranza (default 7, da 0 a 90). Dopo la scadenza, per quei giorni la licenza **resta valida** (`allowed: true`, motivo `grace`): è il tempo per rinnovare senza interruzioni.

### 1.3 Ciclo di vita di una licenza

```
emessa con inizio futuro ──► NOT_STARTED (allowed: false)
        │ arriva il giorno di inizio
        ▼
      ACTIVE ──► EXPIRING_14 ──► EXPIRING_7 ──► scadenza ──► IN_GRACE ──► fine tolleranza ──► PAST_GRACE
   (allowed)       (allowed)        (allowed)                  (allowed)                       (allowed: false)
        │                                                          │
        └──────────── rinnovo: la scadenza si sposta avanti ◄───────┘ (anche da PAST_GRACE)

in qualunque momento:  sospesa  → SUSPENDED (allowed: false), riattivabile
                       revocata → REVOKED   (allowed: false), definitiva
licenza a vita:        LIFETIME (allowed), nessuna scadenza
```

Regole di tempo (già calcolate dal License Manager, **il software non deve ricalcolarle**):

- Una licenza vale dalla mezzanotte, ora italiana, del giorno di inizio.
- La scadenza è un istante (`expiresAt`): da quell'istante in poi la licenza è scaduta (eventualmente in tolleranza).
- `graceEndsAt` è il primo istante **dopo** la tolleranza: da lì `allowed` diventa `false`.
- Rinnovo prima della fine della tolleranza: il nuovo periodo parte dalla vecchia scadenza. Rinnovo dopo: parte dal giorno del rinnovo.

---

## 2. Il flusso completo, dall'iscrizione allo sblocco

```
 Cliente            Software integrato                      Amministratore            License Manager
    │  si registra        │                                       │                          │
    │────────────────────►│ crea l'account con un tenant id       │                          │
    │                     │ stabile, es. "org_8f3a2c"             │                          │
    │                     │ chiede la licenza ───────────────────────────────────────────────►│ 404 tenant_not_found
    │  vede «licenza non  │◄─────────────────────────────────────────────────────────────────│
    │  attiva, ID org_…»  │                                       │                          │
    │  comunica l'ID ─────┼──────────────────────────────────────►│                          │
    │                     │                                       │ crea cliente + tenant ──►│
    │                     │                                       │ (externalId = org_8f3a2c)│
    │                     │                                       │ emette la licenza ──────►│
    │                     │ chiede di nuovo ─────────────────────────────────────────────────►│ 200 allowed: true
    │  software sbloccato │◄─────────────────────────────────────────────────────────────────│ piano "Pro"
```

1. **Una tantum, nel pannello:** l'amministratore registra il software con il suo codice univoco (es. `CRM_CANTIERI`), crea i tipi di piano (Base, Avanzato, Pro) con i prezzi e crea una **chiave API** (*Software e piani → Chiavi API → Nuova chiave*). La chiave si vede una sola volta: va nel `.env` del server del software.
2. **Il cliente si iscrive al software.** Il software crea l'account come fa già e gli assegna un identificativo stabile: questo è il **tenant id** (`externalId`).
3. **Il software chiede la licenza** di quel tenant. Finché l'amministratore non lo ha registrato, la risposta è `404 tenant_not_found`: il software mostra «licenza non attiva» **e l'identificativo dell'account**, così il cliente (o l'amministratore) può comunicarlo.
4. **L'amministratore**, nel pannello: *Clienti → nuovo cliente* (o uno esistente) → *aggiungi tenant* scegliendo il software e incollando l'`externalId` esatto → *Emetti licenza* (tipo di piano, durata, importo, data di inizio).
5. **Alla verifica successiva** il software riceve `200` con `access.allowed: true` e il piano: sblocca le funzioni previste.
6. **Da qui in poi** ogni rinnovo, sospensione, riattivazione, revoca o cambio piano fatto nel pannello è visibile al software alla verifica successiva (dopo la scadenza della sua cache, vedi §3.5).

---

## 3. Requisiti per il software integrato

### 3.1 Il tenant id (`externalId`)

Il software deve avere, per ogni account cliente, un identificativo che:

- **esiste già** nel software (id dell'organizzazione/azienda/workspace) oppure viene creato all'iscrizione e salvato;
- è **stabile e immutabile**: non cambia mai per la vita dell'account (non usare email, nome azienda, slug modificabili);
- è **unico** nel software;
- è lungo da 1 a 160 caratteri, senza caratteri di controllo; gli spazi ai bordi vengono ignorati;
- viene confrontato **esattamente, maiuscole e minuscole comprese** (`Org_1` ≠ `org_1`).

Consigliato: un id opaco con prefisso, per esempio `org_` + UUID o cuid (`org_7f3c9a1e2b`). Se il software ha già un id numerico va bene anche quello (`"1042"`), purché stabile.

**Granularità:** un tenant = un account che paga una licenza. Se nel software un'azienda ha più utenti, il tenant è l'azienda, non il singolo utente. Tutti gli utenti dell'account condividono la stessa licenza.

**Il software deve mostrare il tenant id** in un punto facile da trovare (es. *Impostazioni → Licenza*, o la schermata «licenza non attiva»), con un pulsante «copia», perché l'amministratore deve inserirlo nel License Manager.

### 3.2 Configurazione

Variabili d'ambiente **solo lato server**:

```dotenv
LICENSE_API_URL="https://licenze.example.it"       # indirizzo del License Manager, senza barra finale
LICENSE_API_KEY="lk_…"                              # chiave creata nel pannello per QUESTO software
LICENSE_SOFTWARE_CODE="CRM_CANTIERI"                # facoltativo: codice univoco, controllo di configurazione
LICENSE_CACHE_TTL_SECONDS="300"                     # facoltativo: durata della cache di una risposta valida
LICENSE_OFFLINE_GRACE_HOURS="72"                    # facoltativo: per quanto usare l'ultima risposta se il License Manager non risponde
```

- La chiave **non deve mai** finire nel browser, in un'app mobile, nel codice sorgente, in un repository, nei log o in un messaggio d'errore.
- Se `LICENSE_SOFTWARE_CODE` è impostato, passalo come parametro `software`: se la chiave appartiene a un altro software la risposta è `403 software_mismatch`, così un errore di configurazione si vede subito.
- Senza `LICENSE_API_URL` o `LICENSE_API_KEY` il software deve avviarsi lo stesso, segnalare chiaramente l'errore di configurazione nei log e considerare le licenze non verificabili (vedi §6).

### 3.3 Dove fare la verifica

- **Sempre e solo dal server** del software (backend, API route, middleware). Mai dal frontend.
- Un unico modulo (es. `license-client`) che: chiama l'API, interpreta la risposta, gestisce cache ed errori, ed espone al resto del codice una funzione del tipo `getLicense(tenantId)` → stato già interpretato.
- Il resto del software interroga quel modulo per decidere: accesso all'applicazione, funzioni per piano, banner di avviso.

### 3.4 Cosa fare con la risposta

Regola principale: **usa `data.access.allowed`** per decidere se l'account può usare il software. Non ricalcolare scadenze e tolleranza: il License Manager lo fa già con le regole corrette (fuso orario, tolleranza del software, sospensioni, archiviazioni).

| `access.reason` | `allowed` | Comportamento consigliato nel software |
|---|---|---|
| `active` | sì | Accesso completo secondo il piano. Se `access.state` è `EXPIRING_7` o `EXPIRING_14`, mostra un avviso «la licenza scade il …». |
| `grace` | sì | Accesso consentito con un avviso ben visibile: «licenza scaduta il …, rinnova entro il …» (`graceEndsAt`). |
| `expired` | no | Blocco: schermata «licenza scaduta», contatti per il rinnovo. Consigliato lasciare consultabili/esportabili i dati del cliente. |
| `not_started` | no | «La licenza inizia il …» (`license.startsAt`). |
| `suspended` | no | «Licenza sospesa, contatta l'assistenza». |
| `revoked` | no | «Licenza revocata». |
| `tenant_archived` | no | Come `suspended`: l'account è stato archiviato dall'amministratore. |
| `customer_archived` | no | Come `suspended`: il cliente è stato archiviato. |

Il software può scegliere di tenere una modalità «sola lettura» invece del blocco totale: è una scelta di prodotto, la licenza dice solo se l'uso è consentito.

**Funzioni per piano:** il piano attuale è `data.license.planType`, con il suo `code` stabile (es. `"base"`, `"pro"`) e i suoi **`attributes`**: i limiti e le funzioni che il piano include, già pronti. Il software non deve ricordare nulla di ciò che include ogni piano: legge gli attributi e li applica.

```ts
const { attributes } = data.license.planType;
// Un attributo mancante o sconosciuto vale «non incluso»: false, o il limite più restrittivo.
const can = (key: string) => attributes[key] === true;
const limit = (key: string): number | null => {            // null = illimitato
  const value = attributes[key];
  if (value === null) return null;
  return typeof value === "number" ? value : 0;
};
```

- Un attributo numerico a **`null` è «illimitato»**; `0` è un limite di zero. Non confonderli con una chiave assente, che va trattata come «non incluso».
- **Riconosci il piano dal `code`, mai dal `name`**: il nome può essere cambiato nel pannello in qualsiasi momento, il codice no. Un `code` che il software non conosce va registrato nei log; i suoi `attributes` valgono comunque.
- Se il License Manager non risponde si usa l'ultima risposta valida, con i suoi `attributes` (§6).

### 3.5 Cache e frequenza delle chiamate

- Limite: **120 richieste al minuto per chiave**, condiviso da tutti i tenant del software. **Non** chiamare l'API a ogni richiesta HTTP del software.
- Tieni in cache **per tenant** l'ultima risposta `200` per qualche minuto (consigliato 5 minuti: un cambiamento fatto nel pannello è visibile al massimo dopo quel tempo).
- Anche le risposte `404 tenant_not_found` / `no_license` possono stare in cache, ma per poco (es. 60 secondi), così l'attivazione appena fatta dall'amministratore si vede quasi subito. Offri anche un pulsante «Verifica di nuovo la licenza» che salta la cache.
- Conserva l'**ultima risposta valida** (in memoria o nel database del software, insieme a `checkedAt`) per usarla se il License Manager non risponde (§6).

### 3.6 Dati personali

La risposta contiene dati del cliente (nome, email, telefono, data di nascita) e i suoi pagamenti. Usali solo se servono (es. mostrare il saldo all'amministratore dell'account), non metterli nei log, non inviarli al browser se non necessario.

---

## 4. Riferimento API

### 4.1 Richiesta

```
GET {LICENSE_API_URL}/api/v1/license?tenant={externalId}[&software={codice}]
Authorization: Bearer {LICENSE_API_KEY}
```

| Parametro | | Descrizione |
|---|---|---|
| `tenant` | obbligatorio | L'`externalId` del tenant. Da 1 a 160 caratteri dopo il trim. **Va codificato nell'URL** (`encodeURIComponent`, `urllib.parse.quote`…). |
| `software` | facoltativo | Codice univoco (es. `CRM_CANTIERI`) o id del software. Deve coincidere con il software della chiave, altrimenti `403 software_mismatch`. Confronto esatto, maiuscole comprese. |

- Solo `GET`. Gli altri metodi ricevono `405` con header `Allow: GET`.
- La chiave si accetta **solo** nell'header `Authorization: Bearer …`, mai nell'URL.
- Non ci sono header CORS: l'API non è chiamabile dai browser di altri siti, ed è voluto.
- In produzione l'indirizzo è HTTPS.

### 4.2 Risposta di successo (`200`)

Esempio completo e reale nella forma (valori di esempio):

```json
{
  "data": {
    "checkedAt": "2026-11-11T09:30:00.000Z",
    "access": {
      "allowed": true,
      "reason": "active",
      "state": "ACTIVE",
      "expiresAt": "2027-01-15T00:00:00.000Z",
      "graceEndsAt": "2027-01-22T00:00:00.000Z",
      "graceDays": 7
    },
    "license": {
      "id": "cmwb1a2b30001ab12cd34ef56",
      "status": "ACTIVE",
      "startsAt": "2026-01-15T00:00:00.000Z",
      "expiresAt": "2027-01-15T00:00:00.000Z",
      "lifetime": false,
      "createdAt": "2026-01-14T16:20:11.402Z",
      "updatedAt": "2026-01-14T16:20:11.402Z",
      "planType": {
        "id": "cmwb0p1an0003ab12cd34ef56",
        "code": "pro",
        "name": "Pro",
        "prices": [
          { "months": 1, "priceCents": 3500, "currency": "EUR", "active": true },
          { "months": 12, "priceCents": 35000, "currency": "EUR", "active": true },
          { "months": null, "priceCents": 120000, "currency": "EUR", "active": false }
        ],
        "attributes": { "max_services": 15, "resources": true, "max_packages": null }
      }
    },
    "periods": [
      {
        "id": "cmwb1a2b30002ab12cd34ef56",
        "kind": "ISSUE",
        "source": "MANUAL",
        "planType": { "id": "cmwb0p1an0003ab12cd34ef56", "code": "pro", "name": "Pro" },
        "months": 12,
        "amountCents": 35000,
        "previousExpiresAt": null,
        "newExpiresAt": "2027-01-15T00:00:00.000Z",
        "createdAt": "2026-01-14T16:20:11.402Z",
        "corrections": []
      }
    ],
    "balance": { "dueCents": 35000, "paidCents": 35000, "outstandingCents": 0, "currency": "EUR" },
    "payments": [
      {
        "id": "cmwb1c9pq0004ab12cd34ef56",
        "amountCents": 35000,
        "currency": "EUR",
        "paidAt": "2026-01-20T00:00:00.000Z",
        "note": "Bonifico",
        "voidedAt": null,
        "voidReason": null,
        "createdAt": "2026-01-20T08:02:45.118Z"
      }
    ],
    "customer": {
      "id": "cmwb0c5st0005ab12cd34ef56",
      "kind": "COMPANY",
      "name": "Edil Rossi Srl",
      "firstName": null,
      "lastName": null,
      "contactName": "Luca Bianchi",
      "email": "info@edilrossi.example",
      "billingEmail": "amministrazione@edilrossi.example",
      "phone": "+39 0412345678",
      "taxCode": "01234567897",
      "vatNumber": "01234567897",
      "sdiCode": "ABC1234",
      "pec": null,
      "address": { "street": "Via Garibaldi 10", "postalCode": "30100", "city": "Venezia", "province": "VE", "country": "IT" },
      "birthDate": null,
      "archived": false,
      "archivedAt": null,
      "createdAt": "2026-01-14T16:05:30.000Z"
    },
    "tenant": {
      "id": "cmwb0t3nt0006ab12cd34ef56",
      "externalId": "org_7f3c9a1e2b",
      "archived": false,
      "archivedAt": null,
      "createdAt": "2026-01-14T16:06:02.000Z"
    },
    "software": {
      "id": "cmwb0s0ft0007ab12cd34ef56",
      "code": "CRM_CANTIERI",
      "name": "CRM Cantieri",
      "description": "Gestione cantieri e squadre",
      "status": "ACTIVE",
      "graceDays": 7
    }
  }
}
```

### 4.3 Significato dei campi

Convenzioni: **date** in ISO 8601 UTC; **importi** in centesimi interi (`35000` = 350,00 €); `null` dove non c'è un valore. Tutti i campi sono sempre presenti.

**`access`** — la risposta pronta all'uso:

| Campo | Tipo | Descrizione |
|---|---|---|
| `allowed` | boolean | **Il campo da usare per sbloccare o bloccare.** |
| `reason` | string | `active`, `grace`, `expired`, `not_started`, `suspended`, `revoked`, `tenant_archived`, `customer_archived` (vedi §3.4). |
| `state` | string | Stato fine: `ACTIVE`, `EXPIRING_14` (scade entro 14 giorni), `EXPIRING_7` (entro 7), `IN_GRACE`, `PAST_GRACE`, `LIFETIME`, `NOT_STARTED`, `SUSPENDED`, `REVOKED`. Utile per gli avvisi. Con tenant o cliente archiviato `state` resta quello della licenza, ma `allowed` è `false`. |
| `expiresAt` | string \| null | Scadenza; `null` se a vita. |
| `graceEndsAt` | string \| null | Primo istante dopo la tolleranza; `null` se a vita. |
| `graceDays` | number | Giorni di tolleranza del software. |

**`license`** — la licenza **corrente** del tenant: l'unica non revocata; se sono tutte revocate, la più recente (in quel caso `allowed: false`, `reason: "revoked"`).

| Campo | Descrizione |
|---|---|
| `status` | Stato salvato: `ACTIVE`, `SUSPENDED`, `REVOKED`. Per decidere usa `access`, non questo. |
| `startsAt`, `expiresAt`, `lifetime` | Inizio, scadenza (`null` se a vita), `true` se a vita. |
| `planType.code` | **Identificativo stabile del piano** (`pro`): usalo per riconoscerlo. Non cambia mai. |
| `planType.name` | Nome del piano (Base, Avanzato, Pro…): per mostrarlo, non per riconoscerlo (può essere cambiato). |
| `planType.prices` | Listino del piano: una riga per durata (`months: null` = a vita), `active: false` = non più in vendita. Utile per mostrare «rinnova a …». |
| `planType.attributes` | I limiti e le funzioni del piano, per chiave: `true`/`false`, o un intero (`null` = illimitato). Contiene **tutte** le chiavi definite per il software. Anche `periods[].planType` ha il `code`, ma non gli `attributes`: valgono solo per il piano attuale. |

**`periods`** — emissione (`kind: "ISSUE"`) e rinnovi (`"RENEWAL"`) in ordine di data, ognuno con piano, durata, importo concordato e scadenze prima/dopo; `corrections` contiene le correzioni dell'importo. `voidedAt` e `voidReason` sono valorizzati quando l'amministratore annulla un rinnovo fatto per errore: il periodo resta nell'elenco ma non conta nel saldo, e `license.expiresAt` e `access` sono già tornati ai valori precedenti. Per un periodo valido sono `null`. Non dedurre lo stato corrente dall'ultimo periodo: usa `access` e `license`. `source` è `MANUAL` (`AUTOMATIC` è riservato a un uso futuro).

**`balance`** — `dueCents` (somma dei periodi), `paidCents` (pagamenti non annullati), `outstandingCents` (da incassare; negativo = credito).

**`payments`** — tutti i pagamenti, **anche annullati** (`voidedAt` valorizzato, non contano nel saldo), in ordine di data.

**`customer`**, **`tenant`**, **`software`** — anagrafica del cliente, dati del tenant (`externalId` è quello inviato), dati del software della chiave. Nel cliente: `kind` è `PERSON` (privato), `COMPANY` (azienda) o `null` (cliente registrato prima dei dati di fatturazione); `name` è il nome da mostrare (ragione sociale o «nome cognome»); `firstName`/`lastName` per i privati, `contactName`, `vatNumber`, `sdiCode`, `pec` per le aziende; `taxCode`, `address` (`street`, `postalCode`, `city`, `province`, `country` ISO a due lettere), `email`, `billingEmail` (dove vanno avvisi e fatture, se diversa) e `phone` per tutti. Ogni campo può essere `null`.

### 4.4 Errori

Formato unico: `{ "error": { "code": "…", "message": "…" } }`. Il `code` è stabile e va usato nel codice; il `message` è in italiano, per le persone.

| HTTP | `code` | Quando | Cosa deve fare il software |
|---|---|---|---|
| 400 | `invalid_request` | `tenant` mancante, vuoto, oltre 160 caratteri o con caratteri di controllo. | Bug del software: registra l'errore. Non bloccare per questo motivo senza indagare. |
| 401 | `unauthorized` | Chiave mancante, malformata, sconosciuta o revocata (stessa risposta in tutti i casi). Header `WWW-Authenticate: Bearer`. | Errore di configurazione: allarme nei log, usa l'ultima risposta valida (§6). Non riprovare a raffica. |
| 403 | `software_mismatch` | Il parametro `software` non è il software della chiave. | Errore di configurazione (chiave o codice sbagliati). Come 401. |
| 403 | `software_inactive` | Software Disattivato o Cancellato nel pannello. | Tutti gli account senza licenza valida: blocco, come `suspended`. |
| 404 | `tenant_not_found` | Nessun tenant con quell'`externalId` **in questo software**. | Account non ancora attivato: «licenza non attiva» + tenant id da comunicare. |
| 404 | `no_license` | Il tenant esiste ma non ha mai avuto licenze. | Come sopra: «licenza non ancora emessa». |
| 405 | `method_not_allowed` | Metodo diverso da GET. | Bug del software. |
| 426 | `https_required` | Chiamata in http con il License Manager configurato per esigere HTTPS. | Correggi `LICENSE_API_URL` in `https://`. La chiave è passata in chiaro: chiedi all'amministratore di revocarla e crearne una nuova. |
| 429 | `rate_limited` | Troppe richieste (120/minuto per chiave, o troppi fallimenti dallo stesso indirizzo). Header `Retry-After` in secondi. | Aspetta i secondi indicati; nel frattempo usa la cache. Rivedi la cache (§3.5). |
| 500 | `server_error` | Errore interno del License Manager. | Come «non raggiungibile» (§6). |

Ogni risposta ha `Cache-Control: no-store`: la cache la gestisce il software, non proxy intermedi.

### 4.5 Limiti e sicurezza

- 120 richieste al minuto per chiave (tutte le chiamate del software, di tutti i tenant, **di entrambi gli endpoint insieme**).
- 20 autenticazioni fallite in 10 minuti dallo stesso indirizzo portano a `429` (quando il License Manager è dietro un proxy configurato), anch'esse contate su entrambi gli endpoint.
- Una chiave appartiene a un solo software e non può leggere i tenant di altri software: un tenant di un altro software risponde `404 tenant_not_found`, esattamente come uno inesistente.
- **Rotazione della chiave** senza interruzioni: l'amministratore crea una nuova chiave, si aggiorna il `.env` del software e si riavvia, poi si revoca la vecchia. Una chiave revocata riceve subito `401`.

### 4.6 Catalogo dei piani (`GET /api/v1/plans`)

I piani di un software si definiscono **solo** nel pannello del License Manager: nomi, ordine, listino, descrizione e attributi (limiti e funzioni di ciascun piano). Il software li legge da qui, ne tiene una copia locale e da quella ricava la vetrina, i limiti e i blocchi per funzione. Non ha una propria lista di piani da modificare a mano.

```
GET {LICENSE_API_URL}/api/v1/plans[?software={codice}]
Authorization: Bearer {LICENSE_API_KEY}
```

Stessa autenticazione, stesso limite di richieste e stessi errori dell'endpoint `/license` (`401`, `403 software_mismatch`, `403 software_inactive`, `429`, `5xx`; non ci sono `tenant_not_found` e `no_license`). Solo `GET`. Il software è quello della chiave. **Nessun dato di clienti o tenant.**

Risposta `200` (esempio ridotto; l'esempio completo con i tre piani di Atrio è in [esempio-catalogo-atrio.md](./esempio-catalogo-atrio.md)):

```json
{
  "data": {
    "software": { "code": "ATRIO", "name": "Atrio" },
    "updatedAt": "2026-11-18T09:30:00.000Z",
    "attributes": [
      { "key": "max_services", "type": "integer", "label": "Servizi", "default": 0 },
      { "key": "resources", "type": "boolean", "label": "Risorse", "default": false }
    ],
    "plans": [
      {
        "id": "cmwb0p1an0003ab12cd34ef56",
        "code": "pro",
        "name": "Pro",
        "description": "Per uno studio con più persone e un giro di prenotazioni costante.",
        "position": 2,
        "active": true,
        "prices": [
          { "months": 1, "priceCents": 3900, "currency": "EUR", "active": true },
          { "months": 12, "priceCents": 39000, "currency": "EUR", "active": true }
        ],
        "attributes": { "max_services": 15, "resources": true }
      }
    ]
  }
}
```

| Campo | Descrizione |
|---|---|
| `attributes` (in alto) | Le definizioni degli attributi del software: `key`, `type` (`boolean` o `integer`), `label`, `default`. |
| `plans[].code` | Identificativo stabile del piano: minuscole, cifre e `_`, 2–40 caratteri, unico nel software, **non modificabile**. Riconosci i piani da questo. |
| `plans[].name`, `description` | Per mostrarli. Il nome può cambiare, la descrizione può essere `null`. |
| `plans[].position` | Ordine di presentazione: intero crescente da 1; il piano «più grande» ha la posizione più alta. |
| `plans[].active` | `false` = non più in vendita. **Il piano resta comunque nell'elenco**: chi ha già una licenza su quel piano continua ad averlo. |
| `plans[].prices` | Listino: una riga per durata, anche non attive; `months: null` = a vita; importi in centesimi interi. |
| `plans[].attributes` | **Tutte** le chiavi definite, con il valore del piano o, se non ne ha uno, il predefinito. `true`/`false` o un intero; **`null` = illimitato**. |
| `updatedAt` | Ultima modifica di qualsiasi piano, prezzo o attributo. |

**Regola fondamentale: un attributo mancante o sconosciuto vale «non incluso»** (`false` per le funzioni, il limite più restrittivo per i numeri). Aggiungere attributi nuovi nel pannello non può quindi aprire per sbaglio funzioni a chi non le ha pagate, e un software non ancora aggiornato non scopre funzioni che non conosce.

**Cache con `ETag`.** Ogni risposta ha un header `ETag`. Rimandalo in `If-None-Match`: se il catalogo non è cambiato la risposta è `304` senza corpo, così il software può controllare spesso (per esempio con un comando pianificato ogni pochi minuti) senza costi. Anche il `304` richiede una chiave valida e conta nel limite di richieste.

**Come usarlo:**

- Un comando pianificato legge `/api/v1/plans` e aggiorna la tabella locale dei piani, **per `code`**: nome, ordine, listino, descrizione, in vendita, attributi. I piani spariti dal catalogo non si cancellano: si marcano non in vendita.
- Il piano di un tenant arriva dalla licenza (`license.planType.code`) e si applica con i suoi `attributes`: `license.planType` contiene già gli attributi, quindi funziona anche quando il catalogo non è raggiungibile.
- Se il License Manager non risponde, il software continua con l'ultima copia ricevuta e avvisa gli amministratori.
- Attributo rimosso dal pannello = chiave che sparisce dal catalogo: il software la tratta come «non inclusa».

**Cosa fa l'amministratore, nel pannello** (*Software e piani*): definisce gli attributi del software (sezione **Attributi dei piani**: chiave, tipo, nome, valore predefinito), poi imposta i valori per ogni tipo di piano da **Dettagli e attributi** nella colonna del piano (campo vuoto = predefinito). Chiave e tipo di un attributo, e il `code` di un tipo di piano, non si cambiano dopo la creazione; i tipi di piano creati prima dell'introduzione dei codici hanno un codice generato dal nome, correggibile **una sola volta**.

---

## 5. Esempi di chiamata

### curl (Linux, macOS, Git Bash)

```bash
curl -sS \
  -H "Authorization: Bearer $LICENSE_API_KEY" \
  "$LICENSE_API_URL/api/v1/license?tenant=org_7f3c9a1e2b&software=CRM_CANTIERI"
```

Con un tenant id che contiene caratteri speciali, codificalo: `--get --data-urlencode "tenant=Org 42/A"`.

### PowerShell (Windows)

```powershell
$headers = @{ Authorization = "Bearer $env:LICENSE_API_KEY" }
$tenant = [uri]::EscapeDataString("org_7f3c9a1e2b")
Invoke-RestMethod -Headers $headers -Uri "$env:LICENSE_API_URL/api/v1/license?tenant=$tenant&software=CRM_CANTIERI"
```

### Python (requests)

```python
import os, requests

def read_license(tenant_id: str) -> dict:
    response = requests.get(
        f"{os.environ['LICENSE_API_URL']}/api/v1/license",
        params={"tenant": tenant_id, "software": os.environ.get("LICENSE_SOFTWARE_CODE")},
        headers={"Authorization": f"Bearer {os.environ['LICENSE_API_KEY']}"},
        timeout=5,
    )
    body = response.json()
    if response.ok:
        return {"ok": True, "license": body["data"]}
    return {"ok": False, "status": response.status_code, "error": body["error"]["code"]}
```

### PHP

```php
function read_license(string $tenantId): array {
    $url = getenv('LICENSE_API_URL') . '/api/v1/license?' . http_build_query(['tenant' => $tenantId]);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 5,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . getenv('LICENSE_API_KEY')],
    ]);
    $raw = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    if ($raw === false) return ['ok' => false, 'error' => 'network_error'];
    $body = json_decode($raw, true);
    return $status === 200 ? ['ok' => true, 'license' => $body['data']] : ['ok' => false, 'status' => $status, 'error' => $body['error']['code'] ?? 'unknown'];
}
```

---

## 6. Quando il License Manager non risponde

Rete assente, manutenzione, `500`, timeout: la decisione è del software, ma **non bloccare subito tutti i clienti**. Comportamento consigliato:

1. Timeout della chiamata breve (5 secondi).
2. Se esiste un'ultima risposta `200` per quel tenant più recente di `LICENSE_OFFLINE_GRACE_HOURS` (es. 72 ore), usa quella (`allowed` come allora) e registra l'errore.
3. Se non c'è, o è più vecchia: per un account che non ha mai avuto una risposta valida, tratta la licenza come non verificata (blocco con messaggio «impossibile verificare la licenza, riprova tra poco»); per gli altri, a scelta del prodotto, blocco o sola lettura.
4. Stessa logica per `401`/`403 software_mismatch` (errore di configurazione): non punire i clienti per un errore di configurazione, ma rendilo molto visibile nei log o in un pannello interno.

Salvare l'ultima risposta valida nel database del software (per tenant: JSON della risposta + `checkedAt`) fa sopravvivere questa protezione ai riavvii.

---

## 7. Implementazione di riferimento (TypeScript / Node 18+)

Modulo completo, senza dipendenze. Va adattato allo stack del progetto (Express, Next.js, NestJS, Fastify…), mantenendone il comportamento.

```ts
// license-client.ts — server only
export type AccessReason =
  | "active" | "grace" | "expired" | "not_started" | "suspended" | "revoked" | "tenant_archived" | "customer_archived";

export type LicenseData = {
  checkedAt: string;
  access: { allowed: boolean; reason: AccessReason; state: string; expiresAt: string | null; graceEndsAt: string | null; graceDays: number };
  license: {
    id: string; status: "ACTIVE" | "SUSPENDED" | "REVOKED"; startsAt: string; expiresAt: string | null; lifetime: boolean;
    createdAt: string; updatedAt: string;
    planType: {
      id: string; code: string; name: string;
      prices: { months: number | null; priceCents: number; currency: string; active: boolean }[];
      /** Tutte le chiavi definite: true/false, o un intero (null = illimitato). */
      attributes: Record<string, boolean | number | null>;
    };
  };
  periods: {
    id: string; kind: "ISSUE" | "RENEWAL"; source: string; planType: { id: string; code: string; name: string }; months: number | null; amountCents: number;
    previousExpiresAt: string | null; newExpiresAt: string | null; createdAt: string;
    corrections: { previousAmountCents: number; newAmountCents: number; reason: string; createdAt: string }[];
  }[];
  balance: { dueCents: number; paidCents: number; outstandingCents: number; currency: string };
  payments: { id: string; amountCents: number; currency: string; paidAt: string; note: string | null; voidedAt: string | null; voidReason: string | null; createdAt: string }[];
  customer: {
    id: string; kind: "PERSON" | "COMPANY" | null; name: string; firstName: string | null; lastName: string | null; contactName: string | null;
    email: string | null; billingEmail: string | null; phone: string | null; taxCode: string | null; vatNumber: string | null;
    sdiCode: string | null; pec: string | null;
    address: { street: string | null; postalCode: string | null; city: string | null; province: string | null; country: string | null };
    birthDate: string | null; archived: boolean; archivedAt: string | null; createdAt: string;
  };
  tenant: { id: string; externalId: string; archived: boolean; archivedAt: string | null; createdAt: string };
  software: { id: string; code: string; name: string; description: string | null; status: string; graceDays: number };
};

/** What the rest of the software uses: already interpreted, never the raw HTTP details. */
export type LicenseCheck =
  | { kind: "licensed"; allowed: boolean; reason: AccessReason; plan: string; data: LicenseData; stale: boolean }
  | { kind: "not_activated"; reason: "tenant_not_found" | "no_license" }   // show the tenant id to communicate
  | { kind: "software_inactive" }
  | { kind: "unavailable"; cause: string };                               // network, 5xx, configuration, rate limit

const URL_BASE = process.env.LICENSE_API_URL?.replace(/\/+$/, "");
const API_KEY = process.env.LICENSE_API_KEY;
const SOFTWARE_CODE = process.env.LICENSE_SOFTWARE_CODE || undefined;
const TTL_MS = Number(process.env.LICENSE_CACHE_TTL_SECONDS ?? 300) * 1000;
const NOT_ACTIVATED_TTL_MS = 60_000;
const OFFLINE_GRACE_MS = Number(process.env.LICENSE_OFFLINE_GRACE_HOURS ?? 72) * 3_600_000;

type CacheEntry = { check: LicenseCheck; until: number };
const cache = new Map<string, CacheEntry>();
/** Last 200 per tenant. Persist it in your database too if the check must survive restarts. */
const lastGood = new Map<string, { data: LicenseData; at: number }>();

export async function getLicense(tenantId: string, options: { fresh?: boolean } = {}): Promise<LicenseCheck> {
  const now = Date.now();
  const cached = cache.get(tenantId);
  if (!options.fresh && cached && cached.until > now) return cached.check;

  const check = await fetchLicense(tenantId, now);
  const ttl = check.kind === "licensed" && !check.stale ? TTL_MS : check.kind === "not_activated" ? NOT_ACTIVATED_TTL_MS : 30_000;
  cache.set(tenantId, { check, until: now + ttl });
  return check;
}

async function fetchLicense(tenantId: string, now: number): Promise<LicenseCheck> {
  if (!URL_BASE || !API_KEY) return fallback(tenantId, now, "configurazione mancante: LICENSE_API_URL o LICENSE_API_KEY");
  const url = new URL("/api/v1/license", URL_BASE);
  url.searchParams.set("tenant", tenantId);
  if (SOFTWARE_CODE) url.searchParams.set("software", SOFTWARE_CODE);

  let response: Response;
  try {
    response = await fetch(url, { headers: { Authorization: `Bearer ${API_KEY}` }, signal: AbortSignal.timeout(5000) });
  } catch {
    return fallback(tenantId, now, "License Manager non raggiungibile");
  }

  let body: { data?: LicenseData; error?: { code: string } } = {};
  try { body = await response.json(); } catch { /* non-JSON answer: handled below */ }

  if (response.ok && body.data) {
    lastGood.set(tenantId, { data: body.data, at: now });
    return licensed(body.data, false);
  }
  const code = body.error?.code ?? `http_${response.status}`;
  switch (code) {
    case "tenant_not_found":
    case "no_license":
      return { kind: "not_activated", reason: code };
    case "software_inactive":
      return { kind: "software_inactive" };
    default:
      // unauthorized, software_mismatch, rate_limited, server_error, https_required, invalid_request…
      console.error(`[license] verifica non riuscita per il tenant: ${code}`); // never log the key
      return fallback(tenantId, now, code);
  }
}

function licensed(data: LicenseData, stale: boolean): LicenseCheck {
  return { kind: "licensed", allowed: data.access.allowed, reason: data.access.reason, plan: data.license.planType.code, data, stale };
}

function fallback(tenantId: string, now: number, cause: string): LicenseCheck {
  const last = lastGood.get(tenantId);
  if (last && now - last.at <= OFFLINE_GRACE_MS) return licensed(last.data, true);
  return { kind: "unavailable", cause };
}

/** Feature gating from the plan's attributes. A missing or unknown attribute means "not included". */
export function hasFeature(check: LicenseCheck, feature: string): boolean {
  if (check.kind !== "licensed" || !check.allowed) return false;
  return check.data.license.planType.attributes[feature] === true;
}

/** Numeric limit of the plan: null = unlimited, 0 when the attribute is missing (the strictest limit). */
export function planLimit(check: LicenseCheck, key: string): number | null {
  if (check.kind !== "licensed" || !check.allowed) return 0;
  const value = check.data.license.planType.attributes[key];
  if (value === null) return null;
  return typeof value === "number" ? value : 0;
}
```

### Uso in un middleware (Express)

```ts
import express from "express";
import { getLicense, hasFeature } from "./license-client";

// After authentication: req.account.tenantId is the stable id of the customer's account.
export async function requireLicense(req: express.Request, res: express.Response, next: express.NextFunction) {
  const check = await getLicense(req.account.tenantId);
  res.locals.license = check;
  if (check.kind === "licensed" && check.allowed) return next();
  if (check.kind === "not_activated") {
    return res.status(402).render("licenza-non-attiva", { tenantId: req.account.tenantId });
  }
  if (check.kind === "unavailable") return res.status(503).render("licenza-non-verificabile");
  return res.status(402).render("licenza-bloccata", { reason: check.kind === "licensed" ? check.reason : "software_inactive" });
}

export function requireFeature(feature: string) {
  return (req: express.Request, res: express.Response, next: express.NextFunction) =>
    hasFeature(res.locals.license, feature) ? next() : res.status(403).render("funzione-non-inclusa", { feature });
}

// app.use("/app", requireAuth, requireLicense);
// app.get("/app/report", requireFeature("report"), reportHandler);
```

### Pagina «Licenza» nel software (dati da mostrare)

- Tenant id con pulsante «copia» (sempre, anche quando la licenza non è attiva).
- Piano (`license.planType.name`; il `code` serve alla logica, il nome è solo per mostrarlo), scadenza (`access.expiresAt` in ora locale, oppure «a vita»), stato in parole (da `access.reason` / `access.state`).
- In tolleranza: «rinnova entro» `access.graceEndsAt`.
- Facoltativo: saldo da pagare (`balance.outstandingCents`) e storico pagamenti.
- Pulsante «Verifica di nuovo» → `getLicense(tenantId, { fresh: true })`.

---

## 8. Cosa NON fare

- Non chiamare l'API dal browser o da un'app: la chiave verrebbe esposta.
- Non mettere la chiave nell'URL, nei log, nei messaggi d'errore o nel repository.
- Non ricalcolare scadenze e tolleranza nel software: usa `access.allowed`.
- Non usare come tenant id un valore che può cambiare (email, nome, slug).
- Non chiamare l'API a ogni richiesta: usa la cache.
- Non bloccare tutti i clienti se il License Manager non risponde per qualche minuto.
- Non aspettarti endpoint per creare tenant, emettere licenze o registrare pagamenti: si fa tutto nel pannello del License Manager.
- Non confrontare il piano con l'id (`planType.id`) copiato da un ambiente: gli id cambiano tra installazioni; usa il `code`.
- Non riconoscere un piano dal `name`: nel pannello si può rinominare. Il `code` no.
- Non tenere nel software una tabella a mano di cosa include ogni piano: i limiti e le funzioni stanno negli `attributes`, definiti nel pannello.
- Non trattare un attributo mancante come «illimitato» o «attivo»: mancante = non incluso. Solo `null` in un attributo numerico è illimitato.

---

## 9. Checklist per l'integrazione

- [ ] Ogni account cliente del software ha un tenant id stabile, unico, ≤160 caratteri, salvato nel database.
- [ ] Il tenant id è visibile e copiabile nella pagina «Licenza» e nella schermata «licenza non attiva».
- [ ] `LICENSE_API_URL`, `LICENSE_API_KEY` (e facoltativi `LICENSE_SOFTWARE_CODE`, `LICENSE_CACHE_TTL_SECONDS`, `LICENSE_OFFLINE_GRACE_HOURS`) letti solo lato server; `.env.example` aggiornato senza valori reali.
- [ ] Un unico modulo server chiama `GET /api/v1/license` con `Authorization: Bearer`, timeout 5 s, tenant codificato nell'URL.
- [ ] Cache per tenant (≈5 minuti per le risposte valide, ≈1 minuto per `tenant_not_found`/`no_license`) e ultima risposta valida conservata.
- [ ] Accesso deciso da `data.access.allowed`; messaggi per ogni `access.reason` (§3.4); avvisi per `EXPIRING_7`/`EXPIRING_14` e `grace`.
- [ ] Piano riconosciuto dal `license.planType.code`; funzioni e limiti letti da `license.planType.attributes`; attributo mancante o sconosciuto = non incluso; `null` in un attributo numerico = illimitato.
- [ ] Catalogo letto da `GET /api/v1/plans` (comando pianificato, con `If-None-Match` / `ETag`), copia locale aggiornata **per `code`**; piani spariti marcati non in vendita, mai cancellati; piani con `active: false` conservati.
- [ ] Con il License Manager non raggiungibile: ultima copia del catalogo e ultima licenza valida, e avviso agli amministratori.
- [ ] Errori gestiti per `code` (§4.4); `429` rispetta `Retry-After`; nessun blocco immediato se il License Manager non risponde (§6).
- [ ] Nessun dato del cliente né la chiave nei log.
- [ ] Test automatici del modulo con risposte simulate: 200 attiva, 200 in tolleranza, 200 scaduta, 200 sospesa, 404 `tenant_not_found`, 401, 429, timeout di rete con e senza ultima risposta valida.

---

## 10. Prova completa (manuale)

1. Nel License Manager: *Software e piani* → software con codice univoco (es. `CRM_CANTIERI`, in Bozza o Attivo) → tipi di piano con prezzi → *Chiavi API → Nuova chiave* → copia la chiave nel `.env` del software.
2. Nel software: crea un account di prova e annota il suo tenant id (es. `org_prova_001`). La pagina «Licenza» deve mostrare «licenza non attiva» e il tenant id (`404 tenant_not_found`).
3. Nel License Manager: *Clienti* → nuovo cliente → aggiungi tenant (software `CRM_CANTIERI`, identificativo `org_prova_001`). Il software, dopo «Verifica di nuovo», mostra «licenza non ancora emessa» (`404 no_license`).
4. *Emetti licenza* (es. piano Pro, 12 mesi, inizio oggi). Il software si sblocca con le funzioni Pro (`200`, `allowed: true`, `reason: active`, `planType.code: "pro"` e i suoi `attributes`).
5. *Sospendi* nel pannello → il software blocca (`suspended`). *Riattiva* → sblocca.
6. Rinnova cambiando piano (es. Base) → il software riduce le funzioni. Cambia anche un attributo del piano nel pannello (es. `max_services`) e verifica che dopo il tempo della cache il software applichi il nuovo valore; rinomina un tipo di piano e verifica che il software lo riconosca ancora (stesso `code`).
7. Con una chiave revocata o sbagliata → `401`: il software non blocca i clienti ma segnala l'errore nei log.
