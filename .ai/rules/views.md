---
paths:
  - 'resources/views/**'
---

# Views

## Da $wire si chiamano solo i metodi del componente, non quelli del framework
`$wire.qualcosa()` nel markup risolve **solo** i metodi pubblici dichiarati sul componente. I metodi che arrivano dai trait di Livewire (`resetValidation`, `resetPage`, `reset`, `validate`…) non sono esposti al browser: la chiamata muore con `MethodNotFoundException: Public method [x] not found on component`, e siccome parte da un evento del markup (`@close`, `x-on:`) l'errore esce come 500 nel bel mezzo di un'altra azione — trovato il 2026-09-05 su `@close="$wire.resetValidation()"` del modale portali, che esplodeva proprio mentre mostrava la password generata. Se serve davvero un'azione alla chiusura, dichiara un metodo pubblico sul componente e chiama quello; spesso non serve, perché chi apre il modale ha già fatto il reset.
