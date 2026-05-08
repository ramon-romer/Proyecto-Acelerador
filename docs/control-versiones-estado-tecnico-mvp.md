# Control de Versiones - estado-tecnico-mvp

Ultima actualizacion: 2026-03-26

## Versiones registradas

| Version | Fecha de corte | Fuente principal | Tipo |
|---|---|---|---|
| v0.1-baseline | 2026-03-24 | `docs/estado-tecnico-mvp.md` | baseline historico |
| v0.2-snapshot | 2026-03-26 | `docs/estado-tecnico-mvp-2026-03-26.md` | snapshot actualizado |

## Comparativa v0.1 -> v0.2

| Area | 2026-03-24 (v0.1) | 2026-03-26 (v0.2) | Evidencia del cambio |
|---|---|---|---|
| MCP extraccion/API/cola | Implementado | Implementado | `mcp-server/extract_pdf.php`, `mcp-server/server.php`, `mcp-server/worker_jobs.php` |
| Backend modular de tutorias | No reflejado en el estado MVP | Implementado y validado | `acelerador_panel/backend/src/*`, `docs/2026-03-25-checkpoint-backend-tutorias.md` |
| Contratos REST de tutorias | No reflejados | 7 endpoints con contrato uniforme `data/meta/error` | `acelerador_panel/backend/docs/02-api-rest-contratos.md`, `acelerador_panel/backend/src/Presentation/Routes/TutoriaRoutes.php` |
| Validacion de carga backend tutorias | No reflejada | Bateria agresiva 1h en PASS | `docs/2026-03-25-resultados-pruebas-backend-tutorias.md`, `acelerador_panel/backend/tests/results/aggressive_battery_2026-03-25_1h.json` |
| Skill reusable de pruebas | No existia | Implementada `ejecutar-tests` | `docs/2026-03-26-implementacion-skill-ejecutar-tests.md`, `.agents/skills/ejecutar-tests/SKILL.md` |
| Integracion frontend -> backend API | Pendiente | Sigue pendiente (frontend principal usa mysqli directo) | `acelerador_panel/fronten/lib/tutor_grupos_service.php` |
| JSON Schema formal transversal | Pendiente | Sigue pendiente | No existe schema versionado global en `docs/` ni en raiz del repo |

## Indicadores de crecimiento (24/03 -> 26/03)

- Se incorpora un backend modular nuevo en `acelerador_panel/backend`.
- Se formalizan 7 endpoints REST para tutorias y asignaciones de profesores.
- Se ejecuta y documenta una bateria agresiva de 1 hora en PASS.
- Se amplia la documentacion tecnica con bloque dedicado para backend tutorias.
- Se incorpora una skill operativa para validaciones adaptativas (`ejecutar-tests`).

## Riesgos abiertos (sin cambio al 26/03)

- Integracion frontend-backend incompleta (conviven API nueva y SQL directo en frontend).
- Falta JSON Schema comun para contratos entre modulos/equipos.
- Coexistencia de rutas MCP y scraping alterno sin orquestador unico.

## Regla de mantenimiento para siguientes cortes

1. No sobrescribir snapshots historicos.
2. Crear nuevo snapshot como `docs/estado-tecnico-mvp-YYYY-MM-DD.md`.
3. Actualizar esta tabla de control con el delta entre la version anterior y la nueva.
4. Registrar evidencia minima por cambio (archivo, commit, test o reporte).

