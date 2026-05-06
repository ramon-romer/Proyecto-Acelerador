# auditar-mvp

Skill interna para auditar el estado MVP completo antes de demo o entrega, con salida reproducible y conservadora.

## Objetivo
- Auditar frontend, backend, MCP auxiliar y evaluacion CV/ANECA en una pasada.
- Detectar bloqueantes, riesgos no bloqueantes y mejoras post-MVP.
- Generar un informe Markdown versionable para decision de demo.
- No autocorregir codigo y no hacer operaciones de git (commit/push).

## Modos de uso
- `rapido`
  - `git status --short`
  - existencia de rutas/pantallas clave
  - `php -l` en archivos PHP criticos
  - smokes principales backend (si existen)
- `demo`
  - todo lo de `rapido`
  - flujo tutor/profesor visible a nivel de archivos
  - endpoints backend relevantes
  - smokes de matching/MCP auxiliar
  - smokes de evaluacion CV
  - ausencia de archivos generados pendientes en git
  - documentacion minima de demo/cierre
- `completo`
  - todo lo de `demo`
  - bateria agresiva corta (si no se usa `--sin-tests-largos`)
  - validacion de contrato/schema si existe
  - revision de rutas localhost/hardcodeadas
  - revision de posibles credenciales/tokens en repositorio
  - revision de logs/artefactos no deseados en git status

## Comandos disponibles
- `php .agents/skills/auditar-mvp/scripts/auditar_mvp.php --modo=rapido`
- `php .agents/skills/auditar-mvp/scripts/auditar_mvp.php --modo=demo`
- `php .agents/skills/auditar-mvp/scripts/auditar_mvp.php --modo=completo`
- `php .agents/skills/auditar-mvp/scripts/auditar_mvp.php --modo=completo --sin-tests-largos`
- `php .agents/skills/auditar-mvp/scripts/auditar_mvp.php --modo=demo --output-dir=docs/auditorias-mvp`

## Parametros
- `--modo=rapido|demo|completo`
- `--sin-tests-largos` (omite pruebas largas o intensivas)
- `--output-dir=docs/auditorias-mvp` (directorio de salida para informe)
- `--help`

## Informe generado
Ruta de salida:
- `docs/auditorias-mvp/auditoria-mvp-YYYY-MM-DD-HHMM.md`

Contenido:
- resumen ejecutivo
- modo ejecutado
- fecha/hora
- comandos ejecutados
- resultados
- hallazgos bloqueantes
- hallazgos no bloqueantes
- mejoras post-MVP
- checklist de demo
- veredicto final

## Interpretacion de veredictos
- `LISTO PARA DEMO`
  - Sin bloqueantes ni riesgos relevantes.
- `LISTO CON OBSERVACIONES`
  - Sin bloqueantes, pero con advertencias o mejoras pendientes.
- `NECESITA CORREGIR BLOQUEANTES`
  - Hay bloqueantes concretos corregibles antes de demo.
- `NO APTO PARA DEMO`
  - Riesgo alto o multiple bloqueo critico para una demo fiable.

## Ejemplos recomendados
- Auditoria de validacion previa corta:
  - `php .agents/skills/auditar-mvp/scripts/auditar_mvp.php --modo=rapido`
- Auditoria recomendada antes de mostrar a equipo:
  - `php .agents/skills/auditar-mvp/scripts/auditar_mvp.php --modo=demo --output-dir=docs/auditorias-mvp`
- Auditoria profunda sin pruebas largas:
  - `php .agents/skills/auditar-mvp/scripts/auditar_mvp.php --modo=completo --sin-tests-largos`
