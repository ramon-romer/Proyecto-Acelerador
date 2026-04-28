# Arquitectura de Contratos JSON - Acelerador v1

Fecha de consolidacion: 2026-04-21

## Decision oficial e inmutable de arquitectura

El **unico contrato canonico oficial de dominio** del proyecto Acelerador es:

- `docs/schemas/contrato-canonico-aneca-v1.schema.json`

Esta decision se mantiene:

- en modo desacoplado / sin MCP;
- con pipelines alternativos (OCR, scraping, MCP, ORCID u otros);
- independientemente del extractor transitorio que este en runtime.

Ningun contrato tecnico local de `meritos/scraping` sustituye ni redefine el contrato canonico ANECA.

## Clasificacion oficial de contratos

### 1) Contrato canonico oficial del proyecto

- `docs/schemas/contrato-canonico-aneca-v1.schema.json`
- Uso: contrato de dominio ANECA (corazon funcional del sistema).
- Estado: **vigente oficial**.

### 2) Contratos tecnicos internos del modulo `meritos/scraping`

- `docs/schemas/processing-job.v1.schema.json`
  - Estado: **vigente tecnico interno**.
  - Ambito: formato de jobs en cola/worker.

- `docs/schemas/processing-cache.v1.schema.json`
  - Estado: **vigente tecnico interno**.
  - Ambito: metadatos de cache por hash+versiones.

- `docs/schemas/api-response.v1.schema.json`
  - Estado: **vigente tecnico local**.
  - Ambito: respuestas de `meritos/scraping/public/subir.php` y `api_cv_procesar.php`.

### 3) Contrato legacy/transitorio

- `docs/schemas/pipeline-result-legacy.v1.schema.json`
  - Estado: **legacy/transitorio**.
  - Ambito: salida actual del pipeline legacy (`tipo_documento`, `iva`, etc.).
  - Nota: **NO canonico**.
  - Plan: retirar cuando el pipeline entregue ANECA canonico directamente.

## Trazabilidad a productores/consumidores reales

- Productor de salida legacy actual:
  - `meritos/scraping/src/AnecaExtractor.php`
  - `meritos/scraping/src/Pipeline.php`

- Consumidores que aun dependen del payload legacy (`resultado_json` con `tipo_documento`, `iva`, etc.):
  - `meritos/scraping/src/LegacyPipelineResultValidator.php`
  - `meritos/scraping/src/PipelineResultValidator.php` (alias transitorio de compatibilidad)
  - `meritos/scraping/public/subir.php` (campo `resultado`)
  - `meritos/scraping/public/api_cv_procesar.php` (campo `resultado`)
  - `meritos/scraping/tools/smoke_jobs_queue.php`
  - `docs/schemas/pipeline-result-legacy.v1.schema.json`
  - `docs/schemas/api-response.v1.schema.json` (`$defs.pipeline_result`)

- Productores/consumidores tecnicos internos:
  - `meritos/scraping/src/ProcessingJobQueue.php`
  - `meritos/scraping/src/ProcessingCache.php`
  - `meritos/scraping/src/CvProcessingJobService.php`
  - `meritos/scraping/src/ProcessingJobWorker.php`
  - `meritos/scraping/public/subir.php`
  - `meritos/scraping/public/api_cv_procesar.php`

- Validador canonico ANECA (separado de validaciones tecnicas locales):
  - `evaluador/tests/validate_canonical_schema.php`

## Regla de normalizacion obligatoria

Toda salida tecnica (scraping/OCR/MCP/ORCID/pipeline alternativo) debe:

1. producir su formato tecnico interno si el runtime lo requiere;
2. **normalizar/adaptar** a `contrato-canonico-aneca-v1.schema.json` antes de su uso como dato de dominio.

En `meritos/scraping` se deja una capa de adaptacion explicita:

- `meritos/scraping/src/AnecaCanonicalAdapter.php`
- `meritos/scraping/src/AnecaCanonicalResultValidator.php`

Y trazabilidad tecnica de convergencia en jobs/cache/API:

- `aneca_canonical_path`
- `aneca_canonical_ready`
- `aneca_canonical_validation_status`
- `resultado_principal_formato` (aneca|legacy)
- `resultado_principal_path`

En job/cache, `resultado_json` se mantiene por compatibilidad transitoria, pero el runtime ya expone
un descriptor interno de artefacto principal (`resultado_principal_*`) para reducir el sesgo legacy-first.
`resultado_principal_formato` representa el artefacto principal EFECTIVO/operativo (no solo disponibilidad tecnica).
Ademas, `ProcessingJobWorker` aplica criterio operativo `aneca_operativo` cuando ANECA esta lista/usable
y degrada a `legacy_fallback` cuando no lo esta, manteniendo continuidad con `resultado_json`.
`CvProcessingJobService` usa el mismo criterio en cache-hit mediante `OperationalArtifactDecisionResolver`
para evitar divergencias entre subida sincronica y ejecucion posterior del worker.
`ProcessingCache::validateCurrentMeta()` aplica tambien esa decision operativa para validar reutilizacion
de cache meta (`aneca_operativo` vs `legacy_fallback`) manteniendo compatibilidad con metadatos antiguos.

## Validaciones separadas por responsabilidad

- Canonico de dominio ANECA:
  - `php evaluador/tests/validate_canonical_schema.php`
  - `meritos/scraping/src/AnecaCanonicalResultValidator.php`

- Tecnica legacy transitoria (payload heredado):
  - `meritos/scraping/src/LegacyPipelineResultValidator.php`
  - `meritos/scraping/src/PipelineResultValidator.php` (alias temporal para no romper imports legacy)

- Contratos tecnicos internos de scraping:
  - `php meritos/scraping/tools/validate_scraping_technical_contracts.php`

## Contratos fuera de alcance de este modulo

- `acelerador_panel/backend/docs/02-api-rest-contratos.md`
  - Dominio: tutorias.
  - Estado respecto a `meritos/scraping`: **no aplicable**.
  - No debe usarse para definir contrato de scraping CV/ANECA.
