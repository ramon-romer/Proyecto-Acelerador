# Subbateria PDF sintetica ANECA

Subconjunto reducido para validar el flujo real PDF/OCR/Pipeline sin convertir los 250 casos.

## Cobertura
- 2 casos por rama canonica.
- 10 PDFs en total.
- Seleccion preferente: positivo + problematico/frontera/negativo.

## Archivos
- `pdf_subset_manifest.json`
- `<ID>/cv.pdf`
- `<ID>/expected.json`
- `<ID>/source_case.txt`
- `<ID>/README.md`

## Regeneracion reproducible
```bash
php evaluador/tests/tools/generate_synthetic_cv_pdf_subset.php --force
```

## Runner de pipeline
```bash
php evaluador/tests/run_synthetic_cv_pdf_pipeline.php
```

El runner clasifica `PASS`, `WARN`, `FAIL` y `SKIP_ENV` para distinguir fallos funcionales de limitaciones de entorno OCR/PDF.

## Ejecucion recomendada en Docker
Ver guia operativa OCR/PDF:
- `docs/despliegue-docker-ocr.md`

Comando tipico (perfil dev):
```bash
docker compose --profile dev run --rm acelerador-php-dev php evaluador/tests/run_synthetic_cv_pdf_pipeline.php
```
