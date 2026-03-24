# Resumen Ejecutivo - Trabajo del 24-03-2026

## Objetivo del trabajo de hoy
- Alinear técnicamente el Proyecto Acelerador para la entrega de un MVP funcional integrado.
- Reducir riesgo de fallo visible en demo mediante foco en estabilidad, integración y estrategia de testing.

## Contexto del proyecto
- **Hecho observado en código**: existe un backend PHP funcional para extracción multi-fuente en `mcp-server` con soporte PDF/DB, API HTTP y procesamiento asíncrono por cola.
- **Hecho observado en código**: existe un bloque paralelo de scraping en `meritos/scraping` y utilidades de base de datos/subida en `funcionalidades`.
- **Hecho observado en código**: la parte de frontend completa no está implementada en este repositorio; hay dependencia de integración con otro equipo.
- **Decisión del día**: priorizar integración de sistema completo para MVP por encima de ampliaciones funcionales.

## Decisiones tomadas hoy
- **Decisión del día**: mantener arquitectura modular para aislar fallos y facilitar integración progresiva.
- **Decisión del día**: backend debe validarse de forma autónoma aunque MCP no esté conectado a todo el flujo final.
- **Decisión del día**: distinguir claramente entre:
  - bloqueantes del MVP
  - importantes no bloqueantes
  - mejoras post-MVP
- **Decisión del día**: cambios sensibles deben pasar revisión manual; automatizar solo cambios triviales y de bajo riesgo.
- **Decisión del día**: definir una mega batería de tests (hasta ~8h configurable) y una suite crítica de demo.

## Estado actual por bloque
| Bloque | Estado | Evidencia breve |
|---|---|---|
| Backend MCP (`mcp-server`) | implementado | `extract_pdf.php`, `server.php`, `worker_jobs.php`, tests y reportes en `mcp-server/resultados/` |
| API/Endpoints internos | implementado | `POST /extract-pdf`, `POST /extract-data`, `GET /jobs/{job_id}` en `mcp-server/server.php` |
| Persistencia flujo MCP (SQLite de pruebas/config) | implementado | `fuente_db_config.json`, `fuente_test.db`, soporte DB vía PDO |
| Scraping alterno (`meritos/scraping`) | parcialmente integrado | pipeline OCR independiente (`Pipeline.php`) no unificado aún con API MCP |
| Módulos `meritos/*.php` (evaluación/DOI/listado/login) | pendiente | varios archivos están en estado stub/comentario |
| Utilidades `funcionalidades/*.php` | implementado | funciones mysqli para insertar/seleccionar/eliminar y subida PDF |
| Frontend integrado con backend actual | pendiente | integración esperada con equipo externo; no hay cliente frontend completo en repo |
| Contratos JSON formales con JSON Schema | pendiente | contrato de salida existe en código; no hay JSON Schema formal versionado |
| Orquestación ORCID/DOI/rama entre ANECA/Dialnet/PDFs | definido para fase post-MVP | documentado como siguiente fase en registro técnico |

## Riesgos y prioridades

### Bloqueante del MVP
- Falta de validación end-to-end completa frontend-backend-módulos externos.
- Ausencia de JSON Schema formal para contratos críticos de integración.

### Importante pero no bloqueante
- Coexistencia de dos rutas técnicas de extracción/scraping (`mcp-server` y `meritos/scraping`) sin orquestación única.
- Inconsistencias potenciales entre respuestas de scripts heredados y endpoints nuevos.

### Mejora post-MVP
- Consolidar evaluadores funcionales ORCID/DOI/rama.
- Refactor de módulos stub de `meritos` hacia capa de aplicación completa.
- Unificación de estrategia de persistencia (PDO/mysqli) con estándares comunes.

## Siguientes pasos
- Definir contratos de entrada/salida y publicar JSON Schema oficial.
- Acordar contrato de integración frontend-backend para flujos críticos de demo.
- Implementar suite crítica de demo y ejecución repetida pre-entrega.
- Ejecutar mega batería configurable (hasta ~8h) en ventana controlada y con criterios de salida.
- Preparar fase post-MVP para motor de matching ORCID/DOI/rama multi-fuente.

## Enfoque MVP
- El objetivo actual es entregar un MVP funcional del sistema completo con el menor riesgo visible posible.
- Esta fase no cierra la evolución del producto.
- Tras el MVP se prevé crecimiento por módulos, nuevas funciones y refactors técnicos.
