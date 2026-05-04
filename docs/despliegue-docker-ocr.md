# Despliegue Docker OCR (Acelerador)

Guia operativa para preparar OCR/PDF y ejecutar la subbateria sintetica PDF del evaluador.

Dependencias requeridas:
- `poppler-utils` (incluye `pdftoppm`)
- `tesseract-ocr`
- `tesseract-ocr-spa` (idioma espanol)

## Build del entorno

```bash
docker compose build acelerador-php
```

Para desarrollo con bind mount del repo actual:

```bash
docker compose --profile dev build acelerador-php-dev
```

## Verificacion minima de entorno OCR

Host local (si aplica):

```bash
pdftoppm -v
tesseract --version
tesseract --list-langs
php meritos/scraping/tools/check_ocr_environment.php
```

Docker (imagen estandar):

```bash
docker compose run --rm acelerador-php pdftoppm -v
docker compose run --rm acelerador-php tesseract --version
docker compose run --rm acelerador-php tesseract --list-langs
docker compose run --rm acelerador-php php meritos/scraping/tools/check_ocr_environment.php
```

Docker (perfil dev, recomendado para probar cambios locales sin rebuild completo):

```bash
docker compose --profile dev run --rm acelerador-php-dev pdftoppm -v
docker compose --profile dev run --rm acelerador-php-dev tesseract --version
docker compose --profile dev run --rm acelerador-php-dev tesseract --list-langs
docker compose --profile dev run --rm acelerador-php-dev php meritos/scraping/tools/check_ocr_environment.php
```

Resultado esperado del checker OCR:
- `"ocr_ready": true`
- `"mode": "hybrid_ready"`

## Subbateria sintetica PDF (evaluador)

### 1) Regenerar subset PDF (10 casos) si hace falta

```bash
php evaluador/tests/tools/generate_synthetic_cv_pdf_subset.php --force
```

### 2) Ejecutar runner PDF/OCR del evaluador

```bash
php evaluador/tests/run_synthetic_cv_pdf_pipeline.php
```

### 3) Comandos equivalentes en Docker dev

```bash
docker compose --profile dev run --rm acelerador-php-dev php evaluador/tests/tools/generate_synthetic_cv_pdf_subset.php --force
docker compose --profile dev run --rm acelerador-php-dev php evaluador/tests/run_synthetic_cv_pdf_pipeline.php
```

## Interpretacion de estados del runner PDF

- `PASS`: checks minimos cumplidos (rama/ORCID/campos clave/JSON generado).
- `WARN`: ejecucion completada con desviaciones menores.
- `FAIL`: error funcional de pipeline o mismatch relevante.
- `SKIP_ENV`: entorno OCR/PDF no preparado (`pdftotext`, `pdftoppm`, `tesseract`, idioma, etc.).

`SKIP_ENV` no implica fallo funcional del evaluador.

## Troubleshooting rapido

### `tesseract_disponible=false`
- Verificar `tesseract --version` en el mismo entorno donde corre PHP.
- En Docker, usar `acelerador-php` o `acelerador-php-dev` del compose de este repo.

### `pdftoppm` no encontrado
- Verificar `pdftoppm -v`.
- Confirmar instalacion de `poppler-utils`.

### Idioma `spa` no instalado
- Verificar `tesseract --list-langs` y confirmar que aparece `spa`.
- En Debian/Ubuntu instalar `tesseract-ocr-spa`.

### Permisos/rutas
- Confirmar permisos de lectura sobre `evaluador/tests/fixtures/cv_sinteticos_pdf/*/cv.pdf`.
- Confirmar que `evaluador/output/json` es escribible por el usuario del proceso PHP.

### Diferencia Windows/XAMPP vs Linux/Docker
- En Windows/XAMPP es comun tener `SKIP_ENV` por OCR incompleto.
- Para validacion estable, ejecutar en Docker Linux con dependencias declaradas en `Dockerfile`.

## Nota operativa

- `acelerador-php` usa imagen autosuficiente y volumenes persistentes (`scraping-output`, `scraping-pdfs`).
- `acelerador-php-dev` mantiene bind mount `./:/app` para iteracion local, sin perder persistencia de output/pdfs.
- Evitar instalar paquetes manualmente con `apt`/`composer` dentro de `docker compose run --rm`; esos cambios no persisten.


