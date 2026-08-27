---
paths:
  - 'app/Mail/**'
---

# Mail

## Email: mittente, destinatari, lingua
Tutte le email di prenotazione estendono `App\Mail\BookingMail`, che costruisce l'envelope una volta sola. Non ridefinire `envelope()` nelle sottoclassi: implementa solo `subjectLine()` e `content()`.

**Mittente e trasporto.** Dipendono da come è configurato il portale, e `BookingMail` li risolve da solo:

| Portale | Trasporto | `From:` |
|---|---|---|
| ha SMTP proprio (`hasCustomMailer()`) | il suo server | il suo indirizzo — SPF e DKIM del suo dominio tornano, perché è davvero il suo server |
| non ce l'ha | mailer della piattaforma | `config('mail.from.address')`, con il nome del portale come display name |

**Non** mettere l'indirizzo del portale nel `From` quando si invia dal mailer della piattaforma: SPF e DMARC del suo dominio non autorizzano i nostri server, e il messaggio finisce in spam o viene rifiutato. `Tenant::mailFromAddress()` applica già la regola giusta.

Il mailer del portale viene registrato in `config('mail.mailers.tenant.{id}')` **dentro `BookingMail::send()`**, cioè dentro il job in coda: è l'unico momento in cui il worker sa a quale portale appartiene il messaggio. Registrarlo al dispatch non sopravvivrebbe alla coda.

`Tenant::notificationReplyTo()` risolve `reply_to_email` → `contact_email` → nessun Reply-To.

**Credenziali.** `tenants.mail_password` ha il cast `encrypted`: mai loggarla, mai rimandarla al browser. Il campo password nel form è vuoto di default e un valore vuoto al salvataggio significa "tieni quella che c'è". Cambiare `APP_KEY` rende illeggibili tutte le password SMTP salvate.

**Errori.** Una configurazione sbagliata fa fallire le email in silenzio. `Actions\Tenants\TestMailer` invia una prova **sincrona** e salva l'esito in `mail_tested_at` / `mail_error`, che la schermata mostra.

**Destinatari.** Cliente e operatore ricevono **email separate**, mai una con l'altro in copia: l'operatore ha bisogno di recapiti e note, il cliente del link di gestione, e nessuno dei due deve vedere l'indirizzo dell'altro. Chi riceve cosa lo decide `App\Services\BookingNotifier`, unico punto da cui partono le email di prenotazione — le azioni lo chiamano, non usano `Mail::` direttamente.

**Lingua.** Al cliente si scrive in `bookings.customer_locale`, catturata al momento della prenotazione; all'operatore in `users.locale`. Sempre via `Mail::to(...)->locale(...)`.

**Coda.** `BookingMail` implementa `ShouldQueue`. L'invio avviene **fuori** da `AgendaLock`, così nessuno aspetta la coda mentre l'agenda è bloccata. In sviluppo `MAIL_MAILER=log` scrive in `storage/logs`; il seeder chiama `Mail::fake()` per non sporcarlo.

**Viste.** `resources/views/mail/` con `@extends('mail.layout')`. Non usare componenti `x-mail::`: il namespace `mail::` è già del framework. Stili inline, perché diversi client scartano i blocchi `<style>`.
