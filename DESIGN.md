---
name: DigitHub Connect
description: Pianificatore professionale denso e silenzioso: grafite e bianco, un solo accento cobalto, filetti da 1px, superfici piane.
colors:
  primary: "oklch(0.558 0.243 268)"
  primary-foreground: "oklch(1 0 0)"
  primary-dark: "oklch(0.66 0.177 268)"
  primary-foreground-dark: "oklch(0.169 0.013 271)"
  background: "oklch(0.972 0.003 260)"
  background-dark: "oklch(0.16 0.006 265)"
  surface: "oklch(1 0 0)"
  surface-dark: "oklch(0.2 0.008 265)"
  sidebar: "oklch(0.985 0.002 260)"
  sidebar-dark: "oklch(0.18 0.007 265)"
  ink: "oklch(0.19 0.012 265)"
  ink-dark: "oklch(0.95 0.005 265)"
  muted-ink: "oklch(0.5 0.025 265)"
  muted-ink-dark: "oklch(0.74 0.02 265)"
  subtle-ink: "oklch(0.52 0.02 265)"
  subtle-ink-dark: "oklch(0.62 0.02 265)"
  wash: "oklch(0.955 0.004 260)"
  wash-dark: "oklch(0.25 0.01 265)"
  hairline: "oklch(0.925 0.006 260)"
  hairline-dark: "oklch(1 0 0 / 9%)"
  field-stroke: "oklch(0.9 0.008 260)"
  field-stroke-dark: "oklch(1 0 0 / 14%)"
  destructive: "oklch(0.577 0.245 27.325)"
  destructive-dark: "oklch(0.704 0.191 22.216)"
  success: "oklch(0.55 0.15 152)"
  success-dark: "oklch(0.78 0.17 155)"
  warning: "oklch(0.78 0.16 75)"
  warning-dark: "oklch(0.8 0.15 80)"
typography:
  page-title:
    fontFamily: "Atkinson Hyperlegible Next, ui-sans-serif, system-ui, sans-serif"
    fontSize: "1.5rem"
    fontWeight: 800
    lineHeight: 1.33
    letterSpacing: "-0.025em"
  auth-title:
    fontFamily: "Atkinson Hyperlegible Next, ui-sans-serif, system-ui, sans-serif"
    fontSize: "1.25rem"
    fontWeight: 800
    lineHeight: 1.4
    letterSpacing: "-0.025em"
  title:
    fontFamily: "Atkinson Hyperlegible Next, ui-sans-serif, system-ui, sans-serif"
    fontSize: "1.125rem"
    fontWeight: 700
    lineHeight: 1.55
    letterSpacing: "-0.025em"
  stat-value:
    fontFamily: "Atkinson Hyperlegible Next, ui-sans-serif, system-ui, sans-serif"
    fontSize: "1.875rem"
    fontWeight: 700
    lineHeight: 1.2
    letterSpacing: "-0.025em"
  body:
    fontFamily: "Atkinson Hyperlegible Next, ui-sans-serif, system-ui, sans-serif"
    fontSize: "0.875rem"
    fontWeight: 400
    lineHeight: 1.43
  button:
    fontFamily: "Atkinson Hyperlegible Next, ui-sans-serif, system-ui, sans-serif"
    fontSize: "0.875rem"
    fontWeight: 600
  label-caps:
    fontFamily: "Atkinson Hyperlegible Next, ui-sans-serif, system-ui, sans-serif"
    fontSize: "0.75rem"
    fontWeight: 600
    letterSpacing: "0.025em"
  agenda-micro:
    fontFamily: "Atkinson Hyperlegible Next, ui-sans-serif, system-ui, sans-serif"
    fontSize: "0.75rem"
    fontWeight: 500
    fontFeature: "tnum"
rounded:
  sm: "calc(0.625rem - 4px)"
  md: "calc(0.625rem - 2px)"
  lg: "0.625rem"
  xl: "calc(0.625rem + 4px)"
  full: "9999px"
spacing:
  control-sm: "32px"
  control: "36px"
  control-touch: "44px"
  card-padding: "20px"
  page-padding: "16px"
  page-padding-lg: "32px"
components:
  button-primary:
    backgroundColor: "{colors.primary}"
    textColor: "{colors.primary-foreground}"
    rounded: "{rounded.md}"
    height: "{spacing.control}"
    padding: "8px 16px"
    typography: "{typography.button}"
  button-outline:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink}"
    rounded: "{rounded.md}"
    height: "{spacing.control}"
  button-secondary:
    backgroundColor: "{colors.wash}"
    textColor: "{colors.ink}"
    rounded: "{rounded.md}"
    height: "{spacing.control}"
  button-destructive:
    textColor: "{colors.destructive}"
    rounded: "{rounded.md}"
    height: "{spacing.control}"
  input:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink}"
    rounded: "{rounded.md}"
    height: "{spacing.control}"
    padding: "4px 12px"
  card:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink}"
    rounded: "{rounded.xl}"
    padding: "{spacing.card-padding}"
  dialog:
    backgroundColor: "{colors.surface}"
    rounded: "{rounded.xl}"
    padding: "24px"
  badge:
    backgroundColor: "{colors.wash}"
    textColor: "{colors.ink}"
    rounded: "{rounded.full}"
    padding: "2px 8px"
  switch-on:
    backgroundColor: "{colors.primary}"
    rounded: "{rounded.full}"
    height: "24px"
    width: "42px"
  table-head:
    textColor: "{colors.muted-ink}"
    height: "40px"
---

# Design System: DigitHub Connect

## Overview

**Creative North Star: "The Quiet Planner"**

Un pianificatore professionale denso e silenzioso: l'agenda e' la verita' e tutto il resto le sta intorno senza decorazioni. Grafite e bianco fanno il lavoro; un solo cobalto segnala cio' che si puo' fare adesso (azione principale, selezione, "oggi", fuoco). Le superfici sono piane, separate da filetti da 1px; nessuna ombra pesante, nessun gradiente. Il tema chiaro e quello scuro condividono la stessa struttura e gli stessi nomi di token.

La densita' e' alta sul computer (controlli da 36px, righe tabella da 40px) e si rilassa sul telefono, dove ogni bersaglio tocco sale a 44px. Le pagine pubbliche dei portali sono ospiti: indossano il colore del portale al posto del cobalto, con la stessa struttura.

**Key Characteristics:**
- Un solo accento, `--primary`, sostituibile dal colore del portale sulle pagine pubbliche.
- Colori di stato e colori operatore (dati dal server) fanno tutto il resto del colore.
- Filetti 1px e superfici piane; l'ombra compare solo su finestre sovrapposte e come reazione all'hover di un evento in agenda.
- Cifre tabulari ovunque (`font-variant-numeric: tabular-nums` sul body).
- Atkinson Hyperlegible Next per l'interfaccia; JetBrains Mono dichiarato per orari e tasti. Marchio: il simbolo Atrio, con il punto sempre cobalto fisso.

## Colors

Grafite freddo (tinta 260-265) e bianco, un solo cobalto saturo (identita' "Atrio · Cobalto", decisa il 2026-11-15); i valori sono OKLCH, la fonte e' `resources/css/app.css`. Il suffisso `-dark` indica il valore nel tema scuro.

### Primary
- **Cobalto Atrio** (`primary`, oklch(0.558 0.243 268), #3A5CFF): bottone principale, giorno selezionato, "oggi" (cerchio sul giorno, linea "adesso", tinta `primary/5` sulla colonna), interruttori attivi, checkbox spuntate, anello di fuoco (`--ring` uguale a `--primary`), voce attiva della barra laterale (`--sidebar-primary`). Nel tema chiaro il testo sul primario e' bianco; nel tema scuro diventa `primary-dark` (oklch(0.66 0.177 268), #6489FD) con testo scuro `primary-foreground-dark` (oklch(0.169 0.013 271)). `--sidebar-primary` e `--sidebar-ring` seguono gli stessi valori.
- Sulle pagine pubbliche `PortalLayout` ridefinisce `--primary`, `--primary-foreground` e `--ring` con `brand` e `brand_contrast` del portale (e imposta `--portal-brand`). Ogni regola sul cobalto va quindi scritta come "colore primario", mai come valore fisso.

### Neutral
- **Carta grafite** (`background`): fondo della pagina.
- **Bianco foglio** (`surface`, token `--card`): carte, campi, dialoghi, celle dell'agenda.
- **Barra laterale** (`sidebar`): quasi bianco, un gradino sopra il fondo.
- **Velo grafite** (`wash`, usato come `--secondary`, `--muted`, `--accent`): hover, bottone secondario, icone in tessera. `--accent` e' il grigio di hover, non il colore del marchio.
- **Inchiostro** (`ink`): testo; **Inchiostro attenuato** (`muted-ink`, L 0.50): testo secondario; **Inchiostro sottile** (`subtle-ink`, L 0.52): segnaposto e voci di terzo livello. Entrambi scurito nel tema chiaro dopo la revisione per il contrasto.
- **Filetto** (`hairline`, `--border`): ogni separazione. **Tratto campo** (`field-stroke`, `--input`): bordo di input, select, checkbox, interruttore spento. Nel tema scuro sono bianchi al 9% e al 14%.

### Status
- **Distruttivo** (`destructive`), **Successo** (`success`, con `--success-foreground`), **Avviso** (`warning`, con `--warning-foreground`): stati e messaggi. `ColorBadge` traduce i nomi colore degli enum PHP (amber, emerald, green, rose, red, orange, sky, blue, indigo, violet, zinc) in coppie 100/800 chiaro e 950/200 scuro; il server decide quale colore significa cosa. Il colore di operatori e servizi arriva dai dati (`booking.color`) e compare solo come punto, bordo sinistro da 3px o tinta al 22% mescolata con la carta.

### Named Rules
**The One Voice Rule.** Il primario e' l'unico accento: azione, selezione, "oggi", fuoco. Un secondo colore di marca non esiste; il colore in piu' viene dai dati (operatori, stati).

**The Guest Colour Rule.** Sulle pagine pubbliche il colore del portale prende il posto del primario tramite `--primary`/`--ring`; non scrivere mai il cobalto a mano in quelle pagine (unica eccezione: il punto del simbolo Atrio, vedi Marchio).

**The Same-Names Rule.** Chiaro e scuro ridefiniscono gli stessi token, mai strutture diverse.

## Typography

**Font interfaccia:** Atkinson Hyperlegible Next (400-800, con sans-serif di sistema come ripiego), caricato da Bunny Fonts via `vite.config.js`.
**Font mono:** JetBrains Mono (400-600), esposto come `--font-mono` e come utilita' `.font-time`.

**Character:** una sans ad alta leggibilita', con titoli pesanti (700-800) e tracciatura stretta, e corpo a 14px su desktop (i campi salgono a 16px sul telefono per evitare lo zoom).

### Hierarchy
- **Titolo pagina** (800, 1.5rem, `tracking-tight`): `PageHeader`.
- **Titolo accesso** (800, 1.25rem): `AuthHeader`; stesso peso per il nome del portale nell'intestazione pubblica e per i titoli dei servizi (1.25rem).
- **Titolo sezione** (700, 1.125rem): `EmptyState`, `CardTitle` (700, tracking stretto).
- **Cifra statistica** (700, 1.5rem su telefono, 1.875rem da `sm`): `StatCard`.
- **Corpo** (400, 0.875rem): tutto il resto; testo secondario in `muted-ink`.
- **Bottone** (600, 0.875rem).
- **Etichetta tabella** (600, 0.75rem, maiuscolo, tracciatura 0.025em): intestazioni di colonna e giorni della settimana nel mese. Le etichette dei gruppi della barra laterale usano la stessa famiglia (0.6875rem, 700, maiuscolo).
- **Micro agenda** (0.75rem, cifre tabulari): orari e nomi sugli eventi.

### Named Rules
**The Tabular Rule.** Il body ha `tabular-nums`; orari e conteggi non devono ballare tra una riga e l'altra.

**The Mono For Time Rule.** `.font-time` (mono, tracciatura stretta) e' pensata per orari e tasti; oggi l'utilita' e' definita ma nessun componente la usa ancora, quindi gli orari reali sono in Atkinson Hyperlegible Next tabulare. Usarla solo dove serve un allineamento a larghezza fissa.

## Layout

Modello a barra laterale piu' contenuto. Barra laterale fissa di 15.25rem (3rem in versione a icone), cassetto da 18rem sul telefono; sopra il contenuto, su telefono, una barra sticky di 56px (`h-14`) con il menu e il logo. Il contenuto ha padding 16px, 24px da `sm`, 32px da `lg`. Le pagine pubbliche usano una colonna centrata `max-w-5xl` con padding 16/24px; le pagine di accesso una colonna da `max-w-sm` centrata.

Ritmo: scala Tailwind a passi da 4px; carte con padding 20px e spazio interno 20px; griglie di campi con gap 16px. Breakpoint: `md` (768px) e' il confine telefono/computer per densita' e per le viste agenda; `sm` e `lg` regolano solo il respiro.

Agenda (`pages/app/bookings.tsx`): quattro viste (tabella, mese, settimana, giorno/elenco). Settimana a griglia da `md`: colonna ore da 3.25rem piu' sette colonne uguali, un minuto = un pixel di altezza, corsie affiancate per eventi sovrapposti, linea "adesso" da 2px in primario con pallino da 8px. Sul telefono la settimana diventa elenco di giorni su filetti 1px con eventi da min 44px; il mese mostra puntini colorati al posto dei nomi. Filtri a due colonne sul telefono.

Bersagli tocco: sotto `md` (`max-md`) bottone 44px (sm 40px), icona 44px (sm 40px), input e select 44px (sm 40px), checkbox con area di tocco estesa di 10px.

## Elevation & Depth

Piatto per scelta: la profondita' viene dai filetti da 1px e dal salto tonale fondo, carta, velo. Le ombre esistono solo dove un elemento e' davvero sopra gli altri: dialoghi (`shadow-lg`), manopola dell'interruttore (`shadow-sm`), evento dell'agenda in hover (`shadow-md`). Il dialogo e il foglio laterale hanno velo nero al 50%.

### Named Rules
**The Hairline Rule.** Si separa con un filetto da 1px (`border`), non con un'ombra e non con una carta in piu'.

**The Flat At Rest Rule.** Le superfici sono piane a riposo; l'ombra e' una risposta a un cambio di livello (sovrapposizione, hover su evento).

## Shapes

Raggio base `--radius` 0.625rem (10px). Scala: `sm` 6px, `md` 8px (bottoni, campi, trigger select), `lg` 10px (celle slot, eventi), `xl` 14px (carte, dialoghi, tessere statistiche, stato vuoto), `2xl` per la carta di accesso, `full` per badge, interruttori, punti e cerchio "oggi". Checkbox con raggio 5px. Gli eventi dell'agenda hanno raggio `md` e un bordo sinistro da 3px nel colore dell'operatore. Lo stato vuoto usa un bordo tratteggiato 1px.

## Components

### Buttons
- **Shape:** raggio `md` (8px), 600 a 0.875rem, altezza 36px (sm 32, lg 40, xs 24; 44/40 sotto `md`).
- **Primary:** fondo primario, testo `primary-foreground`, hover a `primary/90`; pressione `scale-[0.98]` (disattivata con `prefers-reduced-motion`).
- **Outline:** fondo carta, bordo `field-stroke`, hover velo. **Secondary:** fondo velo. **Ghost:** solo hover velo. **Destructive:** tinta rossa al 10% con testo rosso, non un pieno. **Link:** testo primario sottolineato in hover (nel tema scuro la sottolineatura serve, vedi limiti noti).
- **Focus:** anello da 3px `ring/50` e bordo `ring`. **Disabled:** opacita' 50%.

### Inputs / Fields
- Fondo carta, bordo `field-stroke`, raggio 8px, 36px (44px su telefono), 16px di testo su telefono e 14px da `md`; segnaposto in `subtle-ink`. Select con la stessa geometria e hover `accent/60`. Focus: stesso anello primario. Errore: bordo e anello `destructive`.
- **Checkbox:** 16px, raggio 5px, attiva in primario. **Interruttore:** 42x24px (sm 32x20), pillola; attivo in primario (cobalto o colore portale), spento `field-stroke`; manopola bianca nel chiaro; nel tema scuro attiva in `primary-foreground`, spenta in `foreground`.

### Cards / Containers
- Raggio `xl`, bordo 1px, fondo carta, padding 20px, nessuna ombra. `StatCard`: etichetta attenuata, tessera icona 32px su velo, cifra grande tabulare. Niente icona-piu-titolo-piu-testo ripetuto come arredo: le carte servono per raggruppare, non per decorare.
- **Servizio (pagina pubblica):** punto piatto del colore del servizio, non barre colorate. La pagina del servizio mantiene una fascia da 6px (`h-1.5`) col colore del servizio sulla testa della carta (vedi nota sulle divergenze).

### Navigation
Barra laterale su fondo `sidebar`, voci da 36px, gruppi con etichetta maiuscola 11px, voce attiva in `sidebar-accent`/primario, conteggio richieste accanto alla voce. Sul telefono: cassetto da 18rem aperto dal pulsante in barra sticky. L'intestazione pubblica e' un filetto sotto, `bg-card/80` con sfocatura, logo e nome del portale (800).

### Table
Righe con filetto inferiore e hover `muted/50`; testata a 40px, 0.75rem maiuscolo `muted-ink`; celle `px-3 py-2.5`. Dentro una carta con `overflow-hidden rounded-xl`.

### Badge / ColorBadge
Pillola (`full`), 0.75rem, 500; `ColorBadge` la porta a 600 con la coppia tinta/testo del colore nominato dal server.

### Agenda (componente distintivo)
Evento: raggio 8px, bordo sinistro 3px nel colore operatore, fondo `color-mix(colore 22%, carta)`; annullato a opacita' 50% con testo barrato. Settimana corta: una riga "orario + nome"; sopra i 45 minuti compaiono servizio e risorsa. "Oggi": cerchio primario sul giorno, tinta `primary/5` sulla colonna o cella. Calendario slot pubblico: giorni da cella con raggio 10px, giorno scelto in primario; i giorni non disponibili sono attenuati (vedi limiti noti).

### Brand mark
Il simbolo Atrio (`AppLogoIcon`, `AppLogoMark` in `app-logo.tsx`) e' una «A» asimmetrica di due lastre e una barra, con il piede destro a punto. `AppLogoMark` e' una tessera grafite #191c22 (raggio `lg`, anello 1px nero/bianco al 5%/10%), «A» in #f5f7fa, uguale in chiaro e scuro. Il punto e' cobalto fisso #3a5cff e non segue `--primary`, nemmeno sulle pagine pubbliche. Favicon (`favicon.svg|ico`) e `apple-touch-icon.png` sono lo stesso simbolo. Le pagine d'errore (`errors/layout.blade.php`) usano neutri freddi (#f5f7fa, #191c22, filetto #d2d6dc) e accento #3a5cff, scuro #6489fd su #0f131a.

### Empty state
Riquadro tratteggiato, raggio `xl`, fondo `card/50`, icona in tessera da 48px su velo, titolo 700.

## Do's and Don'ts

### Do:
- **Do** usare `bg-primary`/`text-primary`/`ring` per ogni azione principale, selezione e "oggi", cosi' il colore del portale subentra da solo sulle pagine pubbliche.
- **Do** separare con filetti da 1px (`border`) e con il salto fondo/carta/velo.
- **Do** tenere a 44px ogni bersaglio tocco sotto `md` (bottoni, icone, campi, righe evento).
- **Do** mostrare il colore operatore/servizio come punto da 6-10px, bordo sinistro da 3px o tinta al 22%.
- **Do** mettere `tabular-nums` su orari, conteggi e cifre.
- **Do** riscrivere i token in `.dark` con gli stessi nomi, mantenendo la struttura.

### Don't:
- **Don't** introdurre un secondo accento di marchio: il colore extra viene dai dati.
- **Don't** usare gradienti, gradienti sul testo, ombre pesanti o ombre a riposo.
- **Don't** usare `--accent` come colore del marchio: e' il grigio di hover.
- **Don't** scrivere a mano il cobalto nelle pagine pubbliche: usare `primary` (il punto del simbolo Atrio e' l'unica eccezione, e sta nel marchio).
- **Don't** usare barre colorate di testa sulle carte servizio dell'elenco: il colore e' un punto.
- **Don't** impilare carte uguali con icona, titolo e testo come arredo.

## Known Gaps (non canonizzati)

- I giorni non disponibili del calendario pubblico, attenuati come testo disabilitato, misurano 4.4:1: lacuna nota e accettata dalla revisione di chiusura, non una regola. Non estendere questo valore ad altro testo che deve restare leggibile.
- `.font-time` e' definita ma inutilizzata; il contratto di direzione la prevedeva per orari e tasti.
- Il contratto prevedeva richieste da confermare "tratteggiate" in agenda: il codice non usa un tratteggio sugli eventi in attesa (il tratteggio e' solo dello stato vuoto).
- Le etichette maiuscole piccole di testata tabella e gruppi della barra laterale sono etichette di navigazione/colonna, non un modello per sopratitoli ("eyebrow") sopra i titoli: quelli non vanno creati.
- La fascia di 6px colorata sulla pagina del servizio (`portal/service.tsx`) e' sopravvissuta alla correzione "punto piatto" fatta sulle carte dell'elenco: incoerenza lasciata com'e', non registrata come regola.
- Il punto del simbolo (#3a5cff) sulla superficie alternativa scura misura 2.8:1: limite di progetto dell'audit della palette d'identita', non da estendere ad altri elementi grafici che devono restare leggibili.
- Nel tema scuro il link in primario sul testo misura 2.9:1: richiede la sottolineatura (non affidarsi solo al colore); limite di progetto dell'audit della palette.
- `.font-time` resta inutilizzata dopo il cambio di identita'.
