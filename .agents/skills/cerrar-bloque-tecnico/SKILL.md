---
name: cerrar-bloque-tecnico
description: Cerrar un bloque tecnico concreto de Proyecto Acelerador y dejar su estado consolidado, documentado y listo para compartir con el equipo. Usar cuando haya suficiente implementacion o analisis real para fijar una foto estable del bloque (transicion ANECA, cola de procesamiento, cache por hash, Docker OCR, contratos JSON, integracion MCP, fallback desacoplado) con salida estructurada de resumen, decisiones, validaciones, compatibilidad, deuda y siguiente paso.
---

# cerrar-bloque-tecnico

## Objetivo
Consolidar el cierre de un bloque tecnico concreto con evidencia real y salida reutilizable para coordinacion tecnica del equipo.

## Cuando usar esta skill
1. Al cerrar un bloque tecnico ya implementado o estabilizado.
2. Cuando ya existe suficiente implementacion o analisis real como para fijar una foto estable del bloque.
3. Al necesitar un resumen claro de que se hizo y por que.
4. Al querer fijar decisiones de arquitectura y compatibilidad mantenida.
5. Al preparar texto reutilizable para Notion y para documentacion tecnica.
6. Al pasar un bloque a estado "cerrado con siguiente paso definido".

## Cuando no usarla
1. Cuando la necesidad principal sea ejecutar validaciones reales o baterias de tests: usar `$ejecutar-tests`.
2. Cuando la necesidad principal sea actualizar documentos diarios acumulativos (`docs/estado-tecnico-YYYY-MM-DD.md` y `docs/registro-diario-YYYY-MM-DD.md`): usar `$generar-documentacion`.
3. Cuando el bloque aun esta en discovery y no hay decisiones ni evidencia suficiente para cerrarlo.

## Que no hace
1. No ejecuta baterias de tests ni reemplaza validaciones reales.
2. No mantiene documentos diarios ni hace merge automatico en los archivos de estado diario.
3. No infiere evidencia faltante a partir de suposiciones.
4. No cierra multiples bloques en una sola salida si no se define alcance explicito.

## Entradas esperadas
Entradas obligatorias:

1. `bloque_nombre`
2. `bloque_contexto`

Minimo recomendado:

1. `archivos_modificados`
2. `decisiones_tomadas`
3. `validaciones_ejecutadas`
4. `compatibilidad_mantenida`
5. `deuda_tecnica_pendiente`
6. `siguiente_paso_previsto`

Opcionales utiles:

1. `estado_bloque`
2. `riesgos_residuales`
3. `observaciones`

Manejo de faltantes:
1. Si falta un campo obligatorio, pedir solo ese dato.
2. Si faltan campos recomendados, no bloquear la skill.
3. Permitir continuar en modo guiado o marcar el campo como `no aportado` o `no disponible`.
4. No inferir evidencia tecnica no aportada.

Preguntas directas permitidas:
1. `Indica solo el nombre del bloque tecnico.`
2. `Indica solo el contexto/proposito del bloque.`
3. `Indica solo los archivos o modulos modificados.`
4. `Indica solo las decisiones tomadas.`
5. `Indica solo las validaciones ejecutadas y su resultado real (o "no ejecutadas").`
6. `Indica solo la compatibilidad mantenida.`
7. `Indica solo la deuda tecnica pendiente.`
8. `Indica solo el siguiente paso previsto.`
9. `Indica solo el estado del bloque (cerrado, cerrado con deuda, cerrado provisional, en consolidacion).`
10. `Indica solo riesgos residuales u observaciones, si aplica.`

## Clasificacion del estado del bloque
Clasificar el bloque cuando haya informacion suficiente en uno de estos estados:

1. `cerrado`: alcance principal completado, evidencia consistente y sin pendientes bloqueantes declarados.
2. `cerrado con deuda`: alcance principal completado, con deuda tecnica pendiente explicitada.
3. `cerrado provisional`: bloque utilizable, pero con validaciones parciales, riesgos relevantes o decisiones pendientes.
4. `en consolidacion`: aun hay cambios abiertos o no existe base estable para dar cierre.

Si no hay informacion suficiente para clasificar bien:
1. Declarar explicitamente `estado_bloque: no clasificable (informacion insuficiente)`.
2. No forzar una etiqueta de cierre.

## Tratamiento de validaciones
Estados permitidos para cada validacion:

1. `no ejecutado`
2. `ejecutado y correcto`
3. `ejecutado y fallido`
4. `ejecutado parcialmente`
5. `desconocido`

Reglas de uso:
1. Solo `ejecutado y correcto` se puede comunicar como validado.
2. `no ejecutado`, `ejecutado y fallido`, `ejecutado parcialmente` y `desconocido` no se deben presentar como validado.
3. Si hay mezcla de estados, reportar cada validacion con su estado y declarar un estado global coherente (por ejemplo: parcial, fallido o no verificable).
4. No usar lenguaje ambiguo tipo "validado" si no aplica al estado real.

## Proceso recomendado
1. Confirmar que la peticion corresponde a un solo bloque tecnico.
2. Si el usuario mezcla varios bloques, pedir separarlos o elegir uno como bloque principal.
3. Confirmar `bloque_nombre` y `bloque_contexto`.
4. Recoger evidencia real disponible y marcar faltantes como `no aportado` o `no disponible` cuando corresponda.
5. Normalizar las validaciones segun los 5 estados permitidos.
6. Clasificar `estado_bloque` si hay informacion suficiente; si no, declararlo como no clasificable.
7. Redactar salida estructurada de 10 secciones.
8. Generar `Texto breve para Notion` y `Texto detallado para documentacion tecnica`.
9. Cerrar con siguiente paso natural accionable.

## Interfaz sugerida
### Modo normal (guiado)
1. Pedir primero los 2 campos obligatorios.
2. Pedir campos recomendados solo si faltan y aportan valor para cerrar mejor el bloque.
3. Permitir continuar sin bloquear cuando falte informacion recomendada, dejando trazado `no aportado` o `no disponible`.
4. Si hay multiples bloques en la misma peticion, pedir separarlos o elegir bloque principal.

### Modo estructurado (directo)
Aceptar toda la informacion en una sola entrada (texto o JSON). Ejemplo JSON:

```json
{
  "bloque_nombre": "transicion-aneca",
  "bloque_contexto": "Consolidar el flujo de evaluacion ANECA sin romper contratos existentes.",
  "archivos_modificados": [
    "backend/src/aneca/TransitionService.php",
    "backend/src/aneca/LegacyAdapter.php",
    "docs/aneca/contratos-compatibilidad.md"
  ],
  "decisiones_tomadas": [
    "Mantener contrato JSON v1 y mapear campos nuevos en adaptador interno.",
    "Separar logica de fallback para permitir rollout gradual."
  ],
  "validaciones_ejecutadas": [
    {
      "nombre": "ejecutar-tests medio 45m",
      "estado": "ejecutado y correcto",
      "detalle": "php .agents/skills/ejecutar-tests/scripts/ejecutar_tests.php --json --nivel=medio --ventana=45m -> passed"
    }
  ],
  "compatibilidad_mantenida": [
    "Sin ruptura de contrato JSON v1",
    "Compatibilidad con flujo legacy de evaluacion"
  ],
  "deuda_tecnica_pendiente": [
    "Eliminar adaptador legacy cuando el 100% del trafico use v2"
  ],
  "siguiente_paso_previsto": "Instrumentar metricas de adopcion v2 y plan de retirada del adaptador.",
  "estado_bloque": "cerrado con deuda",
  "riesgos_residuales": [
    "Cobertura de casos borde legacy aun incompleta"
  ],
  "observaciones": "Pendiente definir fecha objetivo para retirar el adaptador legacy."
}
```

## Estructura de salida
La salida final debe incluir siempre estas 10 secciones:

1. `Resumen ejecutivo`
2. `Resumen tecnico`
3. `Cambios principales`
4. `Decisiones de arquitectura`
5. `Validaciones ejecutadas`
6. `Compatibilidad mantenida`
7. `Deuda tecnica restante`
8. `Siguiente paso natural`
9. `Texto breve para Notion`
10. `Texto detallado para documentacion tecnica`

Notas de formato en la salida:
1. Incluir `Estado del bloque` dentro de `Resumen ejecutivo`.
2. En `Validaciones ejecutadas`, listar cada validacion con su estado permitido y una conclusion global coherente.
3. Si faltan datos recomendados, mantener trazabilidad con `no aportado` o `no disponible`.

Plantilla sugerida:

```md
## 1. Resumen ejecutivo
Estado del bloque: ...
...

## 2. Resumen tecnico
...

## 3. Cambios principales
- ...

## 4. Decisiones de arquitectura
- ...

## 5. Validaciones ejecutadas
- <validacion>: <estado> - <detalle>
Estado global de validacion: ...

## 6. Compatibilidad mantenida
- ...

## 7. Deuda tecnica restante
- ...

## 8. Siguiente paso natural
...

## 9. Texto breve para Notion
...

## 10. Texto detallado para documentacion tecnica
...
```

## Nota sobre deuda y riesgos
1. `Deuda tecnica`: trabajo pendiente estructural o de mantenimiento (refactor, hardening, limpieza, migraciones pendientes).
2. `Riesgos residuales`: problemas potenciales que pueden materializarse aunque no exista una tarea inmediata ya definida.
3. No mezclar ambos conceptos si la evidencia permite separarlos.

## Reglas importantes
1. No inventar evidencia tecnica ni resultados de validacion.
2. Si no hay tests o validaciones reales, declararlo de forma explicita.
3. No presentar como validado nada que este `no ejecutado`, `ejecutado y fallido`, `ejecutado parcialmente` o `desconocido`.
4. No ejecutar tests por defecto ni actuar como skill de testing.
5. No actualizar automaticamente documentacion diaria acumulativa.
6. Mantener foco modular: cerrar un bloque tecnico concreto, no toda la sesion diaria.
7. Si la peticion mezcla varios bloques, pedir separacion o bloque principal antes de cerrar.
8. Explicitar compatibilidad mantenida, riesgos residuales y deuda restante cuando existan.
9. Permitir tono practico y reusable, sin rigidez excesiva.
10. Se puede apoyar en salidas previas de otras skills solo si fueron aportadas en la entrada.
11. Si la informacion aportada contiene contradicciones entre decisiones, validaciones o estado del bloque, senalarlo explicitamente en la salida en lugar de resolverlo por cuenta propia.

## Ejemplos de uso
1. `Usa $cerrar-bloque-tecnico para cerrar bloque transicion ANECA con estos archivos, decisiones y validaciones.`
2. `Usa $cerrar-bloque-tecnico para cerrar bloque cola de procesamiento; no hubo tests, indicalo y deja siguiente paso.`
3. `Usa $cerrar-bloque-tecnico para consolidar bloque cache por hash y generar texto para Notion + texto tecnico ampliado.`
4. `Usa $cerrar-bloque-tecnico para cerrar bloque Docker OCR manteniendo compatibilidad con pipeline actual.`
5. `Usa $cerrar-bloque-tecnico para cerrar bloque contratos JSON y listar deuda tecnica restante.`
6. `Usa $cerrar-bloque-tecnico para cerrar integracion MCP con payload completo en JSON.`
7. `Usa $cerrar-bloque-tecnico para cerrar fallback desacoplado y proponer siguiente paso de hardening.`
8. `Usa $cerrar-bloque-tecnico para estos dos bloques y ayudame a elegir uno como bloque principal para cerrar hoy.`

## Relacion con otras skills del proyecto
1. Con `$ejecutar-tests`: esta skill consume y comunica resultados de validacion ya existentes; no los sustituye ni los simula.
2. Con `$generar-documentacion`: esta skill genera contenido de cierre de bloque reutilizable; no mantiene el historial diario ni hace merge en los documentos del dia.
3. Flujo recomendado cuando aplique: ejecutar validaciones reales con `$ejecutar-tests` -> cerrar bloque con `$cerrar-bloque-tecnico` -> reflejar el avance diario con `$generar-documentacion`.
