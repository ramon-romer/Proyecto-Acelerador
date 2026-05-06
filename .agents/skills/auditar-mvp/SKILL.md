---
name: auditar-mvp
description: Auditar el estado MVP completo antes de demo o entrega con un informe reproducible en Markdown, clasificando bloqueantes, riesgos no bloqueantes y mejoras post-MVP sin autocorregir codigo ni hacer commit/push. Usar cuando se necesite una revision transversal de frontend/backend/MCP/evaluacion, validaciones smoke, higiene git y veredicto final de readiness para demo.
---

# auditar-mvp

## Mision
Ejecutar una auditoria conservadora y repetible del MVP completo, dejando evidencia trazable en un informe Markdown.

## Flujo de uso
1. Ejecutar el script principal con `--modo=rapido|demo|completo`.
2. Revisar el informe generado en `docs/auditorias-mvp/`.
3. Tomar acciones manuales sobre bloqueantes o riesgos detectados.

## Script principal
`php .agents/skills/auditar-mvp/scripts/auditar_mvp.php`

## Reglas operativas
1. No corregir codigo automaticamente.
2. No hacer `commit`, `push` ni cambios de git.
3. Si una comprobacion no puede ejecutarse, registrar advertencia y marcar como no verificable.
4. Senalar siempre si hay cambios sin commitear.
5. Generar un informe reproducible con veredicto final.

## Ejemplos
1. `php .agents/skills/auditar-mvp/scripts/auditar_mvp.php --modo=rapido`
2. `php .agents/skills/auditar-mvp/scripts/auditar_mvp.php --modo=demo --output-dir=docs/auditorias-mvp`
3. `php .agents/skills/auditar-mvp/scripts/auditar_mvp.php --modo=completo --sin-tests-largos`
