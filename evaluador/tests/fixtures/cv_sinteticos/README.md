# Dataset sintetico de CV ANECA

Dataset interno de prueba para estresar el pipeline de extraccion/evaluacion ANECA sin usar datos personales reales.

## Objetivo
- Cubrir ramas canonicas del proyecto con casos positivos, negativos, frontera y problematicos.
- Mantener fixtures reproducibles y aislados en `evaluador/tests/fixtures/cv_sinteticos`.
- Facilitar ampliacion gradual de 2 a 20 casos por rama.

## Ramas canonicas detectadas y mapeo
- `EXPERIMENTALES` <= Experimentales
- `TECNICAS` <= Tecnicas
- `CSYJ` <= Sociales, Juridicas
- `SALUD` <= Salud, Biomedicas, Ciencias de la Salud
- `HUMANIDADES` <= Arte y Humanidades

## Estructura
- `<rama>/<ID>/cv.txt`
- `<rama>/<ID>/cv_cvn_like.txt`
- `<rama>/<ID>/expected.json`
- `<rama>/<ID>/README.md`
- `dataset_manifest.json`

## Generacion reproducible
```bash
php evaluador/tests/tools/generate_synthetic_cv_dataset.php --per-rama=2 --seed=20260504 --force
```

Para ampliar a 20 CV por rama:
```bash
php evaluador/tests/tools/generate_synthetic_cv_dataset.php --per-rama=20 --seed=20260504 --force
```

## Validacion del dataset
```bash
php evaluador/tests/validate_synthetic_cv_dataset.php
```

## Regresion sintetica (semi E2E)
Runner recomendado:
```bash
php evaluador/tests/run_synthetic_cv_regression.php
```

Filtros opcionales:
```bash
php evaluador/tests/run_synthetic_cv_regression.php --rama=EXPERIMENTALES
php evaluador/tests/run_synthetic_cv_regression.php --perfil=positivo
php evaluador/tests/run_synthetic_cv_regression.php --json
```

El runner valida `cv.txt + expected.json` y, si existe `cv_cvn_like.txt`, ejecuta extractor ANECA para una senal semi end-to-end sin pasar por PDF/OCR.

## Validacion sintetica por ramas ANECA
Comando rapido (dataset actual, sin regenerar):
```bash
php evaluador/tests/tools/run_synthetic_cv_megatest.php --strict
```

Comando nightly seguro (valida dataset + megatest, sin regenerar por defecto):
```bash
php evaluador/tests/tools/run_synthetic_cv_megatest.php --nightly --strict
```

Regenerar dataset de forma explicita y luego validar:
```bash
php evaluador/tests/tools/run_synthetic_cv_megatest.php --nightly --generate --per-rama=50 --seed=20260504 --strict
```

Interpretacion de reportes (`reports/test-validation/<timestamp>-synthetic-cv-megatest/`):
- `synthetic_cv_megatest_report.json`: detalle por caso + resumen global.
- `synthetic_cv_megatest_report.md`: resumen legible para seguimiento historico.
- Campos clave: `total_cases`, `by_branch`, `resultado_match_rate`, `full_match_rate`, `warnings`, `failures`, `duration_ms`.
- Exit code: `0` si todo OK, `!= 0` si hay fallos (y en `--strict`, tambien warnings).

Limitaciones conocidas:
- El megatest es semi E2E con `cv_cvn_like.txt` (sin OCR/PDF real).
- `resultado_obtenido` usa heuristicas de perfil para baseline, no el flujo OCR completo.
- Para validacion OCR/PDF usar una tercera pasada dedicada.

## Subbateria PDF/OCR (tercera pasada controlada)
Generar subbateria PDF reducida (10 PDFs, 2 por rama):
```bash
php evaluador/tests/tools/generate_synthetic_cv_pdf_subset.php --force
```

Ejecutar pipeline real PDF/OCR sobre la subbateria:
```bash
php evaluador/tests/run_synthetic_cv_pdf_pipeline.php
```

Ubicacion:
- `evaluador/tests/fixtures/cv_sinteticos_pdf/`
- `reports/test-validation/<timestamp>-synthetic-cv-pdf-pipeline/`

Notas operativas:
- Este runner clasifica `SKIP_ENV` cuando faltan dependencias de entorno (`pdftotext`, OCR).
- `SKIP_ENV` no implica fallo funcional del codigo del evaluador.

## Notas
- No depende de descargas externas.
- No toca `evaluador/src` ni contratos JSON existentes del core.
- La subbateria PDF esta aislada y no convierte los 250 casos a PDF.
