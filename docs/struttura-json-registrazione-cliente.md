# Struttura JSON — registrazione cliente nel License Manager

Un file JSON per ogni portale/cliente. Stessi nomi e stesse regole del License Manager (`src/lib/customer-profile.ts`). Stesso formato oggi (esportato a mano e importato dal pannello) e domani (corpo di una chiamata API di registrazione automatica).

## Struttura

```json
{
  "schemaVersion": 1,
  "exportedAt": "2026-11-12T10:00:00Z",
  "software": "DH_CONNECT_001",
  "tenant": {
    "externalId": "b3f1c2a4-8d5e-4c7a-9f21-6a0e1d2c3b44"
  },
  "customer": {
    "kind": "COMPANY",
    "name": "Rossi Servizi S.r.l.",
    "firstName": null,
    "lastName": null,
    "contactName": "Maria Bianchi",
    "taxCode": "01234567897",
    "vatNumber": "01234567897",
    "sdiCode": "ABC1234",
    "pec": "rossiservizi@pec.it",
    "street": "Via Roma 12",
    "postalCode": "20121",
    "city": "Milano",
    "province": "MI",
    "country": "IT",
    "email": "info@rossiservizi.it",
    "billingEmail": "amministrazione@rossiservizi.it",
    "phone": "+39 02 1234567"
  }
}
```

Privato:

```json
{
  "schemaVersion": 1,
  "exportedAt": "2026-11-12T10:00:00Z",
  "software": "DH_CONNECT_001",
  "tenant": {
    "externalId": "7c9e6679-7425-40de-944b-e07fc1f90ae7"
  },
  "customer": {
    "kind": "PERSON",
    "name": "Mario Rossi",
    "firstName": "Mario",
    "lastName": "Rossi",
    "contactName": null,
    "taxCode": "RSSMRA85T10A562S",
    "vatNumber": null,
    "sdiCode": null,
    "pec": null,
    "street": "Via Garibaldi 5",
    "postalCode": "00185",
    "city": "Roma",
    "province": "RM",
    "country": "IT",
    "email": "mario.rossi@example.com",
    "billingEmail": null,
    "phone": null
  }
}
```

## Campi

| Campo | Tipo | Note |
|---|---|---|
| `schemaVersion` | numero | `1`. Cambia solo se la struttura cambia in modo incompatibile. |
| `exportedAt` | data ISO 8601 UTC | Informativa. |
| `software` | testo | Codice software nel License Manager (`LICENSE_SOFTWARE_CODE`). Se non coincide con il software scelto in importazione, l'importazione si ferma. |
| `tenant.externalId` | testo, 1–160 | L'identificativo stabile del portale (`license_id`, UUID). È la stessa chiave che il software manda all'API di lettura. Unico dentro il software. |
| `customer.kind` | `"PERSON"` o `"COMPANY"` | Obbligatorio. |
| `customer.name` | testo, 2–120 | Nome mostrato: ragione sociale per le aziende, «Nome Cognome» per i privati. Sempre presente. |
| `firstName`, `lastName` | testo, max 60 l'uno, insieme max 120 | Solo `PERSON`; `null` per le aziende. |
| `contactName` | testo, max 120 | Referente, solo `COMPANY`, facoltativo. |
| `taxCode` | testo | Privato: 16 caratteri con cifra di controllo (omocodia ammessa). Azienda: 11 cifre o 16 caratteri (ditta individuale). Maiuscolo, senza spazi, senza prefisso `IT`. |
| `vatNumber` | testo | Solo `COMPANY`: 11 cifre con cifra di controllo, senza prefisso `IT`. Per l'estero: partita IVA o equivalente come digitata. |
| `sdiCode` | testo | Solo `COMPANY` italiana: 7 caratteri lettere/cifre, maiuscolo. |
| `pec` | email | Solo `COMPANY` italiana. Per un'azienda italiana serve almeno uno tra `sdiCode` e `pec`. |
| `street` | testo, max 200 | |
| `postalCode` | testo | Italia: 5 cifre. Estero: testo libero, max 12 caratteri. |
| `city` | testo, max 100 | |
| `province` | testo | Italia: sigla di 2 lettere maiuscole. Estero: come digitata. |
| `country` | testo | ISO 3166-1 a 2 lettere maiuscole. Default `IT`. |
| `email` | email, max 254 | Minuscola. |
| `billingEmail` | email, max 254 | Facoltativa. Dove vanno avvisi e fatture se diversa da `email`. |
| `phone` | testo, max 40 | Facoltativo. |

Regole generali: tutti i campi di `customer` sono sempre presenti e usano `null` quando non ci sono (mai testo vuoto); niente caratteri di controllo; spazi ai bordi tolti; nessuna verifica online, solo formato e cifra di controllo.

Completo per la fatturazione:

- Privato italiano: nome, cognome, codice fiscale, via, CAP, comune, provincia, email.
- Azienda italiana: ragione sociale, partita IVA, SDI o PEC, via, CAP, comune, provincia, email.
- Estero: nome o ragione sociale, via, comune, nazione, email e, per le aziende, la partita IVA o equivalente.

## Cosa NON contiene

- Nessuna chiave API e nessun segreto.
- Nessun dato di licenza (piano, scadenza, importo): quelli li decide il License Manager.
- Niente dati del manager del portale o dei clienti finali del portale.

## Uso

1. **Oggi (manuale):** in DigitHub Connect, pulsante accanto al portale «Copia JSON per License Manager»; nel License Manager «Importa da JSON» (da costruire) crea cliente e tenant in un passaggio, con anteprima e controlli.
2. **Domani (automatico):** stessa struttura come corpo di una chiamata di registrazione dal software al License Manager (da costruire, fa parte dei «Da fare»).
