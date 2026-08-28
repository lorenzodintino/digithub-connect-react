---
paths:
  - 'tests/**'
---

# Tests

## Niente GD: non usare UploadedFile::fake()->image()
Il PHP di questo progetto (herd-lite, build statica senza cartella ext) non ha l'estensione GD, quindi `UploadedFile::fake()->image()` fallisce con "GD extension is not installed."

Per i file di test usa `UploadedFile::fake()->createWithContent($name, $bytes)` con i byte reali di un PNG minimo, eventualmente riempito con NUL fino al peso voluto: finfo legge i magic bytes iniziali e la regola `image`/`mimes` passa lo stesso. Vedi `fakeLogo()` in `tests/Feature/PortalLogoTest.php`.
