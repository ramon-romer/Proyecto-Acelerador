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
