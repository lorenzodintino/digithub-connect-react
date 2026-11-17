# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

- **Titolari e operatori di piccole attività** (studi, centri, saloni, ambulatori): gestiscono servizi, orari, risorse e agenda del proprio portale. Usano il back-office **soprattutto dal telefono** (confermato il 2026-11-14), spesso tra un appuntamento e l'altro, e dal computer in sede. Ruoli: *manager* (uno per portale) e *operatore* (vede e gestisce solo la propria agenda).
- **Clienti finali** (senza account): prenotano dalla pagina pubblica del portale, quasi sempre da telefono, e gestiscono la prenotazione da un link con token (sposta/annulla, QR).
- **Amministratore della piattaforma** (raro): crea e sospende i portali, gestisce piani, SMTP, documenti legali, coda e registro azioni.

## Product Purpose

DigitHub Connect è una piattaforma di prenotazione multi-portale (stile Calendly / Cal.com): ogni attività cliente ha il suo portale prenotabile, con agenda, servizi, risorse condivise (stanze, cabine, macchinari), pacchetti, lista d'attesa ed email automatiche. Successo = il cliente prenota senza chiedere aiuto, e il titolare apre l'agenda e sa subito cosa fare.

## Positioning

Una sola piattaforma ospita molte attività, ciascuna con **il proprio logo e colore** sulla pagina pubblica (link diretto e incorporamento `?embed=1`). Ogni scrittura sull'agenda è serializzata e ricontrollata, quindi due prenotazioni non si sovrappongono; la piattaforma assegna in automatico l'operatore meno carico e occupa le risorse necessarie.

## Operating Context

- Pagina pubblica `/p/{portale}`: elenco servizi, calendario mensile con giorni liberi, orari mostrati nel fuso del cliente, scelta della persona (o «operatore indifferente»), modulo con nome, email, telefono, note; link con token e QR per gestire la prenotazione.
- Back-office: agenda, servizi, risorse, pacchetti, staff, disponibilità, clienti, lista d'attesa, report, impostazioni del portale (logo, contatti, link pubblico, snippet iframe, SMTP proprio).
- Admin: portali, account, piani, documenti legali, coda dei job, registro delle azioni.
- Interfaccia bilingue IT/EN; orari salvati in UTC, interpretati nel fuso del portale e mostrati in quello di chi guarda.
- Email transazionali (nove messaggi) inviate dalla piattaforma con il nome del portale come mittente.

## Capabilities and Constraints

- **La riprogettazione è solo visiva**: ogni funzione, flusso, permesso, rotta e testo funzionale resta identico e funzionante (richiesto il 2026-11-14). Cambia l'interfaccia, non il comportamento.
- Stack esistente da mantenere: Laravel 13 + Inertia 3 + React 19 + TypeScript, Tailwind CSS 4, componenti Radix in `resources/js/components/ui`, rotte tipizzate con Wayfinder. Le bozze sono prototipi statici; l'implementazione vera avverrà su questo stack, dopo la scelta.
- Il colore e il logo di ogni portale (`brand_color`, logo) devono continuare a poter vestire la pagina pubblica: il nuovo stile deve accettare un colore d'accento scelto dal cliente.
- Pagina pubblica incorporabile in iframe su siti esterni (`?embed=1`).
- Passkey nascoste dietro `PASSKEYS_ENABLED` (spento); registrazione pubblica disattivata; 2FA presente.
- Non decisi: tema scuro (oggi esiste un'impostazione di aspetto), eventuale logo definitivo della piattaforma.

## Brand Commitments

- Nome: **DigitHub Connect**. Lingua principale dell'interfaccia: italiano.
- Nessun vincolo visivo vincolante dichiarato: l'utente vuole **rivoluzionare totalmente lo stile grafico** rispetto all'attuale (shadcn/ui «neutral», Instrument Sans, bianco/nero/grigi).

## Evidence on Hand

- Dati di dimostrazione sintetici nel seeder (portale «Studio Rossi», operatori e servizi inventati): utili per le bozze, vanno dichiarati finti.
- **Non esistono** clienti reali, testimonianze, recensioni, numeri di mercato né prezzi commerciali definitivi da mostrare: non vanno inventati nelle bozze (i piani Base/Pro/Studio e i loro prezzi sono quelli del seeder).
- Nessun logo definitivo della piattaforma (`app-logo.tsx` è provvisorio).

## Product Principles

1. **Chi prenota non deve pensare**: la pagina pubblica porta da servizio a conferma con il minimo di decisioni, da telefono.
2. **L'agenda è la verità**: l'informazione che serve ora (chi, quando, stato, azione) si legge a colpo d'occhio e si agisce con un tocco.
3. **Ogni attività è ospite, non la piattaforma**: sulla pagina pubblica il marchio dell'attività conta più di quello della piattaforma.
4. **Funzione prima dello stile**: nessun elemento grafico può nascondere, rallentare o cambiare un'azione esistente.
5. **Mobile-first anche per chi lavora**: ogni schermata del back-office nasce a 390px e cresce, non viceversa.
