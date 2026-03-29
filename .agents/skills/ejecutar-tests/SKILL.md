---
name: ejecutar-tests
description: Ejecutar baterias de tests y validaciones tecnicas en el repositorio Acelerador con salida estructurada reutilizable. Usar cuando el usuario invoque $ejecutar-tests, pida correr validaciones smoke/regresion/integracion, o cuando otra skill necesite resultados reales de pruebas para documentacion tecnica.
---

# ejecutar-tests

## Mision
Ejecutar validaciones reales del repositorio y devolver resultados claros, trazables y estructurados para consumo humano o automatizado.

## Inputs validos
1. `nivel`: `standard` | `medio` | `agresivo`
2. `ventana`: `15m` | `30m` | `45m` | `1h` | `6h`

Si falta un input:
1. Pedir solo el dato faltante.
2. No pedir informacion adicional.
3. Preguntas permitidas:
4. `Indica solo el nivel: standard, medio o agresivo.`
5. `Indica solo la ventana: 15m, 30m, 45m, 1h o 6h.`

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
  "observations": "Nivel standard, ventana 15m, 0 verificaciones no verificables."
}
```

## Referencia operativa
Usar la matriz de apoyo:
`references/matriz-nivel-ventana.md`

## Ejemplos de invocacion
1. `Usa $ejecutar-tests con nivel=standard y ventana=15m`
2. `Usa $ejecutar-tests con nivel=medio y ventana=45m`
3. `Usa $ejecutar-tests con nivel=agresivo y ventana=1h`
