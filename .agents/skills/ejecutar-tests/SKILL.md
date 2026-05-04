---
name: ejecutar-tests
description: Ejecutar baterias de tests y validaciones tecnicas en el repositorio Acelerador con salida estructurada reutilizable. Usar cuando el usuario invoque $ejecutar-tests, pida correr validaciones smoke/regresion/integracion, o cuando otra skill necesite resultados reales de pruebas para documentacion tecnica.
---

# ejecutar-tests

## Mision
Ejecutar validaciones reales del repositorio y devolver resultados claros, trazables y estructurados para consumo humano o automatizado.

## Modos de ejecucion
1. `Interactivo real`:
2. - Se activa con `--interactive`.
3. - Tambien se activa automaticamente si faltan `nivel`, `ventana` o `scope` y hay terminal interactiva.
4. - No ejecuta nada hasta confirmar.
5. `No interactivo`:
6. - Requiere `--nivel`, `--ventana` y `--scope`.
7. - Ideal para pipelines y otras skills.
8. `Importante`:
9. - `Sin --json` solo significa salida humana/legible.
10. - Eso NO equivale a modo interactivo.

## Flujo interactivo obligatorio
Cuando falten datos suficientes, preguntar en este orden:
1. Nivel de dureza: `basico` | `medio` | `agresivo` | `extremo`
2. Ventana: `15m` | `30m` | `45m` | `1h` | `6h` | `personalizado`
3. Alcance: `backend` | `frontend` | `evaluador` | `ANECA` | `MCP` | `contratos` | `toda la app`
4. Salida: `consola legible` | `JSON` | `ambas`
5. Fase intensiva: `si` | `no` | `automatica segun nivel`
6. Confirmacion final: mostrar resumen y pedir `si/no` antes de ejecutar.

## Inputs y flags validos
1. `--nivel=basico|medio|agresivo|extremo`
2. `--ventana=15m|30m|45m|1h|6h|12h|24h|<custom>`
3. `--scope=backend|frontend|evaluador|aneca|mcp|contratos|toda-app`
4. `--output=console|json|both`
5. `--json` (atajo de `--output=json`)
6. `--both` (atajo de `--output=both`)
7. `--intensiva=auto|si|no`
8. `--fase-intensiva=auto|si|no`
9. `--interactive`
10. `--yes` (omite confirmacion final en modo interactivo)
11. `--dry-run`

## Semantica de nivel y ventana
1. La ventana SI afecta al tiempo real de la fase intensiva.
2. `basico` (`standard` interno): solo checks base/rapidos en `auto`.
3. `medio`: fase intensiva moderada con presupuesto temporal real de la ventana.
4. `agresivo`: fase intensiva fuerte con reparto temporal real.
5. `extremo`: fase mas exigente que agresivo con el mismo presupuesto de ventana, aplicando mayor presion/repeticion.

## Politica de reparto intensivo
1. `medio`:
2. - 100% ANECA aggressive si existe.
3. - si ANECA no existe, 100% backend aggressive.
4. - si no hay bateria intensiva disponible, se reporta en observaciones.
5. `agresivo`:
6. - 60% ANECA aggressive.
7. - 30% backend aggressive.
8. - 10% worker MCP en bucle temporal.
9. `extremo`:
10. - 45% ANECA aggressive.
11. - 35% backend aggressive.
12. - 20% worker MCP en bucle temporal.
13. - aplica repeticion por bloques para aumentar presion con el mismo presupuesto total.

## Ejecucion recomendada
Comando:
`php .agents/skills/ejecutar-tests/scripts/ejecutar_tests.php --json --nivel=<nivel> --ventana=<ventana> --scope=<scope>`

Interactivo real:
`php .agents/skills/ejecutar-tests/scripts/ejecutar_tests.php --interactive`

## Reglas obligatorias
1. No inventar resultados ni resumir sin evidencia real.
2. Ejecutar comandos reales del repositorio.
3. Si una validacion opcional no puede ejecutarse (por entorno/dependencias), marcarla como no verificable.
4. Mantener salida estructurada con campos consistentes.
5. Reportar errores reales de ejecucion con mensaje breve.
6. No asumir automaticamente nivel, ventana ni alcance cuando faltan datos.
7. Si el usuario dice `elige tu`, `lo que veas` o `pasada rapida`, se permite elegir defaults razonables y dejarlo explicitado.
8. Si se pide `toda la app` y algun bloque no existe/no esta disponible, reportarlo de forma explicita.

## Salida estructurada esperada
```json
{
  "executed": true,
  "suiteName": "ejecutar-tests:standard-15m",
  "total": 3,
  "passed": 3,
  "failed": 0,
  "scope": "toda-app",
  "outputMode": "console",
  "intensiveMode": "auto",
  "errors": [],
  "summary": "Bateria completada sin fallos.",
  "timestamp": "2026-03-27 10:00:00",
  "observations": "Nivel=standard; Ventana=15m; Presupuesto intensivo=0s; Distribucion=sin fase intensiva; No verificables=0; sin redistribuciones.",
  "noVerificable": 0,
  "executionTimeMs": 1234,
  "checkStats": {},
  "executionPlan": {},
  "checks": []
}
```

## Salida humana (mini-acta tecnica)
En modo consola legible, incluir:
1. Suite, timestamp, duracion total y resumen.
2. Configuracion final (nivel, ventana, alcance, salida, fase intensiva).
3. Disponibilidad detectada por bloque.
4. Metricas (obligatorias, no verificables, totales, categorias).
5. Detalle por check con estado, optional/obligatorio y duracion.
6. Observaciones y errores reales.

## Integracion megatest CV sinteticos
En niveles `medio|agresivo|extremo` y alcance `evaluador|ANECA`, la bateria incluye un check opcional:
- `ANECA synthetic CV megatest (50 por rama)`
- Comando interno: `php evaluador/tests/tools/run_synthetic_cv_megatest.php --nightly --strict`
- Produce reportes en `reports/test-validation/<timestamp>-synthetic-cv-megatest/`.

## Runner PDF/OCR sintetico (manual y opt-in)
- Comando: `php evaluador/tests/run_synthetic_cv_pdf_pipeline.php`
- Dataset: `evaluador/tests/fixtures/cv_sinteticos_pdf/`
- Este check no se ejecuta por defecto en la bateria general porque depende del entorno (`pdftotext`/OCR) y puede devolver `SKIP_ENV`.

## Referencia operativa
Usar la matriz de apoyo:
`references/matriz-nivel-ventana.md`

## Ejemplos de invocacion
1. `Usa $ejecutar-tests en modo interactivo`
2. `Usa $ejecutar-tests con nivel=medio, ventana=45m y alcance=toda la app`
3. `Usa $ejecutar-tests con nivel=agresivo, ventana=1h, alcance=backend y salida=JSON`
4. `Usa $ejecutar-tests con nivel=extremo, ventana=12h y fase intensiva=si`
