---
name: ejecutar-tests
description: Ejecutar baterias de tests y validaciones tecnicas en el repositorio Acelerador con salida estructurada reutilizable. Usar cuando el usuario invoque $ejecutar-tests, pida correr validaciones smoke/regresion/integracion, o cuando otra skill necesite resultados reales de pruebas para documentacion tecnica.
---

# ejecutar-tests

## Mision
Ejecutar validaciones reales del repositorio y devolver resultados claros, trazables y estructurados para consumo humano o automatizado.

## Inputs validos
1. `nivel`: `standard` | `medio` | `agresivo` | `extremo`
2. `ventana`: `15m` | `30m` | `45m` | `1h` | `6h` | `12h` | `24h`

Si falta un input:
1. Pedir solo el dato faltante.
2. No pedir informacion adicional.
3. Preguntas permitidas:
4. `Indica solo el nivel: standard, medio, agresivo o extremo.`
5. `Indica solo la ventana: 15m, 30m, 45m, 1h, 6h, 12h o 24h.`

## Semantica de nivel y ventana
1. La ventana SI afecta al tiempo real de la fase intensiva.
2. `standard`: solo checks base/rapidos. Sin baterias largas.
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
`php .agents/skills/ejecutar-tests/scripts/ejecutar_tests.php --json --nivel=<nivel> --ventana=<ventana>`

Por defecto:
1. `nivel=standard`
2. `ventana=15m`

## Reglas obligatorias
1. No inventar resultados ni resumir sin evidencia real.
2. Ejecutar comandos reales del repositorio.
3. Si una validacion opcional no puede ejecutarse (por entorno/dependencias), marcarla como no verificable.
4. Mantener salida estructurada con campos consistentes.
5. Reportar errores reales de ejecucion con mensaje breve.

## Salida estructurada esperada
```json
{
  "executed": true,
  "suiteName": "ejecutar-tests:standard-15m",
  "total": 3,
  "passed": 3,
  "failed": 0,
  "errors": [],
  "summary": "Bateria completada sin fallos.",
  "timestamp": "2026-03-27 10:00:00",
  "observations": "Nivel=standard; Ventana=15m; Presupuesto intensivo=0s; Distribucion=sin fase intensiva; No verificables=0; sin redistribuciones.",
  "checks": []
}
```

## Referencia operativa
Usar la matriz de apoyo:
`references/matriz-nivel-ventana.md`

## Ejemplos de invocacion
1. `Usa $ejecutar-tests con nivel=standard y ventana=24h`
2. `Usa $ejecutar-tests con nivel=medio y ventana=45m`
3. `Usa $ejecutar-tests con nivel=agresivo y ventana=1h`
4. `Usa $ejecutar-tests con nivel=extremo y ventana=12h`
