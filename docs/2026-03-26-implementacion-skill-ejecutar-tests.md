# Implementacion Skill ejecutar-tests

Fecha: 26-03-2026

## 1) Objetivo

Documentar la implementacion de la skill `ejecutar-tests` creada para el repositorio Acelerador, orientada a ejecutar baterias de validacion reutilizables sobre backend modular, MCP e integraciones sensibles.

## 2) Alcance implementado

- Creacion de skill en ruta:
  - `.agents/skills/ejecutar-tests/`
- Creacion de matriz operativa de apoyo:
  - `.agents/skills/ejecutar-tests/references/`
- Definicion de ejecucion por parametros:
  - `nivel`: `standard`, `medio`, `agresivo`
  - `ventana`: `15m`, `30m`, `45m`, `1h`, `6h`
- Regla de entrada incompleta:
  - si falta `nivel`, preguntar solo `nivel`
  - si falta `ventana`, preguntar solo `ventana`

## 3) Entregables creados

1. `.agents/skills/ejecutar-tests/SKILL.md`
2. `.agents/skills/ejecutar-tests/references/matriz-nivel-ventana.md`

No se realizaron cambios en otros modulos del repo por esta implementacion.

## 4) Contenido funcional implementado

### 4.1 SKILL.md

Incluye:
- frontmatter (`name`, `description`)
- mision operativa de la skill
- inputs validos y comportamiento cuando falta informacion
- reglas obligatorias de no inventar comandos/resultados
- priorizacion obligatoria de validacion
- flujo de ejecucion paso a paso
- baseline minimo obligatorio
- formato de salida final obligatorio (12 puntos)
- formato de evidencia (`comprobado`, `inferido`, `no verificable`)
- limites y cautelas
- ejemplos de invocacion exactos

### 4.2 Matriz nivel-ventana

Incluye:
- packs operativos `C0` a `C4`
- mapeo exacto `nivel + ventana` a bateria concreta
- regla de `progress-interval` para `run_aggressive_battery.php`
- regla de recorte por tiempo real sin omitir `C0`

## 5) Evidencia tecnica usada para diseno (repo real)

Comandos y scripts reales del proyecto usados como base de la skill:

- Backend tutorias:
  - `php acelerador_panel/backend/tests/run_usecases_smoke.php`
  - `php acelerador_panel/backend/tests/run_aggressive_battery.php ...`
  - `php acelerador_panel/backend/tools/inspect_schema.php`
  - lint PHP recursivo sobre `acelerador_panel/backend`

- MCP:
  - `php mcp-server/tests/unit_extract_pdf.php`
  - `powershell -NoProfile -ExecutionPolicy Bypass -File mcp-server/test_extract_pdf.ps1 ...`
  - `powershell -NoProfile -ExecutionPolicy Bypass -File mcp-server/test_extract_pdf_ocr_aggressive.ps1 ...`
  - `php mcp-server/worker_jobs.php --once`

- Contratos/rutas:
  - `acelerador_panel/backend/docs/02-api-rest-contratos.md`
  - `acelerador_panel/backend/src/Presentation/Routes/TutoriaRoutes.php`
  - `mcp-server/README.md`
  - `mcp-server/server.php`
  - `docs/estado-tecnico-mvp.md`
  - `docs/estrategia-testing-mvp.md`

## 6) Decisiones de diseno

1. Mantener la skill enfocada en validacion real del estado actual del repo.
2. No crear tooling nuevo: solo reutilizar scripts/comandos existentes.
3. Forzar trazabilidad de evidencia:
   - `comprobado`: ejecutado realmente
   - `inferido`: deducido de codigo/docs
   - `no verificable`: no ejecutable por entorno/tiempo/dependencias
4. Proteger integraciones sensibles (JSON, JSON Schema, MCP, persistencia, integracion entre modulos).
5. Priorizar estabilidad y no ruptura de contratos por encima de validacion profunda.

## 7) Restricciones y cautelas documentadas

- `inspect_schema.php` puede fallar sin conectividad a BD real. Debe reportarse como `no verificable` y no como error falso de implementacion.
- Si OCR/binarios no estan disponibles en entorno, reportar `no verificable` y continuar con el resto.
- Si la ventana de tiempo no alcanza, ejecutar primero lo critico y declarar omisiones.
- Si aparece riesgo de rotura en cascada, detener autoaplicacion y proponer parche controlado.

## 8) Ejemplos de invocacion en este repo

1. `Usa $ejecutar-tests con nivel=standard y ventana=15m`
2. `Usa $ejecutar-tests con nivel=medio y ventana=45m`
3. `Usa $ejecutar-tests con nivel=agresivo y ventana=1h`

## 9) Estado final

- Skill creada y operativa en el repositorio.
- Matriz de ejecucion incluida.
- Sin cambios en `AGENTS.md` (no existe en este repo).
- Sin commits realizados en esta tarea.

