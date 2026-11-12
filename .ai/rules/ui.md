---
paths:
  - 'resources/js/components/ui/**'
---

# Ui

## CLI di shadcn: corregge l'import di cn e installa un pacchetto sbagliato
`npx shadcn add` in questo progetto scrive `import { cn } from "cn"` e installa il pacchetto npm `cn`, estraneo. Dopo ogni `add`: `npm uninstall cn` e sostituisci l'import con `@/lib/utils`. I testi per screen reader dei componenti generati (Close, Toggle Sidebar…) vanno passati da `__()`: i componenti sono nostri, si modificano.
