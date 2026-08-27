---
paths:
  - 'resources/views/pages/**'
---

# Pages

## Page components: single root, layout via slot
Livewire 4 full-page SFCs must render ONE root element. Do not wrap the view in `<x-layouts::app>` — Livewire applies `layouts::app` automatically. Wrapping caused `MultipleRootElementsDetectedException`.

Pattern used everywhere:

```blade
<x-slot:title>{{ __('Services') }}</x-slot>

<div class="w-full">
    ...page content and all flux:modal blocks...
</div>
```

The `<x-slot:title>` sits outside the root (allowed for layout slots) and keeps the browser title translatable — `#[Title]` cannot call `__()`.

Public pages use `#[Layout('layouts::portal')]` (a bare html shell) and compose `<x-portal-header>` / `<x-portal-footer>` inside their own root, with `style="--portal-brand: {{ $tenant->brand_color }}"` on that root.
