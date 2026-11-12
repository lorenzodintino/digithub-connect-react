---
paths:
  - 'tests/**'
---

# Tests

## Niente GD: non usare UploadedFile::fake()->image()
Il PHP di questo progetto (herd-lite, build statica senza cartella ext) non ha l'estensione GD, quindi `UploadedFile::fake()->image()` fallisce con "GD extension is not installed."

Per i file di test usa `UploadedFile::fake()->createWithContent($name, $bytes)` con i byte reali di un PNG minimo, eventualmente riempito con NUL fino al peso voluto: finfo legge i magic bytes iniziali e la regola `image`/`mimes` passa lo stesso. Vedi `fakeLogo()` in `tests/Feature/PortalLogoTest.php`.

## Test delle pagine: HTTP e assertInertia
Ogni pagina è Inertia: lettura con `get()->assertInertia(fn (Assert $page) => $page->component(...)->where(...))`, scritture con `post/patch/put/delete` + `assertSessionHasErrors`/`assertSessionHasNoErrors`, toast con `assertInertiaFlash('toast.message', '...')`. Per un flusso con redirect usa `from(route(...))->followingRedirects()`: `assertInertia` legge anche l'HTML della prima visita. I testi nei componenti React non si vedono lato server: verifica le props, non `assertSee`. Una verifica su un testo che solo il client disegna non prova nulla lato server: si verificano le props che lo producono.

## serviceForm(): il modulo servizio come lo manda la pagina
Per creare o modificare un servizio nei test usa `serviceForm($tenant, [...])` (nuovo servizio) o `serviceForm($service, [...])` (servizio esistente caricato nel modulo), definita in `tests/Pest.php` sopra `App\Support\ServiceFormData`, la stessa classe che il controller usa per le props. Manda sempre il modulo completo: `ServiceRequest` vuole tutti i campi, e le domande esistenti portano il loro `id` (senza, le risposte già date perderebbero il collegamento).

## Utente autenticato riusato fra le richieste dei test
Con `actingAs($user)` ogni richiesta del test riceve la stessa istanza di User: le relazioni caricate alla prima richiesta (`$user->tenant`) restano in cache. Se fra due richieste modifichi il portale con un'altra istanza (`$this->tenant->update(...)`), il codice che legge `$request->user()->tenant` vede ancora i vecchi valori. Aggiorna tramite la relazione (`$this->manager->tenant->update(...)`) o fai `unsetRelation('tenant')`. Nel browser non succede: ogni richiesta ricarica l'utente.

Helper globali in `tests/Pest.php` per i moduli come li manda la pagina: `serviceForm()` e `portalForm()` (portale admin, con i campi del manager usati solo alla creazione). Per l'email del portale `TenantMailerTest` ha `mailForm($tenant, $reader, [...])` sopra `MailSettingsData`.
