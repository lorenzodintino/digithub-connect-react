---
paths:
  - resources/js/hooks/use-appearance.ts
---

# Hooks

## Tema chiaro/scuro: un solo stato condiviso e animazione con View Transitions
Il tema vive in `useAppearance()` (`resources/js/hooks/use-appearance.ts`), che legge e scrive uno **stato di modulo condiviso** (`useSyncExternalStore`): il pulsante rapido `ThemeToggle` nella barra laterale e le Impostazioni › Aspetto restano sincronizzati senza ricaricare. Chiave di salvataggio `flux.appearance` (come ai tempi di Flux; «sistema» = nessun valore), lo script in `app.blade.php` applica la classe `dark` prima del disegno. `toggle()` inverte ciò che si vede ora e anima con la View Transitions API: cerchio che cresce dal pulsante (`::view-transition-new(root)` con `clip-path`, regole CSS in `app.css` che spengono la dissolvenza predefinita). Senza `document.startViewTransition` o con `prefers-reduced-motion` il tema cambia e basta. Non cambiare la classe `dark` fuori da questo hook. Lo strumento di test CDP del progetto non consegna clic del mouse a queste pagine: nei collaudi usare `element.click()` dentro la pagina.
