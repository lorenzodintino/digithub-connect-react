---
paths:
  - 'resources/js/vetrina/**'
---

# Vetrina

## Vetrina: CSS isolato sotto .vetrina, motori con mount/destroy, testi da __()
La home (`pages/welcome.tsx` → `vetrina/vetrina.tsx`) è la vetrina del prodotto, portata dalla bozza chiusa. (1) Tutto il suo CSS è in `resources/css/vetrina.css` e vive SOTTO `.vetrina` (`html.dark .vetrina`, `html[data-palette='x'] .vetrina`): non scrivere mai selettori globali lì dentro; senza `data-palette` (o con `basic`) vale Basic, il set definito per primo in «palettes»; Oltremare e Lampone lo ridefiniscono con l'attributo, e sull'home la scelta del visitatore sta in localStorage `atrio.palette`. Il file nasce da uno script di isolamento dei CSS della bozza: ritocchi a mano in fondo, sotto «aggiunte del porting». (2) Struttura e testi sono React (`sections/*.tsx`, ogni testo da `__()` con chiave inglese; `WelcomePageTest` fallisce se una chiave manca in `lang/it.json`); calendario 3D, demo del telefono, email e portale col marchio sono il motore `engine/atrio.ts` (`// @ts-nocheck`, `mountAtrio(host)` che ritorna la funzione di smontaggio): ogni listener su window/document, observer e rAF nuovo va registrato con `on()`/`observe()` o in `cleanups`, altrimenti sopravvive alla navigazione Inertia. (3) Le classi `ready`, `is-in`, `no-gl` sono messe via `classList` da effetti/motore: non metterle nel `className` di React (lo cancellerebbe). (4) Giorni e mesi vengono da `Intl` con `getLocale()`, i plurali da `trans_choice`. (5) three è fissata a 0.170.0 (la versione collaudata): non aggiornarla senza rifare il controllo visivo.
