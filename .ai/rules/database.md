---
paths:
  - 'database/**'
---

# Database

## I default di colonna non tornano nel modello dopo create()
`Model::create()` non rilegge la riga: le colonne con un default definito nella migrazione restano **null sull'istanza in memoria** finché non fai `->refresh()`.

La tabella `services` si appoggia molto ai default (`slot_interval_minutes`, `min_notice_minutes`, `max_advance_days`, `is_active`, …). Creare un servizio senza passarli e poi usarlo subito ha già rotto silenziosamente il seeder: `is_active` era `null`, e `SlotFinder` restituiva zero slot senza errori.

Regola: **passa sempre i valori esplicitamente** nei `create()`, anche quando la migrazione ha un default. In alternativa `->refresh()` prima di leggerli, ma è più facile dimenticarsene.

Vale anche per le **factory**: una factory che omette una colonna con default produce modelli mezzi vuoti in ogni test.

Questa trappola ha già colpito **tre volte**, e ogni volta in silenzio:
- `services.is_active` null → il generatore di slot restituiva zero slot senza errori
- `tenants.notify_manager_on_booking` null → assegnazione a una proprietà `bool` della vecchia pagina Livewire in errore
- `bookings.ics_sequence` null → file `.ics` con `SEQUENCE:` vuoto, che alcuni calendari rifiutano

Nessuna delle tre ha prodotto un'eccezione al punto in cui nasceva il problema. Dove un valore mancante produrrebbe un dato malformato invece di un errore, vale la pena difendersi anche al punto d'uso.

## Ogni nuova tabella di un portale entra nell'esportazione
App\Actions\Tenants\ExportPortal::sections() elenca a mano ogni tabella che appartiene a un portale: è la restituzione dei dati a fine rapporto (art. 28 GDPR). Una migrazione che crea una tabella con tenant_id, o legata a una riga del portale (pivot, risposte, fasi), deve aggiungere lì la sua sezione: con il modello se ha cast cifrati o colonne nascoste, con DB::table per le pivot. Credenziali (password, 2FA, passkey, SMTP) restano escluse. Un nuovo file caricato va aggiunto ad addUploadedFiles(). PortalExportTest controlla contenuto, isolamento fra portali ed esclusione dei segreti.

## tenant_billing_profiles resta fuori da ExportPortal (eccezione voluta)
La regola «ogni tabella di un portale entra nell'esportazione» ha UN'eccezione: `tenant_billing_profiles` (chi paga la piattaforma: nome legale, codice fiscale, P.IVA, indirizzo di fatturazione). Sono dati che la piattaforma tiene per fatturare il portale, non dati del portale restituiti a fine rapporto (art. 28 GDPR). Per questo la tabella è separata da `tenants` (ExportPortal esporta il modello Tenant intero: come colonne sarebbero finite nello zip). Non aggiungerla a ExportPortal::sections(); PortalBillingTest «the billing profile reaches nobody but the administrator» lo verifica.

## tenant_licenses resta fuori da ExportPortal, e tiene solo il necessario
`tenant_licenses` è l'ultima risposta del License Manager su un portale: dati della piattaforma, non del portale, quindi **non** entra in ExportPortal (stessa eccezione di tenant_billing_profiles). Si salva solo ciò che serve a decidere e a mostrare: verdetto, date, nome del piano, i totali (dovuto, pagato, ancora da incassare) e i prezzi di rinnovo ancora in vendita (`renewal_prices`, solo mesi, prezzo e valuta). I totali e i prezzi li vedono l'admin e il **manager del portale** nella scheda «Licenza» della sua dashboard (decisione del 2026-11-12, che supera il «saldo solo all'admin»), mai gli operatori. Anagrafica del cliente, singoli pagamenti e periodi della risposta non si conservano mai: se un domani servisse l'elenco dei pagamenti, è una decisione nuova (dati personali). `outcome` null = il License Manager non ha mai risposto; `last_error` = codice dell'ultimo tentativo fallito (la copia è allora «stale»). Un portale `license_exempt` (demo e di prova non registrati) esce dalla licenza.
