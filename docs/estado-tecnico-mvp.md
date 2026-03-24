# Estado Técnico MVP - Proyecto Acelerador

## Mapa del sistema detectado (estado real)

### Capa de extracción y parsing
- **Hecho observado en código**: `mcp-server/extract_pdf.php` implementa extracción multi-fuente:
  - PDF nativo (`smalot/pdfparser`)
  - OCR (`pdftoppm` + `tesseract`)
  - DB vía PDO (`dsn`, `query`, `params`)
- **Hecho observado en código**: contrato de salida fijo con claves:
  - `tipo_documento`, `numero`, `fecha`, `total_bi`, `iva`, `total_a_pagar`, `texto_preview`
- **Estado**: implementado

### Capa API
- **Hecho observado en código**: `mcp-server/server.php` expone:
  - `POST /extract-pdf`
  - `POST /extract-data`
  - `GET /jobs/{job_id}`
- **Hecho observado en código**: control de payload (`MAX_PDF_BYTES`) y respuesta `413`.
- **Estado**: implementado

### Capa async y cola
- **Hecho observado en código**: jobs en `mcp-server/resultados/jobs`.
- **Hecho observado en código**: worker en `mcp-server/worker_jobs.php` (`--once`, `--loop`).
- **Estado**: implementado

### Capa scraping alterna
- **Hecho observado en código**: `meritos/scraping/src/*` contiene pipeline OCR independiente (`Pipeline`, `PdfToImage`, `OcrProcessor`, `AnecaExtractor`).
- **Hecho observado en código**: `meritos/scraping/public/subir.php` ejecuta esa ruta de procesamiento.
- **Estado**: parcialmente integrado

### Persistencia detectada
- **Hecho observado en código**: SQLite en flujo MCP (configs/resultados de validación).
- **Hecho observado en código**: MySQL/MariaDB en `meritos/config.php` y utilidades `funcionalidades/*.php` (mysqli).
- **Estado**: parcialmente integrado (coexisten dos enfoques)

## Relación backend, frontend y MCP
- **Hecho observado en código**: MCP/backend de extracción es operable sin depender de frontend.
- **Hecho observado en código**: no hay frontend completo implementado en este repo; integración depende de otro equipo.
- **Decisión del día**: documentar y validar backend autónomamente para no bloquear MVP por dependencia cruzada.
- **Estado de integración frontend-backend**: pendiente

## Contratos y puntos críticos

### Contratos JSON
- **Hecho observado en código**: existe contrato JSON fijo de extracción en el backend MCP.
- **Hecho observado en código**: existen diferencias entre scripts heredados y endpoints nuevos en la forma de respuesta esperada.
- **Riesgo**: importante pero no bloqueante (puede provocar fricción de integración si no se normaliza consumo).

### JSON Schema
- **Hecho observado en código**: no existe JSON Schema formal versionado en repo para contratos de integración.
- **Estado**: pendiente
- **Riesgo**: bloqueante del MVP si no se cierra antes de integración final entre equipos.

### Evaluadores / motor de matching
- **Hecho observado en código**: hay reglas regex de extracción documental (`DocumentoExtractor`) y ficheros de `meritos` con lógica aún no implementada.
- **Pendiente/hipótesis**: no existe todavía motor integral ORCID/DOI/rama ni política formal de scoring.
- **Estado**: definido para fase post-MVP (según línea de trabajo actual)

## Estado por bloque MVP
| Bloque | Clasificación |
|---|---|
| Extracción PDF/DB + OCR robusto | implementado |
| API MCP y cola async | implementado |
| Persistencia homogénea entre módulos | parcialmente integrado |
| Integración de scraping alterno con API principal | parcialmente integrado |
| Integración frontend-backend completa | pendiente |
| Contratos formales + JSON Schema | pendiente |
| Motor ORCID/DOI/rama multi-fuente | definido para fase post-MVP |

## Incoherencias arquitectura-código detectadas
- **Hecho observado en código**: coexistencia de rutas técnicas paralelas para extracción (`mcp-server` y `meritos/scraping`) sin orquestador único.
- **Hecho observado en código**: parte de `meritos/*.php` está en estado stub y no refleja todavía la arquitectura funcional objetivo.
- **Hecho observado en código**: doble stack de acceso a datos (PDO y mysqli) sin capa de integración común.
- **Hecho observado en código**: no hay JSON Schema formal pese a existir contrato JSON implícito.

## Qué está listo para MVP y qué falta para validación total

### Listo para MVP (base técnica)
- Backend de extracción operable por CLI y API.
- Manejo de casos de carga alta en PDF/DB.
- Suite de tests unitarios y baterías funcionales del módulo MCP.

### Falta para validación total
- Contratos oficiales + JSON Schema compartidos con todos los consumidores.
- Validación e2e transversal (frontend + backend + módulos colindantes).
- Cierre de política de integración entre rutas MCP y scraping alterno.
- Definición final de cambios bajo revisión manual en pipeline de entrega.
