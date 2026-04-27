# Despliegue Docker OCR (Acelerador)

El pipeline de lectura de PDFs en `meritos/scraping` requiere estas dependencias del sistema:

- `poppler-utils` (incluye `pdftoppm`)
- `tesseract-ocr`
- `tesseract-ocr-spa` (idioma espanol)

## Build del entorno

```bash
docker compose build acelerador-php
```

Para desarrollo con bind mount del repo:

```bash
docker compose --profile dev build acelerador-php-dev
```

## Comprobaciones dentro del contenedor

```bash
docker compose run --rm acelerador-php pdftoppm -v
docker compose run --rm acelerador-php tesseract --version
docker compose run --rm acelerador-php php meritos/scraping/tools/check_ocr_environment.php
```

Validacion equivalente en perfil dev:

```bash
docker compose --profile dev run --rm acelerador-php-dev pdftoppm -v
docker compose --profile dev run --rm acelerador-php-dev tesseract --version
docker compose --profile dev run --rm acelerador-php-dev php meritos/scraping/tools/check_ocr_environment.php
```

## Check post-despliegue (obligatorio)

```bash
docker compose run --rm acelerador-php php meritos/scraping/tools/check_ocr_environment.php
```

Resultado esperado:

- `"ocr_ready": true`
- `"mode": "hybrid_ready"`

## Nota operativa

- `acelerador-php` usa imagen autosuficiente y volumenes persistentes (`scraping-output`, `scraping-pdfs`).
- `acelerador-php-dev` mantiene bind mount `./:/app` para iteracion local, sin perder persistencia de output/pdfs.
- Evitar instalar paquetes manualmente con `apt`/`composer` dentro de `docker compose run --rm`; esos cambios no persisten.


