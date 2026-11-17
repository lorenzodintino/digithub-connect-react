# Prompt 3 — Catalogo dei piani e attributi per piano (da dare allo sviluppatore / agente AI del License Manager)

Copia tutto quello che segue il separatore e consegnalo a chi sviluppa DigitHub License Manager.

---

## Contesto

Il software **Atrio** (gestionale e portale di prenotazione, codice software da registrare nel License Manager) usa già l'API di sola lettura `GET /api/v1/license?tenant={externalId}` descritta in `guida-integrazione-licenze.md`. Oggi quell'API restituisce del piano soltanto `license.planType` con `id`, `name` e `prices` del **piano corrente di quel tenant**.

Decisione di prodotto: **il License Manager diventa l'unica fonte dei piani.** Nomi, ordine, listino, descrizione e anche ciò che ogni piano include (limiti numerici e funzioni attive) si definiscono nel pannello del License Manager. Atrio li legge, li tiene in una tabella locale di copia (`plans`) e da quella tabella leggono la vetrina pubblica, il back-office, i limiti e i blocchi per funzione. Atrio **non** ha più una propria lista di piani da modificare a mano.

Per questo servono due modifiche al License Manager. Non cambia nulla per i software già integrati: tutto è **additivo**.

## Cosa realizzare

### 1. Attributi per piano (nel pannello e nel modello dati)

Ogni **software** ha un elenco di *definizioni di attributo*, ogni **tipo di piano** ha un valore per ciascun attributo.

Definizione di attributo (per software):

| Campo | Note |
|---|---|
| `key` | Stringa stabile, minuscole, cifre e `_`, 2–40 caratteri, **unica nel software**, non modificabile dopo la creazione. |
| `type` | `boolean` oppure `integer`. |
| `label` | Nome leggibile per il pannello. |
| `description` | Facoltativa, per l'amministratore. |
| `default` | Valore usato per un piano che non ne ha uno esplicito (booleano, intero o `null`). |

Regole dei valori:
- `boolean`: `true` o `false`.
- `integer`: intero ≥ 0 oppure `null`, dove **`null` significa «illimitato»**. Va distinto da «non impostato» (che usa il `default`).
- Il pannello deve permettere di impostare gli attributi nel modulo del tipo di piano (una riga per attributo, con interruttore per i booleani e campo numerico con «illimitato» per gli interi) e mostrare i valori nell'elenco dei piani.
- Aggiungere una definizione di attributo non deve rompere i piani esistenti (prendono il `default`). Rimuoverne una è permesso solo se nessuna integrazione ne ha bisogno: basta una conferma esplicita.

### 2. Codice stabile del tipo di piano

Oggi Atrio riconosce il piano dal **nome** (trim + minuscole), quindi rinominare «Pro» nel pannello rompe la mappatura. Aggiungere a ogni tipo di piano un **`code`**: stringa stabile (stesse regole di `key`), unica per software, impostata alla creazione, **non modificabile** (il `name` resta libero di cambiare). I piani esistenti ricevono un `code` generato dal nome normalizzato; l'amministratore può correggerlo una sola volta prima che sia usato.

### 3. Nuovo endpoint: catalogo dei piani del software

```
GET {LICENSE_API_URL}/api/v1/plans[?software={codice}]
Authorization: Bearer {LICENSE_API_KEY}
```

Stessa autenticazione, stesso limite di richieste, stessi errori (`401`, `403 software_mismatch`, `429`, `5xx`) dell'endpoint esistente. Solo `GET`. Il software è quello della chiave. Nessun dato di clienti o tenant.

Risposta `200`:

```json
{
  "data": {
    "software": { "code": "ATRIO", "name": "Atrio" },
    "updatedAt": "2026-11-18T09:30:00.000Z",
    "attributes": [
      { "key": "max_services", "type": "integer", "label": "Servizi", "default": null }
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
        "attributes": {
          "max_services": 15,
          "max_operators": 5,
          "max_bookings_per_month": 1000,
          "max_waiting_list": 5,
          "max_packages": 3,
          "resources": true,
          "waiting_list": true,
          "packages": true,
          "group_services": false,
          "calendar_buttons": true,
          "embed": true,
          "custom_smtp": true
        }
      }
    ]
  }
}
```

- `position`: ordine di presentazione, intero crescente (il piano «più grande» ha la posizione più alta); modificabile nel pannello.
- `active: false` = non più in vendita. **Va comunque incluso**: chi ha già una licenza su quel piano continua ad averlo.
- `attributes` di ogni piano contiene **tutte** le chiavi definite, con il valore esplicito o il `default`. Tutti i campi sono sempre presenti, `null` dove non c'è un valore (come nel resto dell'API).
- Importi in centesimi interi, date ISO 8601 UTC.
- `updatedAt`: ultima modifica di qualunque piano, attributo o listino del software. Se possibile supportare `If-None-Match` / `ETag` e rispondere `304`, così Atrio può chiedere spesso senza costi.

### 4. Estendere la risposta esistente

Nella risposta di `GET /api/v1/license`, dentro `data.license.planType` aggiungere, **senza togliere nulla**:

```json
"planType": {
  "id": "…", "code": "pro", "name": "Pro",
  "prices": [ … ],
  "attributes": { "max_services": 15, "resources": true, "…": "…" }
}
```

e lo stesso `code` dentro `periods[].planType`. In questo modo Atrio riceve in un'unica chiamata piano e attributi del tenant, anche quando il catalogo non è raggiungibile.

## Come Atrio userà i dati (per capire le scelte)

- Un comando pianificato legge `/api/v1/plans` e aggiorna la tabella `plans` (per `code`): nome, ordine, listino, descrizione, in vendita, attributi. I piani spariti dal catalogo non si cancellano, si marcano non in vendita.
- Il piano di un portale arriva dalla licenza (`license.planType.code`) e si applica con i suoi `attributes`.
- **Un attributo mancante o sconosciuto vale «non incluso»** (booleano `false`, limite più restrittivo). Quindi aggiungere attributi nuovi non può aprire per sbaglio funzioni a chi non le ha pagate.
- Se il License Manager non risponde, Atrio continua a usare l'ultima copia ricevuta e avvisa gli amministratori.

## Attributi iniziali da creare per il software Atrio

Booleani: `resources`, `waiting_list`, `packages`, `group_services`, `calendar_buttons`, `embed`, `custom_smtp`.
Interi (`null` = illimitato): `max_services`, `max_operators`, `max_bookings_per_month`, `max_waiting_list` (persone in lista d'attesa per servizio), `max_packages`.

Valori di partenza dei tre piani (indicativi: li decide l'amministratore dal pannello):

| Attributo | Base | Pro | Studio |
|---|---|---|---|
| `max_services` | 3 | 15 | illimitato |
| `max_operators` | 1 | 5 | illimitato |
| `max_bookings_per_month` | 100 | 1000 | illimitato |
| `max_waiting_list` | — | 5 | illimitato |
| `max_packages` | — | 3 | illimitato |
| `resources` | no | sì | sì |
| `waiting_list` | no | sì | sì |
| `packages` | no | sì | sì |
| `group_services` | no | no | sì |
| `calendar_buttons` | no | sì | sì |
| `embed` | no | sì | sì |
| `custom_smtp` | no | sì | sì |

Listino indicativo mensile: Base 19 €, Pro 39 €, Studio 79 €.

## Requisiti non funzionali

- **Retrocompatibilità totale:** i campi nuovi sono aggiunte, nessun campo esistente cambia nome o significato; i software già integrati non devono accorgersi di nulla.
- **Sicurezza:** la chiave resta solo nell'header `Authorization`; il catalogo non contiene dati personali; il nuovo endpoint ha lo stesso rate limit (120/min per chiave) e non è chiamabile dai browser.
- **Validazione:** `code` e `key` con i vincoli indicati; valori degli attributi coerenti col tipo; un tipo di piano non può essere eliminato se ha licenze (comportamento attuale), può essere reso non in vendita.
- **Test automatici:** catalogo con piani in vendita e non; attributo con `default`; attributo `integer` con `null` (illimitato) e con valore; piano senza valore esplicito; `code` non modificabile; risposta di `/license` con `attributes` e `code`; `403 software_mismatch`, `401`, `429` sul nuovo endpoint; `304` con `ETag` se implementato.
- **Documentazione:** aggiornare `guida-integrazione-licenze.md` (nuovo endpoint, nuovi campi, regola «attributo mancante = non incluso», sezione sul `code`) e l'elenco delle checklist di integrazione.

## Da restituire a fine lavoro

1. Il riepilogo delle modifiche (modello dati, pannello, API).
2. La guida di integrazione aggiornata.
3. Un esempio reale di risposta di `/api/v1/plans` e di `/api/v1/license` con i campi nuovi, per i tre piani di Atrio.
