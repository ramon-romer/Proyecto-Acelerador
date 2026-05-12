# Estado Tecnico MVP - Snapshot 2026-03-26

Este documento es la fotografia tecnica del proyecto a fecha 2026-03-26.

Baseline historico previo:
- `docs/estado-tecnico-mvp.md` (corte 2026-03-24)

Control de evolucion:
- `docs/control-versiones-estado-tecnico-mvp.md`

## Metodologia de evidencia

- Comprobado en codigo: rutas/archivos existentes en el repositorio.
- Comprobado en docs/resultados: checkpoints y reportes tecnicos ya registrados.
- Inferido: conclusion razonable a partir de codigo y documentacion disponible.

## Estado por bloques (hoy)

### 1) Extraccion MCP (PDF/DB/OCR), API y cola async

- Estado: implementado
- Comprobado en codigo:
  - `mcp-server/extract_pdf.php`
  - `mcp-server/server.php`
  - `mcp-server/worker_jobs.php`
- Comprobado en docs/resultados:
  - `mcp-server/TESTS_RESULTS.md`
  - `mcp-server/resultados/validacion_extract_pdf_report.json`
  - `mcp-server/resultados/validacion_ocr_agresiva_report.json`

### 2) Backend modular Tutorias/Profesores (nuevo respecto al baseline)

- Estado: implementado y validado
- Comprobado en codigo:
  - Arquitectura modular en `acelerador_panel/backend/src/` (Presentation, Application, Domain, Infrastructure)
  - Endpoints registrados en `acelerador_panel/backend/src/Presentation/Routes/TutoriaRoutes.php`
  - Runners de prueba en `acelerador_panel/backend/tests/run_usecases_smoke.php` y `acelerador_panel/backend/tests/run_aggressive_battery.php`
- Comprobado en docs/resultados:
  - `docs/2026-03-25-checkpoint-backend-tutorias.md`
  - `docs/2026-03-25-resultados-pruebas-backend-tutorias.md`
  - `acelerador_panel/backend/tests/results/aggressive_battery_2026-03-25_1h.json`

### 3) Frontend e integracion con backend nuevo

- Estado: parcialmente integrado
- Comprobado en codigo:
  - Frontend principal sigue usando consultas SQL directas (`mysqli`) en:
    - `acelerador_panel/fronten/lib/tutor_grupos_service.php`
    - `acelerador_panel/fronten/lib/db.php`
  - El backend API existe, pero no se observa migracion completa del frontend para consumirlo de forma nativa.
- Inferido:
  - Existe avance funcional de frontend, pero la convergencia completa a API aun no esta cerrada.

### 4) Contratos JSON

- Estado: parcialmente integrado
- Comprobado en codigo/docs:
  - Contrato homogeno `data/meta/error` para backend tutorias:
    - `acelerador_panel/backend/docs/02-api-rest-contratos.md`
  - Contrato fijo de extraccion en MCP (campos de salida en extractor/server):
    - `mcp-server/extract_pdf.php`
- Inferido:
  - Hay contratos operativos por modulo, pero no un contrato transversal unico versionado para todo el proyecto.

### 5) JSON Schema formal compartido

- Estado: pendiente
- Comprobado:
  - No se identifica JSON Schema global versionado en `docs/` ni en la raiz.
- Riesgo:
  - Puede bloquear integracion final entre equipos si no se formaliza antes del cierre MVP.

### 6) Testing y calidad

- Estado: implementado en MCP + reforzado en backend tutorias
- Comprobado en codigo/docs:
  - Estrategia general en `docs/estrategia-testing-mvp.md`
  - Bateria agresiva backend 1h en PASS (docs + reporte JSON)
  - Skill reusable de validacion:
    - `.agents/skills/ejecutar-tests/SKILL.md`
    - `docs/2026-03-26-implementacion-skill-ejecutar-tests.md`

## Semaforo MVP al 2026-03-26

| Bloque | Estado actual | Cambio vs baseline 2026-03-24 |
|---|---|---|
| Extraccion MCP + API + cola async | implementado | sin cambio |
| Backend modular tutorias/profesores | implementado y validado | mejora alta |
| Integracion frontend-backend por API | parcialmente integrado | mejora baja |
| Persistencia homogenea entre modulos | parcialmente integrado | sin cambio |
| Contratos JSON transversales unificados | parcialmente integrado | mejora media |
| JSON Schema formal compartido | pendiente | sin cambio |
| Motor ORCID/DOI/rama multi-fuente | post-MVP | sin cambio |

## Resumen de avance

- El baseline del 24/03 sigue siendo valido como foto historica del bloque MCP.
- El crecimiento principal del 25-26/03 esta en el backend modular de tutorias y su validacion de carga.
- El mayor gap tecnico para integracion final sigue siendo la estandarizacion transversal de contratos (JSON Schema) y la migracion completa frontend -> API.

