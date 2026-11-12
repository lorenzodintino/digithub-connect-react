# Prompt 1 — Dati di fatturazione alla creazione del portale

Leggi prima LOG.md (stato e ultime voci) e .ai/rules/index.md, poi le regole dei file che tocchi. Segui le convenzioni del progetto: Actions, FormRequest, Pest, traduzioni in lang/it.json, voce nel diario a fine sessione.

## OBIETTIVO

DigitHub Connect sarà collegato a DigitHub License Manager, il gestionale delle licenze. Il «cliente» del License Manager è il PORTALE (Tenant) e la persona o azienda che lo paga. Oggi la Tenant non ha dati di fatturazione: il suo `address` e i suoi contatti sono dati PUBBLICI del portale e non vanno riusati. Devi aggiungere una scheda di fatturazione, raccolta quando l'amministratore crea il portale. Per ora copierò i dati a mano nel License Manager; in futuro li invieremo via API con gli stessi campi, quindi nomi e regole devono corrispondere.

## PUNTO DI INGRESSO

La registrazione pubblica è spenta (config/fortify.php) e DEVE restare spenta. I portali li crea l'admin dal form di admin/portals (PortalRequest, SavePortal, pagina resources/js/pages/admin/portals.tsx e admin/portal.tsx). Lavora lì, sia in creazione sia in modifica.

## CAMPI

Il nome mostrato è già `name` del portale: qui serve il nome LEGALE. Salva i campi come tabella separata 1:1 con la Tenant (non come colonne della Tenant), così i dati non finiscono in ExportPortal né in altri punti che serializzano la Tenant. Nomi dei campi come sotto.

Tipo (obbligatorio): `PERSON` privato / `COMPANY` azienda. Il form mostra i campi giusti.

- PERSON: firstName, lastName (obbligatori, max 60 l'uno, insieme max 120), taxCode (16 caratteri, cifra di controllo, accetta omocodia).
- COMPANY: ragione sociale (obbligatoria, 2–120), vatNumber (11 cifre con cifra di controllo, prefisso IT tolto), taxCode (11 cifre come la P.IVA oppure 16 caratteri per ditta individuale; prefisso IT tolto), sdiCode (7 caratteri lettere/cifre), pec (email), contactName (referente, facoltativo, max 120).
- Per tutti: street (max 200), postalCode, city (max 100), province, country (ISO a 2 lettere, default IT), email (max 254), billingEmail (facoltativa: dove vanno avvisi e fatture, se diversa), phone (max 40).

Obbligatori per salvare nel nostro form (dati completi):

- Privato IT: nome, cognome, codice fiscale, via, CAP, comune, provincia, email.
- Azienda IT: ragione sociale, partita IVA, SDI oppure PEC (almeno uno), via, CAP, comune, provincia, email.
- Estero (country diversa da IT): nessun controllo italiano; obbligatori nome/ragione sociale, via, comune, nazione, email e, per le aziende, la partita IVA o equivalente.
- Facoltativi sempre: billingEmail, phone, contactName.

Normalizzazione: codice fiscale, P.IVA, SDI, provincia IT e nazione in maiuscolo senza spazi; email in minuscolo; testi con spazi ridotti, senza caratteri di controllo (anche nei codici). CAP italiano 5 cifre, provincia italiana sigla di 2 lettere; per l'estero CAP e provincia restano come digitati («SW1A 1AA», «Greater London»). Nessuna verifica online, solo formato e cifra di controllo. Errori in italiano accanto al campo, form che resta compilato, cursore sul campo da correggere.

Riferimento: docs/riferimento/customer-profile.ts (e il suo test) è l'implementazione TypeScript delle regole del License Manager. Portala in PHP con gli STESSI risultati (stessi casi dei test: codice fiscale valido, con omocodia, con cifra errata; P.IVA valida e non; prefisso IT; azienda senza SDI né PEC; estero; normalizzazione; nome troppo lungo).

## DATI E COMPATIBILITÀ

- Migrazione non distruttiva: colonne facoltative a livello di database. I portali esistenti (compreso il portale demo del seeder) non devono rompersi: mostrano «dati di fatturazione incompleti» con l'elenco di cosa manca, senza bloccare nulla. Aggiorna anche factory e seeder.
- Modifica di un portale esistente senza profilo: la sezione tutta vuota è accettata e il portale resta «Incompleto». Appena compili anche solo un campo valgono tutte le regole. Nei portali nuovi la sezione è sempre obbligatoria.
- Non toccare i campi pubblici contact_email, contact_phone, website, address.
- Il manager (User) resta com'è: name ed email del manager sono altra cosa dalla fatturazione.
- Dati personali: non nei log (il registro eventi riporta solo i nomi dei campi cambiati, mai i valori), non esposti al portale pubblico, non nell'esportazione del portale (ExportPortal). Scrivi l'eccezione nella regola di database.md.
- Permessi: solo l'admin vede e modifica (TenantPolicy::manageBilling). Il manager non li vede, né nelle pagine né nelle props Inertia.

## VISTA PER ME (ADMIN)

Nella pagina admin del portale, sezione «Dati di fatturazione»: tutti i campi, un chip «Completi» o «Incompleti» con ciò che manca, e un pulsante «Copia dati di fatturazione» (riusa il componente copyable-input o un equivalente già presente) che mette negli appunti un blocco di testo, costruito dal server:

```
Ragione sociale / Nome Cognome
Partita IVA: ...
Codice fiscale: ...
Indirizzo: Via ..., CAP Comune (PR), IT
Codice SDI: ...
PEC: ...
Referente: ...
Email fatture e avvisi: ...
Email: ...
Telefono: ...
Tenant id (License Manager): <id numerico del portale>
```

Le righe vuote si omettono. L'identificativo per il License Manager è l'`id` della Tenant come stringa: è stabile, a differenza dello slug che si può cambiare. Mostralo anche nella pagina, ben visibile e copiabile.

## COME PROCEDERE

1. Esplora i file citati e il flusso di creazione portale. Prima di scrivere codice, dimmi il piano in poche righe: struttura dei dati e migrazione, FormRequest/Action, comportamento Privato/Azienda/estero del form, permessi, esportazione, vista admin, test. Aspetta il mio ok.
2. Implementa con test Pest (regole di controllo, creazione e modifica portale, portali esistenti senza dati, permessi, normalizzazione) e prova il flusso nel browser: creazione privato, azienda, estero, errore con campi conservati, vista admin e copia.
3. Esegui i comandi di verifica elencati nel LOG.md (test, lint, tipi, build) e riporta gli esiti reali, fallimenti compresi. Scrivi la voce nel LOG.md.

Non toccare altro, non aggiungere dipendenze, non riattivare la registrazione pubblica. Se qualcosa non torna con il codice, chiedimelo invece di indovinare.
