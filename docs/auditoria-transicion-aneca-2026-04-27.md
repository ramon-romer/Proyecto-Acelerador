# Auditoria de Consumidores Legacy vs ANECA (2026-04-27)

## 1) Objetivo y alcance

Objetivo de esta auditoria:

- localizar consumidores reales del resultado legacy;
- verificar consumo real de salida ANECA canonica;
- dejar propuesta de transicion para que ANECA sea salida preferente sin romper runtime.

Alcance revisado:

- `meritos/scraping` (public, src, tools);
- `mcp-server` (scripts y tests que consumen contrato legacy);
- `docs/schemas` y documentacion operativa relacionada.

Terminos auditados en repo:

- `resultado`
- `prefer_aneca`
- `include_aneca`
- `tipo_documento`
- `numero`
- `fecha`
- `total_bi`
- `iva`
- `total_a_pagar`
- `texto_preview`
- `aneca_canonical_path`
- `aneca_canonical_ready`
- `aneca_canonical_validation_status`

## 2) Hallazgos clave

- El runtime sigue publicando `resultado` legacy por defecto en endpoints de salida.
- ANECA ya esta integrado y trazado, pero su consumo preferente sigue condicionado a flags (`prefer_aneca=1`, `include_aneca=1`).
- Los mayores acoplamientos legacy estan en validacion tecnica, cache/job model y smokes.
- No se detecta frontend del repo que consuma/parsee el payload de resultado; solo existe formulario de subida.

## 3) Inventario de consumidores

| ID | Capa | Archivo (lineas) | Que consume exactamente | Dependencia | Impacto de migracion | Dificultad | Clase | Deuda tecnica legacy |
|---|---|---|---|---|---|---|---|---|
| 1 | Backend API | `meritos/scraping/public/api_cv_procesar.php` (104-139, 225-237) | `resultado` desde `job['resultado_json']`; opcional `resultado_aneca_canonico`; `resultado_preferente` con `prefer_aneca`; metadata `aneca_canonical_*` | Ambos (default legacy) | Cambiar preferencia de salida sin romper `resultado` | Media | B | Si (default en `resultado`) |
| 2 | Backend API | `meritos/scraping/public/subir.php` (73-113, 171-187) | Igual que API: `resultado` legacy por defecto, ANECA opcional/preferente por flags | Ambos (default legacy) | Igual al endpoint API; impacto en clientes sync | Media | B | Si (default en `resultado`) |
| 3 | Servicio intermedio | `meritos/scraping/src/CvProcessingJobService.php` (50-53, 216, 239-242, 281) | Cache-hit y finalizacion de job usando `resultado_json`; arrastra `aneca_canonical_*` como metadata | Ambos (core legacy + metadata ANECA) | Refactor interno de modelo de job/cache | Alta | C | Si |
| 4 | Servicio intermedio | `meritos/scraping/src/ProcessingJobWorker.php` (81-93, 116-123, 555-607) | Flujo de cache y `markCompleted` basado en `resultado_json`; trazabilidad `aneca_canonical_*` | Ambos (core legacy + metadata ANECA) | Refactor de worker y reglas de cache/finalizacion | Alta | C | Si |
| 5 | Servicio intermedio | `meritos/scraping/src/ProcessingCache.php` (108-109, 229-235, 300-302) | Persistencia principal de `resultado_json_path`; ANECA como metadatos/carga secundaria | Ambos (core legacy + metadata ANECA) | Cambiar formato principal de cache implica migracion de artefactos | Alta | C | Si |
| 6 | Servicio intermedio | `meritos/scraping/src/ProcessingJobQueue.php` (38, 45-47, 233) | Modelo de job con `resultado_json` y metadatos `aneca_canonical_*` | Ambos (core legacy + metadata ANECA) | Cambios de contrato interno del job | Alta | C | Si |
| 7 | Validador tecnico | `meritos/scraping/src/PipelineResultValidator.php` (56, 141-155) | Requiere claves legacy (`tipo_documento`, `numero`, `fecha`, `total_bi`, `iva`, `total_a_pagar`, `texto_preview`) | Legacy | Punto mas acoplado a naming legacy | Alta | C | **Si (acoplamiento fuerte por nombres legacy)** |
| 8 | Adaptador intermedio | `meritos/scraping/src/AnecaCanonicalAdapter.php` (18-35, 202-211) | Adapta desde payload legacy y reutiliza campos legacy como fallback de evidencia | Ambos | Se mantiene util como puente; ajuste menor cuando ANECA sea primario | Media | B | Si |
| 9 | Orquestador pipeline | `meritos/scraping/src/Pipeline.php` (263-275, 307-331) | Genera legacy (`AnecaExtractor` + `PipelineResultValidator`) y en paralelo ANECA canonico | Ambos | No bloquea cambio de preferencia API, pero mantiene productor legacy | Media | B | Si |
| 10 | Validador canonico | `meritos/scraping/src/AnecaCanonicalResultValidator.php` (47-55, 302-303) | Evalua payload canonico y emite `aneca_canonical_ready/status` | ANECA | Ya alineado a destino | Baja | A | No |
| 11 | Script smoke | `meritos/scraping/tools/smoke_jobs_queue.php` (114-133, 246-254) | Verifica claves legacy obligatorias en `resultado_json`; tambien verifica `aneca_canonical_*` | Ambos con sesgo legacy | Debe migrar asserts a `resultado_preferente` | Media | C | **Si (acoplamiento fuerte por nombres legacy)** |
| 12 | Script contratos | `meritos/scraping/tools/validate_scraping_technical_contracts.php` (294-307, 318-331) | Construye muestras con `resultado` legacy y `resultado_preferente`/`resultado_aneca_canonico` | Ambos | Ya preparado; requiere ajustes menores al cambiar default | Baja | B | Parcial |
| 13 | Script adaptador ANECA | `meritos/scraping/tools/validate_aneca_canonical_adapter.php` (155-170) | Consumo preferente ANECA con fallback legacy (`consumoFormato`) | Ambos, prioriza ANECA | Practicamente listo | Baja | A | No |
| 14 | Script backend local | `mcp-server/extract_pdf.php` (13-19, 715-720) | Contrato fijo legacy (`tipo_documento`, `numero`, `fecha`, `total_bi`, `iva`, `total_a_pagar`, `texto_preview`) | Legacy | Requiere adaptador o cambio de contrato de salida | Alta | C | **Si (acoplamiento fuerte por nombres legacy)** |
| 15 | Tests unitarios | `mcp-server/tests/unit_extract_pdf.php` (63, 95-101, 191-196) | Asserts directos sobre nombres/campos legacy | Legacy | Rompera al cambiar contrato sin adaptador | Media | C | **Si** |
| 16 | Tests PowerShell | `mcp-server/test_extract_pdf.ps1` (10-17), `mcp-server/test_extract_pdf_ocr_aggressive.ps1` (10-17) | Lista de required keys legacy y checks de `texto_preview` | Legacy | Igual que unit tests | Media | C | **Si** |
| 17 | Contrato API docs | `docs/schemas/api-response.v1.schema.json` (118-126, 413-414, 434-444) | Define `resultado` como `pipeline_result` legacy y campos opcionales ANECA/preferente | Ambos (schema legacy-first) | Ajuste no rompedor posible (promover preferente) | Media | B | Si |
| 18 | Contrato legacy | `docs/schemas/pipeline-result-legacy.v1.schema.json` (8-14) | Define explicitamente payload legacy | Legacy | Debe quedar deprecado hasta retirada final | Media | C | **Si** |
| 19 | Contrato job | `docs/schemas/processing-job.v1.schema.json` (15, 22-24) | Modela `resultado_json` + `aneca_canonical_*` | Ambos con core legacy | Cambio de modelo interno cuando ANECA sea primario | Media | C | Si |
| 20 | Contrato cache | `docs/schemas/processing-cache.v1.schema.json` (15, 19-21) | Modela `resultado_json_path` + `aneca_canonical_*` | Ambos con core legacy | Igual que `processing-job` | Media | C | Si |
| 21 | Doc operativa | `docs/cola-procesamiento-cv.md` (19, 71) | Documenta `resultado_json` como campo central de job/worker | Legacy | Actualizacion documental menor | Baja | B | Si |
| 22 | Doc arquitectura | `docs/contratos-json-acelerador-v1.md` (55-61, 88-90) | Inventario actual de dependencia legacy + trazabilidad ANECA | Ambos | Actualizacion incremental | Baja | B | No |

### Nota frontend

- `meritos/scraping/public/index.php` (11-13) solo sube PDF y no consume/parsea payload de resultado.
- No se detectaron consumidores frontend reales del contrato `resultado`/`resultado_preferente` dentro de este repo.

## 4) Clasificacion A/B/C (resumen)

- **A (ya puede usar ANECA casi sin cambios):** IDs 10, 13.
- **B (requiere ajuste pequeno/adaptador):** IDs 1, 2, 8, 9, 12, 17, 21, 22.
- **C (dependencia fuerte legacy):** IDs 3, 4, 5, 6, 7, 11, 14, 15, 16, 18, 19, 20.

## 5) Bloqueos actuales para ANECA por defecto

1. `resultado_json` sigue siendo el artefacto central en job/worker/cache.
2. El contrato de salida API publica `resultado` legacy por defecto; ANECA preferente es opt-in por flag.
3. Smokes y tests tecnicos validan explicitamente claves legacy.
4. Sub-sistema `mcp-server` mantiene contrato fijo legacy y su bateria de pruebas acoplada.
5. `PipelineResultValidator` bloquea cambio directo por acoplamiento fuerte a nombres legacy.

## 6) Deuda tecnica legacy explicita (nombres acoplados)

Sitios con acoplamiento fuerte por claves legacy (`tipo_documento`, `total_bi`, `iva`, `total_a_pagar`):

- `meritos/scraping/src/PipelineResultValidator.php` (56, 141-155)
- `meritos/scraping/src/AnecaCanonicalAdapter.php` (202-211)
- `meritos/scraping/tools/smoke_jobs_queue.php` (114-121, 246-247)
- `mcp-server/extract_pdf.php` (13-19, 715-720)
- `mcp-server/tests/unit_extract_pdf.php` (63, 95-101, 191)
- `mcp-server/test_extract_pdf.ps1` (10-17)
- `mcp-server/test_extract_pdf_ocr_aggressive.ps1` (10-17)
- `docs/schemas/pipeline-result-legacy.v1.schema.json` (8-14)
- `docs/schemas/api-response.v1.schema.json` (`$defs.pipeline_result`, 118-126)

## 7) Propuesta de transicion minima compatible (sin romper runtime)

### 7.1 Cambio por configuracion (recomendado)

Aplicar preferencia ANECA por config, no por ruptura de contrato:

- agregar `ANECA_PREFER_DEFAULT=1` (por defecto apagado en entornos legacy hasta activacion);
- mantener override por request:
  - `prefer_aneca=1` fuerza ANECA preferente,
  - `prefer_aneca=0` fuerza legacy (escape hatch temporal),
  - ausencia de flag => valor de `ANECA_PREFER_DEFAULT`.

### 7.2 Mantener `prefer_aneca` temporalmente

Si, conviene mantenerlo durante la migracion para rollback rapido y para consumidores aun no migrados (clase C).

### 7.3 Endpoints a priorizar ANECA primero

Priorizar en:

- `GET /api/cv/procesar/{job_id}/resultado`
- `POST /subir.php` (respuesta sync)

Comportamiento recomendado:

- mantener `resultado` (legacy) sin cambios para compatibilidad;
- devolver siempre `resultado_preferente_formato` y `resultado_preferente`;
- cuando `aneca_canonical_ready=true`, `resultado_preferente` debe apuntar a ANECA por defecto si config lo habilita.

### 7.4 Ampliar respuesta API o no

- **No hace falta ampliar de forma rompiente.**
- Los campos `resultado_aneca_canonico`, `resultado_preferente_formato` y `resultado_preferente` ya existen en contrato tecnico.
- Solo hace falta pasar de uso opt-in a uso por defecto controlado por configuracion.

## 8) Criterio recomendado para cambiar a ANECA por defecto

Recomendacion de corte (go/no-go):

1. Cero consumidores runtime clase C en API/backend (o todos con adaptador activo).
2. Smokes y validadores de contratos migrados a `resultado_preferente` como fuente primaria.
3. Tasa de `aneca_canonical_ready=true` >= 95% sostenida (minimo 2 semanas de ejecucion real).
4. Fallback legacy operativo (`prefer_aneca=0`) durante ventana de estabilizacion.
5. Sin regresiones funcionales en contratos tecnicos (`validate_scraping_technical_contracts.php`, `smoke_jobs_queue.php`, `validate_aneca_canonical_adapter.php`).

## 9) Siguiente paso tecnico recomendado tras esta auditoria

Implementar un PR corto y no rompedor con 3 cambios:

1. Encender preferencia ANECA por config (`ANECA_PREFER_DEFAULT`) en `api_cv_procesar.php` y `subir.php`.
2. Emitir siempre `resultado_preferente_formato` + `resultado_preferente` en respuestas `ready`.
3. Actualizar smokes clase B para validar primero `resultado_preferente` y mantener checks legacy solo como compatibilidad temporal.

