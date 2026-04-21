# Estado de Convergencia ANECA (2026-04-21)

## 1) Resumen

- El contrato canonico oficial se mantiene en `docs/schemas/contrato-canonico-aneca-v1.schema.json`.
- Se reforzo la convergencia sin romper runtime legacy:
  - adaptacion ANECA con contenido util minimo;
  - validacion canonic+calidad;
  - trazabilidad ANECA persistida en job/cache/API;
  - consumo preferente ANECA opcional con fallback legacy.

## 2) Estado del adaptador

### Estado actual

- `meritos/scraping/src/AnecaCanonicalAdapter.php` ya no emite solo estructura vacia.
- Ahora clasifica lineas de texto extraido por secciones ANECA (bloques 1-3) y usa fallback controlado en `bloque_4`.
- Conserva compatibilidad con salida legacy del pipeline.

### Cambios clave

- Mapeo heuristico por keywords a secciones:
  - publicaciones, libros, proyectos, transferencia, tesis, congresos;
  - docencia/evaluacion/formacion/material;
  - formacion academica y experiencia profesional.
- Fallback de evidencias legacy en `bloque_4` cuando aplica.
- Metadatos de adaptacion (lineas analizadas, evidencias, secciones con contenido).

## 3) Calidad del JSON ANECA generado

### Validador tecnico nuevo

- `meritos/scraping/src/AnecaCanonicalResultValidator.php`
- Valida:
  - schema canonico;
  - texto extraido minimo;
  - cobertura/evidencias;
  - estado: `valido`, `valido_con_advertencias`, `incompleto`, `invalido`.

### Evidencia E2E ejecutada

- Script: `php meritos/scraping/tools/validate_aneca_canonical_adapter.php`
- Run: `aneca_adapter_20260421_150022_e6581d`
- Resultado:
  - `adaptador_genera_json_aneca_schema_ok`: PASS
  - `adaptador_genera_json_aneca_util`: PASS
  - `quality_status`: `valido_con_advertencias`
  - warning observado: `comite_no_identificado`

## 4) Trazabilidad ANECA en runtime

Se incorporaron metadatos tecnicos:

- `aneca_canonical_path`
- `aneca_canonical_ready`
- `aneca_canonical_validation_status`

### Persistencia

- Job JSON: `meritos/scraping/src/ProcessingJobQueue.php` + `ProcessingJobWorker.php` + `CvProcessingJobService.php`
- Cache meta JSON: `meritos/scraping/src/ProcessingCache.php`
- API local: `meritos/scraping/public/api_cv_procesar.php` y `subir.php`

### Logs/trace

- `Pipeline.php` ahora escribe:
  - `aneca_canonical_file`
  - `aneca_canonical_ready`
  - `aneca_canonical_validation_status`

## 5) Consumidores legacy detectados

Consumo legacy activo (todavia necesario):

- Productor legacy:
  - `meritos/scraping/src/AnecaExtractor.php`
- Validador legacy:
  - `meritos/scraping/src/PipelineResultValidator.php`
- Payload API por defecto (`resultado`):
  - `meritos/scraping/public/subir.php`
  - `meritos/scraping/public/api_cv_procesar.php`
- Schemas tecnicos legacy:
  - `docs/schemas/pipeline-result-legacy.v1.schema.json`
  - `docs/schemas/api-response.v1.schema.json` (`$defs.pipeline_result`)
- Smokes/fixtures legacy:
  - `meritos/scraping/tools/smoke_jobs_queue.php`
  - `meritos/scraping/tools/validate_scraping_technical_contracts.php`

## 6) Cambios aplicados

### Codigo

- `meritos/scraping/src/AnecaCanonicalAdapter.php`
- `meritos/scraping/src/AnecaCanonicalResultValidator.php` (nuevo)
- `meritos/scraping/src/Pipeline.php`
- `meritos/scraping/src/ProcessingCache.php`
- `meritos/scraping/src/ProcessingJobQueue.php`
- `meritos/scraping/src/ProcessingJobWorker.php`
- `meritos/scraping/src/CvProcessingJobService.php`
- `meritos/scraping/public/api_cv_procesar.php`
- `meritos/scraping/public/subir.php`

### Tests/validadores

- `meritos/scraping/tools/validate_aneca_canonical_adapter.php`
- `meritos/scraping/tools/smoke_jobs_queue.php`
- `meritos/scraping/tools/validate_scraping_technical_contracts.php`

### Documentacion/schemas

- `docs/schemas/processing-job.v1.schema.json`
- `docs/schemas/processing-cache.v1.schema.json`
- `docs/schemas/api-response.v1.schema.json`
- `docs/schemas/README.md`
- `docs/schemas/validacion-contrato-canonico-aneca-v1.md`
- `docs/contratos-json-acelerador-v1.md`

## 7) Plan de retirada del legacy

### Dependencias actuales

- El backend/API local sigue entregando `resultado` legacy por defecto.
- `PipelineResultValidator` y smoke tests siguen modelando el payload legacy.
- No hay aun productor nativo ANECA directo desde extractor semantico.

### Bloqueos

- Extractor actual (`AnecaExtractor`) no produce dominio ANECA real; produce campos transitorios.
- Consumidores actuales aun esperan claves legacy en `resultado`.

### Pasos minimos (compatibles)

1. Mantener `resultado` legacy temporalmente y migrar consumidores a:
   - `aneca_canonical_ready=true`
   - `resultado_aneca_canonico` (cuando se solicite `include_aneca=1`)
   - `resultado_preferente` con `prefer_aneca=1`.
2. Introducir validador de dominio ANECA en punto de consumo backend principal (no solo smoke).
3. Migrar frontend/backend a lectura ANECA preferente.
4. Cuando no queden consumidores legacy:
   - reclasificar `resultado` legacy como deprecado final;
   - retirar `pipeline-result-legacy.v1.schema.json`;
   - retirar `PipelineResultValidator` legacy.

## 8) Pruebas ejecutadas

- `php meritos/scraping/tools/validate_aneca_canonical_adapter.php` -> PASS
- `php meritos/scraping/tools/smoke_jobs_queue.php` -> PASS
- `php meritos/scraping/tools/validate_scraping_technical_contracts.php` -> PASS

## 9) Riesgos pendientes

- La calidad ANECA actual es heuristica (keyword-based), no semantica completa de CV.
- En muestras no etiquetadas, puede quedar en `valido_con_advertencias`.
- Para retirada final del legacy hace falta migracion real de consumidores y extractor semantico nativo ANECA.
