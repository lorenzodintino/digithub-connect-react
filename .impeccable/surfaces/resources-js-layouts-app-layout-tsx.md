---
version: 1
slug: "resources-js-layouts-app-layout-tsx"
primary_target: "resources/js/layouts/app-layout.tsx"
related_targets: ["resources/js/pages/app/bookings.tsx"]
---

# Back-office e interfaccia: nuovo stile «Pro» (proposta F)

## Ambito e modo
Riprogettazione **solo visiva** di tutta l'interfaccia React (back-office, admin, accesso, home, pagine pubbliche dei portali). Modo **Operate** per back-office, admin e impostazioni; **Operate** anche per la pagina pubblica di prenotazione (compito: prenotare); la home è l'unica superficie di tipo Persuade. Funzioni, rotte, permessi e testi funzionali restano identici (PRODUCT.md). Scelta dell'utente il 2026-11-14 tra sei proposte; prototipo di riferimento: `C:\ARCHIVIO\AI\digithub-connect-react-bozze\f-pro.html`.

## Pubblico, compito, vincoli
Titolari e operatori che aprono l'agenda molte volte al giorno, da telefono e da computer; clienti che prenotano da telefono. Compito: leggere cosa succede e decidere (confermare, annullare, esito) senza pensare. Vincoli: stack Inertia + React + Tailwind 4 + componenti Radix esistenti; tema chiaro e scuro già presenti (impostazione «Aspetto»); colore e logo del portale devono continuare a vestire la pagina pubblica (`--portal-brand`); IT/EN; nessuna funzione nuova (niente ricerca-comandi né scorciatoie finché l'utente non le chiede).

## Direction contract

THESIS: un pianificatore professionale denso e silenzioso: l'agenda è la verità e tutto il resto le sta intorno senza decorazioni. Rifiuta l'arredo SaaS generico (carte tutte uguali con icona e titolo, ombre pesanti, gradienti).

OWN-WORLD: grafite e bianco con un solo accento rosa (#F0287A chiaro, #FF4D95 scuro) usato per azione principale, selezione, «oggi» e focus; i colori degli operatori (blu, verde, ambra) e gli stati (verde, ambra, rosso) fanno il resto. Albert Sans per l'interfaccia, JetBrains Mono per orari e tasti. Filetti da 1px, superfici piane, raggi 9–14px, nessun gradiente di testo. Densità alta (controlli da 36px, righe da 40px) con 44px sui bersagli del telefono. Chiaro e scuro con la stessa struttura.

STORY: chi apre l'app capisce in due secondi cosa aspetta una decisione (richieste da confermare in evidenza), legge la settimana a colpo d'occhio e agisce con un tocco; il cliente arriva alla conferma senza mai chiedersi cosa fare dopo.

FIRST VIEWPORT: agenda a 1440×900: barra laterale a sinistra (nome del portale, voci con icona, conteggio richieste), barra degli strumenti in alto (titolo, settimana, filtri operatore, viste, oggi), griglia settimanale a tutta larghezza con la riga «adesso» e le richieste tratteggiate; i dettagli si aprono nel foglio laterale già esistente (`?booking=`); azione principale rosa in alto a destra.

FORM: proposta F «Pro» (piano di lavoro denso, tema chiaro/scuro), scelta dall'utente tra sei; posizione sulla lista dei miei candidati: l'ultimo della seconda serie; seed: none (unavailable).

FINISH: unreviewed and undocumented is unfinished; this build ends with the finish review, the verdict, DESIGN.md, and every shipping raster carrying its provenance.

## Decisioni aperte
- Ricerca-comandi (Ctrl K), scorciatoie da tastiera e pulsante rapido del tema: sono funzioni nuove, fuori da questa riprogettazione finché l'utente non le approva.
- Su telefono il menu laterale diventa un cassetto con tutte le voci (come già fa la barra laterale mobile di shadcn).
- I dettagli di una prenotazione restano nel foglio laterale (URL `?booking=`), non diventano un pannello fisso.
