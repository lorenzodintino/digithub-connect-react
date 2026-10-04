---
paths:
  - 'resources/views/flux/**'
---

# Flux

## Accessible labels must follow the request locale
Blaze fold/memo can freeze __() labels in Flux components using the first compiled locale. Local input viewable/copyable/clearable, modal and toast overrides disable fold/memo while keeping compiled rendering. Keep translated input labels on callers too; cover EN→IT requests in a single regression test. Do not edit vendor files.
