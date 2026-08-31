---
paths:
  - '**'
---

# General

## Leggi e aggiorna LOG.md a ogni sessione
`LOG.md` in radice è il diario di bordo del progetto: stato, stack, mappa del codice, comandi di verifica, credenziali di seed, backlog e cronologia delle sessioni.

- **Prima di lavorare**: leggilo per sapere a che punto siamo e quali problemi noti esistono.
- **Alla fine di ogni sessione**: aggiungi una voce nel Diario (in cima, ordine cronologico inverso) usando il formato descritto nel file — Obiettivo / Fatto / Decisioni / Problemi e soluzioni / Verifica / Aperto. Aggiorna il Backlog spuntando o aggiungendo voci.
- Riporta gli esiti reali dei comandi di verifica, fallimenti compresi.
- Divisione delle responsabilità: `.ai/rules` = normativo ("come si fa qui"), `LOG.md` = cronologico ("cosa è successo e perché"). Una lezione permanente va in `.ai/rules` con `record-rule`, non ricopiata nel log.

## startOfMonth before addMonth/subMonth
Carbon month arithmetic overflows: `now()->addMonth()` on 31 Aug gives 1 Oct, and `now()->subMonth()` on 31 Mar gives 3 Mar. Always normalise first — `now()->startOfMonth()->subMonth()`, never `now()->subMonth()->startOfMonth()`. Same in tests: a test written on the 12th passes for weeks and then fails on the 31st.
