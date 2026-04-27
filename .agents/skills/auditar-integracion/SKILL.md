---
name: auditar-integracion
description: Auditar una integracion tecnica concreta en Proyecto Acelerador para detectar alineaciones, desalineaciones, dependencias legacy, riesgos de contrato y acoplamientos entre modulos/capas. Usar antes de cerrar o migrar un flujo (MCP->ANECA, frontend->backend, backend->cola/cache, contratos JSON, fallback preferente/desacoplado) cuando se necesite un diagnostico estructurado y accionable sin implementar cambios.
---

# auditar-integracion

## Objetivo
Auditar una integracion o cruce tecnico entre modulos/capas para identificar que esta alineado, que requiere revision, que depende de legacy y que corre riesgo de romper contratos.

## Cuando usar esta skill
1. Cuando se necesita diagnosticar una integracion concreta antes de cerrar, migrar o endurecer arquitectura.
2. Cuando existe evidencia parcial o completa y hace falta una lectura tecnica estructurada.
3. Cuando se quiere detectar producer/consumer mismatch o divergencias entre contrato canonico y runtime real.
4. Cuando se revisa encaje de un modulo nuevo frente a la arquitectura actual del proyecto.
5. Cuando se necesita una auditoria util para priorizar correccion minima y siguiente paso tecnico.

## Cuando no usarla
1. Cuando la necesidad principal sea ejecutar validaciones o baterias de tests reales: usar `$ejecutar-tests`.
2. Cuando la necesidad principal sea mantener documentacion diaria acumulativa: usar `$generar-documentacion`.
3. Cuando la necesidad principal sea cerrar formalmente un bloque tecnico ya consolidado: usar `$cerrar-bloque-tecnico`.
4. Cuando se espere implementacion directa de cambios de codigo: esta skill audita y diagnostica.

## Que no hace
1. No ejecuta tests por defecto ni reemplaza validaciones reales.
2. No actualiza la documentacion diaria del proyecto.
3. No cierra el bloque tecnico en nombre de `$cerrar-bloque-tecnico`.
4. No corrige codigo; solo propone correccion minima o transicion sugerida.
5. No inventa evidencia tecnica cuando faltan datos.

## Entradas esperadas
Entradas obligatorias:

1. `integracion_nombre`
2. `objetivo_auditoria`

Minimo recomendado:

1. `alcance_revisado` (modulos, archivos, capas o contratos revisados)
2. `criterio_referencia` (criterio objetivo, contrato canonico o regla de comparacion)
3. `hallazgos_observados` (evidencia disponible)

Opcionales utiles:

1. `riesgos_iniciales`
2. `taxonomia_diagnostico` (si el equipo quiere variar etiquetas)
3. `observaciones`
4. `fuentes_revisadas` o `evidencia_fuente` (PRs, commits, logs, rutas de runtime, documentos o tickets)

Manejo de faltantes:
1. Si falta un campo obligatorio, pedir solo ese dato.
2. Si faltan campos recomendados, no bloquear la auditoria.
3. Continuar en modo guiado y marcar datos faltantes como `no aportado` o `no verificable` cuando corresponda.
4. Si no hay evidencia suficiente para afirmar algo, clasificar como `REVISAR` o `NO VERIFICABLE`.

Preguntas directas permitidas:
1. `Indica solo el nombre de la integracion o flujo a auditar.`
2. `Indica solo el objetivo de la auditoria.`
3. `Indica solo el alcance revisado (modulos/archivos/capas/contratos).`
4. `Indica solo el criterio o referencia de comparacion.`
5. `Indica solo los hallazgos observados o evidencia disponible.`
6. `Indica solo riesgos iniciales, si aplica.`

## Patrones de relacion a auditar
1. `productor -> normalizador -> consumidor`
2. `contrato canonico` vs `contrato tecnico local`
3. `via preferente` vs `fallback`
4. `canonico` vs `legacy`
5. `producer/consumer mismatch`
6. coherencia entre `job/cache/worker/service/API/frontend`

## Taxonomia de diagnostico
Taxonomia base recomendada:
1. `OK`
2. `REVISAR`
3. `INCORRECTO`
4. `LEGACY TRANSITORIO`
5. `NO APLICA DIRECTAMENTE`
6. `OBJETIVO CANONICO NO IMPLEMENTADO`

Reglas de uso:
1. Usar la taxonomia base salvo que el usuario aporte una taxonomia explicita.
2. Si la evidencia es insuficiente, priorizar `REVISAR` y marcar `NO VERIFICABLE` en notas.
3. No usar `OK` sin evidencia concreta trazable.
4. Distinguir estado tecnico (`OK/REVISAR/...`) de certeza de evidencia (`confirmado/sospechado/pendiente`).

## Semantica de evidencia
Clasificar cada afirmacion relevante como:
1. `confirmado` (evidencia explicita disponible)
2. `sospechado` (indicios razonables, sin confirmacion completa)
3. `pendiente de comprobar` (falta evidencia operativa para concluir)

Si hay contradicciones entre datos de entrada:
1. Senalarlas explicitamente.
2. No resolverlas por su cuenta ni forzar conclusion.

## Limitaciones de la auditoria
1. Si el alcance revisado es parcial, declararlo explicitamente.
2. Si no se revisaron productores, consumidores o contratos relevantes, indicarlo como limitacion.
3. No presentar la auditoria como exhaustiva si solo cubre una parte del flujo.
4. No extrapolar conclusiones al sistema completo cuando la evidencia sea parcial.

## Proceso recomendado
1. Confirmar que la peticion se refiere a una integracion principal.
2. Si la entrada mezcla varias integraciones distintas, pedir separarlas o elegir una principal.
3. Recoger objetivo, alcance y criterio de referencia.
4. Mapear piezas por rol: productor, normalizador, consumidor, preferente, fallback, legacy/canonico.
5. Contrastar evidencia observada contra criterio de referencia.
6. Clasificar cada pieza con taxonomia de diagnostico y semantica de evidencia.
7. Detectar acoplamientos, dependencias legacy, divergencias de contrato y riesgos de ruptura.
8. Ajustar el diagnostico global al alcance real (parcial o amplio) sin extrapolar.
9. Proponer cambio minimo de correccion o transicion (sin implementar).
10. Redactar salida estructurada de 11 secciones.

## Interfaz sugerida
### Modo normal (guiado)
1. Pedir primero `integracion_nombre` y `objetivo_auditoria`.
2. Pedir despues `alcance_revisado`, `criterio_referencia` y `hallazgos_observados` si faltan.
3. Permitir continuar con evidencia parcial, marcando incertidumbre de forma explicita.
4. Evitar pedir informacion no imprescindible para emitir diagnostico util.

### Modo estructurado (directo)
Aceptar toda la informacion en una sola entrada (texto o JSON). Ejemplo JSON:

```json
{
  "integracion_nombre": "consumidores-legacy-vs-aneca",
  "objetivo_auditoria": "Detectar que consumidores siguen acoplados al payload legacy y que partes ya pueden usar ANECA.",
  "alcance_revisado": [
    "meritos/scraping/src/Pipeline.php",
    "meritos/scraping/src/ProcessingJobWorker.php",
    "meritos/scraping/src/CvProcessingJobService.php",
    "meritos/scraping/public/api_cv_procesar.php",
    "docs/schemas/contrato-canonico-aneca-v1.schema.json"
  ],
  "criterio_referencia": [
    "ANECA como contrato canonico oficial",
    "modo desacoplado como fallback",
    "MCP como via preferente futura"
  ],
  "hallazgos_observados": [
    "La API ya expone resultado_preferente",
    "Worker y service siguen usando parte de la semantica legacy",
    "Hay deuda tecnica en mcp-server"
  ],
  "riesgos_iniciales": [
    "Divergencia entre runtime real y documentacion",
    "Dependencia temporal de resultado_json"
  ]
}
```

## Estructura de salida
La salida final debe incluir como minimo estas 11 secciones:

1. `Resumen ejecutivo`
2. `Alcance auditado`
3. `Hallazgos clave`
4. `Inventario de piezas revisadas`
5. `Clasificacion por estado`
6. `Dependencias o acoplamientos detectados`
7. `Riesgos principales`
8. `Propuesta minima de correccion o transicion`
9. `Siguiente paso tecnico recomendado`
10. `Texto breve para Notion`
11. `Texto tecnico ampliado para documentacion o revision de equipo`

Notas de salida:
1. En cada hallazgo relevante, indicar si esta `confirmado`, `sospechado` o `pendiente de comprobar`.
2. Si no se puede verificar una afirmacion, marcarla como `NO VERIFICABLE`.
3. Incluir trazabilidad clara de posibles rupturas de contrato y dependencias legacy.
4. Incluir en `Resumen ejecutivo` un `Diagnostico global` acorde al alcance auditado (por ejemplo: `alineado con ajustes menores`, `revisar antes de integrar`, `no apto para convergencia aun`).

Plantilla sugerida:

```md
## 1. Resumen ejecutivo
Diagnostico global: ...
Alcance de la conclusion: parcial | amplio
...

## 2. Alcance auditado
- ...

## 3. Hallazgos clave
- [confirmado|sospechado|pendiente] ...

## 4. Inventario de piezas revisadas
- <pieza>: <rol>

## 5. Clasificacion por estado
- <pieza/relacion>: <OK|REVISAR|INCORRECTO|LEGACY TRANSITORIO|NO APLICA DIRECTAMENTE|OBJETIVO CANONICO NO IMPLEMENTADO> | evidencia=<confirmado|sospechado|pendiente>

## 6. Dependencias o acoplamientos detectados
- ...

## 7. Riesgos principales
- ...

## 8. Propuesta minima de correccion o transicion
- ...

## 9. Siguiente paso tecnico recomendado
...

## 10. Texto breve para Notion
...

## 11. Texto tecnico ampliado para documentacion o revision de equipo
...
```

## Reglas importantes
1. No inventar evidencias ni conclusiones no sustentadas.
2. Si falta evidencia para una afirmacion, marcarla como `REVISAR` o `NO VERIFICABLE`.
3. No presentar una pieza como `OK` sin evidencia trazable.
4. Separar claramente lo `confirmado`, lo `sospechado` y lo `pendiente de comprobar`.
5. Si hay contradicciones entre alcance, hallazgos, contratos o estado diagnosticado, senalarlo explicitamente en la salida.
6. Si el alcance revisado es parcial, la conclusion global debe reflejar esa limitacion y no extrapolarse al sistema completo.
7. La propuesta minima de correccion debe priorizar el cambio mas pequeno que reduzca el mayor riesgo detectado.
8. Evitar planes de correccion excesivamente amplios si no son necesarios para mitigar el riesgo principal.
9. No ejecutar tests por defecto ni comportarse como skill de testing.
10. No mantener documentacion diaria acumulativa ni sustituir `$generar-documentacion`.
11. No actuar como skill de cierre de bloque ni sustituir `$cerrar-bloque-tecnico`.
12. Mantener foco en auditoria y diagnostico; la correccion propuesta debe ser minima y no implementada.
13. Si la auditoria mezcla varias integraciones distintas, pedir separacion o definir una integracion principal.

## Ejemplos de uso
1. `Usa $auditar-integracion para auditar MCP -> ANECA y detectar dependencias legacy.`
2. `Usa $auditar-integracion para revisar frontend -> backend y clasificar producer/consumer mismatch.`
3. `Usa $auditar-integracion para auditar backend -> cola/cache y detectar acoplamientos ocultos.`
4. `Usa $auditar-integracion para comparar contrato canonico ANECA vs contrato tecnico local en runtime.`
5. `Usa $auditar-integracion para revisar fallback MCP -> desacoplado y proponer correccion minima.`
6. `Usa $auditar-integracion para validar si este modulo nuevo encaja con la arquitectura actual.`

## Relacion con otras skills del proyecto
1. Con `$ejecutar-tests`: esta skill puede consumir resultados de validacion existentes, pero no ejecuta pruebas reales por si sola.
2. Con `$generar-documentacion`: esta skill produce una auditoria tecnica puntual reutilizable, pero no mantiene el diario acumulativo.
3. Con `$cerrar-bloque-tecnico`: esta skill diagnostica integraciones; el cierre formal del bloque se hace despues con `$cerrar-bloque-tecnico` cuando corresponda.
4. Flujo recomendado cuando aplique: auditar con `$auditar-integracion` -> validar cambios con `$ejecutar-tests` -> cerrar bloque con `$cerrar-bloque-tecnico` -> reflejar actividad diaria con `$generar-documentacion`.
