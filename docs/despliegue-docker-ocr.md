# Despliegue Docker OCR (Acelerador)

El pipeline de lectura de PDFs en `meritos/scraping` requiere estas dependencias del sistema:

- `poppler-utils` (incluye `pdftoppm`)
- `tesseract-ocr`
- `tesseract-ocr-spa` (idioma español)

## Build del entorno

```bash
docker compose build acelerador-php
```

## Comprobaciones dentro del contenedor

```bash
docker compose run --rm acelerador-php pdftoppm -v
docker compose run --rm acelerador-php tesseract --version
docker compose run --rm acelerador-php php meritos/scraping/tools/check_ocr_environment.php
```

## Check post-despliegue (obligatorio)

```bash
php meritos/scraping/tools/check_ocr_environment.php
```

Resultado esperado:

- `"ocr_ready": true`
- `"mode": "hybrid_ready"`

