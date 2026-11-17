---
paths:
  - 'app/Actions/Plans/**'
---

# Plans

## Chiedere un piano superiore: richiesta, non cambio
Deciso il 2026-11-17. Il manager non cambia il piano da solo: `POST app/plan/upgrade` (`RequestPlanUpgrade`) manda un'email a ogni admin attivo e scrive `plan.upgrade_requested` nel registro; il piano lo cambia l'admin (o lo dirà il License Manager). Si può chiedere solo un piano in vendita con `position` maggiore di quello in vigore (`Tenant::upgradePlans()`); senza piano o al piano più alto non c'è nulla da chiedere. Il registro tiene piani e motivo, mai la nota libera. Limite 5 richieste all'ora. I prezzi non arrivano mai al manager (la prop condivisa `auth.user.tenant.plan.upgrades` ha solo i limiti). Dove sta il pulsante (`components/upgrade-request.tsx`, sparisce da solo se non c'è un piano sopra): scheda «Il tuo piano», pagina Risorse bloccata, avviso SMTP, errore di servizi/operatori (si riconosce dalla chiave `plan_limit` nell'errore di validazione) e avviso delle prenotazioni mensili (link `#your-plan` nelle impostazioni portale). Un nuovo limite di piano deve aggiungere `plan_limit` all'errore e il pulsante accanto.
