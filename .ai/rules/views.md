---
paths:
  - 'resources/views/**'
---

# Views

## Da $wire si chiamano solo i metodi del componente, non quelli del framework
`$wire.qualcosa()` nel markup risolve **solo** i metodi pubblici dichiarati sul componente. I metodi che arrivano dai trait di Livewire (`resetValidation`, `resetPage`, `reset`, `validate`…) non sono esposti al browser: la chiamata muore con `MethodNotFoundException: Public method [x] not found on component`, e siccome parte da un evento del markup (`@close`, `x-on:`) l'errore esce come 500 nel bel mezzo di un'altra azione — trovato il 2026-09-05 su `@close="$wire.resetValidation()"` del modale portali, che esplodeva proprio mentre mostrava la password generata. Se serve davvero un'azione alla chiusura, dichiara un metodo pubblico sul componente e chiama quello; spesso non serve, perché chi apre il modale ha già fatto il reset.

## Conteggi con trans_choice, e nessun controllo senza nome
Due trappole trovate tre volte, sempre dal collaudo a mano e mai dai test.

- **Ogni numero a schermo passa da `trans_choice`**, con singolare e plurale scritti in inglese e in italiano: `trans_choice('one seat free|:count seats free', $n)`. Un `__(':count posti liberi')` produce «1 posti liberi». Vale per avvisi, badge, toast, guardie di eliminazione.
- **Ogni `flux:select`, filtro o tendina ha una `:label`**, anche in una barra compatta. Scelto un valore, il valore si nomina ma la dimensione no: «Europe/Rome» sopra una colonna di orari sembra un'impostazione che nessuno ha chiesto, «Giulia Bianchi» non dice «operatore». Riusa le chiavi esistenti (`Timezone` = «Fuso orario») invece di inventare etichette nuove.
