# Registro Tecnico - 2026-03-23

Este documento deja trazabilidad de lo implementado hoy y de lo pendiente para la siguiente fase (reglas, contratos y JSON Schema).

## 1) Implementado hoy

### 1.1 Extraccion PDF robusta
- Deteccion de binarios OCR con fallback local (`mcp-server/.tools`) y rutas globales.
- Resolucion automatica de `TESSDATA_PREFIX`.
- OCR por lotes de paginas para evitar cargas extremas de memoria/disco.
- Limites configurables:
  - `MAX_OCR_PAGES` (default 400)
  - `OCR_BATCH_SIZE` (default 10)
- Diagnostico previo de estrategia en PDF:
  - `MAX_SYNC_PDF_BYTES` (default 15MB)
  - `MAX_SYNC_PAGES` (default 80)

### 1.2 API/Servidor
- `POST /extract-pdf`:
  - Si el diagnostico recomienda `sync`, procesa en el momento.
  - Si recomienda `async`, encola un job y devuelve `job_id`.
- `POST /extract-data`:
  - Soporta `fuente.tipo=pdf` y `fuente.tipo=db` (JSON unificado).
- `GET /jobs/{job_id}`:
  - Consulta estado y resultado/error de jobs async.
- Limite de payload HTTP con `MAX_PDF_BYTES` (default 50MB).
- Respuesta `413 Payload Too Large` cuando aplica.
- Tolerancia a BOM y JSON con `Content-Type` imperfecto (si el body parece JSON).

### 1.3 Worker async
- Nuevo script: `mcp-server/worker_jobs.php`
  - `--once`: procesa una tanda.
  - `--loop`: modo continuo.

### 1.4 Fuente DB robusta
- Lectura iterativa (`fetch`) en vez de `fetchAll`.
- Limites y protecciones:
  - `max_rows` (maximo 10000)
  - `max_text_chars` / `MAX_DB_TEXT_CHARS` (default 2000000)
  - `query_timeout_seconds` / `MAX_DB_QUERY_SECONDS` (default 30s)
- Errores controlados cuando se supera limite de texto o timeout.

### 1.5 Configuracion multi-fuente (ejemplos)
- `mcp-server/resultados/fuente_db_config.json` (default seguro actual).
- `mcp-server/resultados/fuente_db_config_aneca.example.json`
- `mcp-server/resultados/fuente_db_config_dialnet.example.json`

## 2) Validaciones ejecutadas
- Lint OK en `extract_pdf.php`, `server.php`, `worker_jobs.php`.
- Tests unitarios OK (`passed=13 failed=0`).
- Flujo async validado con PDF grande real:
  1. Encola (`queued=true` + `job_id`)
  2. Worker procesa
  3. `GET /jobs/{id}` devuelve `status=done`
- Endpoint `extract-data` validado para `db` y `pdf`.

## 3) Pendiente para siguiente fase (cuando haya reglas/contratos/schema)

Objetivo funcional:
- Entrada de criterios (`ORCID`, `DOI`, `rama`).
- Orquestacion por reglas de negocio:
  - Consultar ANECA.
  - Consultar Dialnet segun rama.
  - Consultar uno o varios PDFs origen (X/Y) cuando aplique.
- Matching consolidado (prioridad sugerida):
  1. DOI exacto
  2. ORCID exacto
  3. Coincidencias auxiliares (titulo/autoria/metadatos)
- Salida JSON unificada con evidencia por fuente y score/razon de match.

Dependencias pendientes:
- Definir contratos de entrada/salida.
- Definir JSON Schema oficial.
- Definir reglas por rama/fuente.

## 4) Nota operativa
- Este registro es la referencia tecnica temporal hasta que se cierre la especificacion formal (contratos + JSON Schema).
