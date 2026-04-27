# Cola de Procesamiento de CV (JSON)

Implementacion inicial sin base de datos para desacoplar subida HTTP y procesamiento pesado.

## Estructura actual

- Jobs en `meritos/scraping/output/jobs/<job_id>.json`
- Logs por job en `meritos/scraping/output/jobs/logs/<job_id>.log`
- Worker CLI en `meritos/scraping/tools/process_jobs_worker.php`

Campos principales del job:

- `id`
- `archivo_pdf`
- `hash_pdf`
- `estado`
- `progreso_porcentaje`
- `fase_actual`
- `resultado_json`
- `aneca_canonical_path`
- `aneca_canonical_ready`
- `aneca_canonical_validation_status`
- `resultado_principal_formato`
- `resultado_principal_path`
- `error_mensaje`
- `fecha_creacion`
- `fecha_inicio`
- `fecha_fin`
- `tiempo_total_ms`
- `trace_path`
- `log_path`

Estados definidos:

- `pendiente`
- `procesando_pdf`
- `procesando_ocr`
- `extrayendo_meritos`
- `calculando_puntuacion`
- `validando_resultado`
- `completado`
- `error_parcial`
- `error`

## Endpoints

- `POST /api/cv/procesar` (crea job)
- `GET /api/cv/procesar/{job_id}/estado`
- `GET /api/cv/procesar/{job_id}/resultado`

### Salida preferente (ready)

En respuestas `ready` de `subir.php` y `api_cv_procesar.php`:

- `resultado`: se mantiene como payload legacy por compatibilidad temporal.
- `resultado_preferente`: payload estable para consumidores nuevos.
- `resultado_preferente_formato`: `aneca` o `legacy`.

Preferencia configurable:

- env `PREFER_ANECA_DEFAULT=true|false` (por defecto `false`).
- override por request:
  - `prefer_aneca=1` fuerza ANECA preferente;
  - `prefer_aneca=0` fuerza legacy;
  - sin flag, aplica `PREFER_ANECA_DEFAULT`.

Fallback seguro:

- si ANECA no esta disponible (`aneca_canonical_ready=false` o artefacto no accesible), `resultado_preferente` cae a legacy.

### Validacion en runtime (legacy vs ANECA)

- `LegacyPipelineResultValidator` valida el payload legacy/transitorio (`resultado_json`).
- `PipelineResultValidator` queda como alias transitorio para compatibilidad con imports existentes.
- `AnecaCanonicalResultValidator` valida el artefacto canonico ANECA (`aneca_canonical_*`).

### Artefacto interno principal (job/cache)

- `resultado_json` y `resultado_json_path` se mantienen por compatibilidad temporal.
- `resultado_principal_formato` y `resultado_principal_path` se hidratan en runtime para expresar el artefacto tecnico principal.
- Regla actual: el principal refleja la decision operativa efectiva (`aneca_operativo` o `legacy_fallback`), no solo la disponibilidad tecnica de archivos.

### Criterio operativo del worker

- `ProcessingJobWorker` ya no decide solo con validacion legacy.
- Si el artefacto principal es `aneca`, `aneca_canonical_ready=true` y `aneca_canonical_validation_status` es utilizable, el worker usa criterio `aneca_operativo` para cacheabilidad/finalizacion.
- Si ANECA no esta lista o no es utilizable, aplica `legacy_fallback` con validacion legacy.
- `CvProcessingJobService` aplica el mismo criterio en `cache_hit`/finalizacion temprana para mantener coherencia service-worker.
- El criterio comun se centraliza en `OperationalArtifactDecisionResolver`.
- `ProcessingCache::validateCurrentMeta()` tambien aplica este criterio operativo para decidir reutilizacion de meta, evitando bloqueo injusto por `validation_status` legacy cuando ANECA es utilizable.
- `ProcessingJobQueue` hidrata `resultado_principal_*` con ese mismo criterio operativo para evitar desalineaciones semanticas entre queue/cache/worker/service.
- En logs de job quedan trazas explicitas:
  - `decision_operativa_runtime ...`
  - `decision_operativa_cache ...`
  - `decision_operativa_service_cache_hit ...`
  - `criterio_operativo=<aneca_operativo|legacy_fallback>`

## Ejecucion del worker

```bash
php meritos/scraping/tools/process_jobs_worker.php --once
php meritos/scraping/tools/process_jobs_worker.php --loop --sleep=5
php meritos/scraping/tools/process_jobs_worker.php --job-id=<job_id>
```

## Smoke test

```bash
php meritos/scraping/tools/smoke_jobs_queue.php
```

## Migracion futura a SQL

Mapeo sugerido de `output/jobs/*.json` a tabla `cv_processing_jobs`:

- `id` (PK)
- `archivo_pdf`
- `pdf_path`
- `hash_pdf`
- `estado`
- `progreso_porcentaje`
- `fase_actual`
- `resultado_json` (JSON/TEXT)
- `error_mensaje`
- `error_parcial` (BOOL)
- `fecha_creacion`
- `fecha_inicio`
- `fecha_fin`
- `tiempo_total_ms`
- `trace_path`
- `log_path`
- `pipeline_log_path`
- `fecha_actualizacion`

Para migrar, reemplazar `ProcessingJobQueue` por repositorio SQL manteniendo la misma interfaz publica.
