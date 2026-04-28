---
name: checklist-pre-mvp
description: Evaluar si un bloque, modulo, integracion o area de Proyecto Acelerador esta listo para MVP, casi listo o no listo, con checklist estructurada de madurez, bloqueos y siguiente paso minimo. Usar cuando se necesite medir readiness real (funcionalidad, integracion, contratos, compatibilidad, validacion, documentacion, operativa y dependencias externas) sin ejecutar tests ni implementar cambios.
---

# checklist-pre-mvp

## Objetivo
Determinar de forma estructurada el readiness pre-MVP de un bloque o area del proyecto, identificando que ya esta listo, que falta, que bloquea y cual es el siguiente paso minimo para acercarlo a MVP.

## Cuando usar esta skill
1. Cuando se quiere saber si un bloque esta realmente preparado para MVP o casi preparado.
2. Cuando se necesita una checklist razonada de madurez tecnica, no solo una impresion general.
3. Antes de comprometer una entrega MVP de un modulo, integracion o flujo.
4. Cuando hay evidencia parcial y se necesita una conclusion prudente sin inventar.
5. Cuando se deben explicitar bloqueos y dependencias de terceros antes de decidir.

## Cuando no usarla
1. Cuando la necesidad principal sea ejecutar validaciones reales o baterias de tests: usar `$ejecutar-tests`.
2. Cuando la necesidad principal sea documentacion diaria acumulativa: usar `$generar-documentacion`.
3. Cuando la necesidad principal sea cierre formal de bloque tecnico: usar `$cerrar-bloque-tecnico`.
4. Cuando la necesidad principal sea auditoria diagnostica profunda de integracion/capas: usar `$auditar-integracion`.
5. Cuando se espere implementacion directa de cambios: esta skill evalua readiness, no implementa.

## Que no hace
1. No ejecuta tests por defecto ni sustituye resultados de validacion reales.
2. No actualiza el diario tecnico del proyecto.
3. No cierra formalmente un bloque tecnico.
4. No corrige codigo ni aplica cambios.
5. No declara "listo para MVP" sin evidencia suficiente.

## Entradas esperadas
Entradas obligatorias:

1. `bloque_nombre`
2. `objetivo_revision`

Minimo recomendado:

1. `alcance_revisado`
2. `estado_actual`
3. `evidencia_disponible`
4. `validaciones`
5. `bloqueos_conocidos`
6. `dependencias_externas`

Opcionales utiles:

1. `riesgos_residuales`
2. `fuentes_revisadas` o `evidencia_fuente`
3. `observaciones`
4. `fecha_evidencia_principal`
5. `ultimo_estado_conocido`
6. `cambios_recientes_relevantes`

Manejo de faltantes:
1. Si falta un campo obligatorio, pedir solo ese dato.
2. Si faltan campos recomendados, no bloquear la evaluacion.
3. Continuar con evidencia parcial y marcar como `incierto` o `no verificable` cuando aplique.
4. Si no hay validaciones reales, declararlo explicitamente en la salida.
5. Si no se puede confirmar frescura de evidencia, marcar el diagnostico como potencialmente desactualizado.

Preguntas directas permitidas:
1. `Indica solo el nombre del bloque o area a revisar.`
2. `Indica solo el objetivo de la revision pre-MVP.`
3. `Indica solo el alcance revisado.`
4. `Indica solo el estado actual conocido.`
5. `Indica solo la evidencia disponible.`
6. `Indica solo las validaciones ejecutadas o no ejecutadas.`
7. `Indica solo los bloqueos conocidos.`
8. `Indica solo dependencias externas o de otros companeros.`
9. `Indica solo la fecha de la evidencia principal usada para esta evaluacion, si se conoce.`
10. `Indica solo el ultimo estado conocido del bloque, si aplica.`
11. `Indica solo cambios recientes relevantes que puedan afectar el veredicto, si existen.`

## Criterios de readiness para MVP
Evaluar como minimo estas dimensiones:

1. `funcionalidad`: el flujo principal funciona de forma util para MVP.
2. `integracion`: el bloque encaja con los modulos/capas con los que debe convivir.
3. `contratos`: contratos y formatos esperados estan alineados (canonico vs local, si aplica).
4. `compatibilidad`: comportamiento compatible con consumidores actuales y transicion prevista.
5. `validacion/tests`: existe evidencia de validacion real o se explicita su ausencia.
6. `documentacion`: existe documentacion minima suficiente para operar y revisar.
7. `despliegue u operativa`: hay condiciones minimas para ejecutar/soportar el bloque en entorno objetivo.
8. `dependencias externas`: bloqueos o pendientes de terceros estan identificados y acotados.
9. `riesgos y bloqueos`: riesgos residuales y bloqueos criticos estan identificados y priorizados.

## Checklist base de evaluacion
Usar esta base como guia ligera en todas las revisiones pre-MVP:

1. `funcionalidad`: el flujo principal funciona sin depender de pasos fragiles o manuales.
2. `integracion`: productor/consumidor y capas implicadas se comportan de forma coherente en el alcance revisado.
3. `contratos`: existe contrato claro (o transicion clara) y no hay contradicciones relevantes.
4. `compatibilidad`: se mantiene compatibilidad necesaria para MVP o existe plan de transicion minimo realista.
5. `validacion/tests`: hay evidencia de validacion real o se declara explicitamente ausencia/limitacion.
6. `documentacion`: la documentacion minima no contradice el runtime observado.
7. `operativa/despliegue`: existe camino operativo minimo para ejecutar el bloque en entorno objetivo.
8. `dependencias externas`: dependencias de terceros o de otros bloques estan identificadas con impacto claro.
9. `riesgos y bloqueos`: riesgos principales y bloqueos duros estan explicitados y priorizados.

Estado recomendado por item:
1. `cumplido`
2. `no cumplido`
3. `incierto/no verificable`

## Taxonomia de readiness
Taxonomia base recomendada:
1. `listo para MVP`
2. `casi listo para MVP`
3. `no listo para MVP`
4. `no evaluable con la evidencia actual`

Reglas de uso:
1. Usar la taxonomia base salvo que el usuario pida otra.
2. No usar `listo para MVP` sin evidencia suficiente en criterios criticos.
3. Si faltan datos clave o validaciones relevantes, preferir `casi listo` o `no evaluable`.
4. Diferenciar hechos confirmados de supuestos o pendientes.

## Senales orientativas para el veredicto
1. `listo para MVP`: flujo principal funcional, contratos/compatibilidad razonablemente estables, evidencia minima de validacion real y operativa basica resuelta sin bloqueos duros activos.
2. `casi listo para MVP`: base funcional util con gaps acotados, sin bloqueo duro inmediato, y con siguiente paso minimo claro para cerrar huecos cercanos.
3. `no listo para MVP`: existen fallos relevantes en funcionalidad, integracion, contratos, operativa o bloqueos que impiden uso MVP razonable.
4. `no evaluable con la evidencia actual`: cobertura insuficiente, validaciones ausentes o evidencia critica incompleta para sostener una conclusion fiable.

## Bloqueos duros para MVP
Si aparece uno o mas de estos puntos, tratarlo como bloqueo duro hasta resolver o acotar:

1. No existe flujo principal funcional.
2. No hay contrato claro o hay contradiccion grave de contrato.
3. No hay evidencia minima de validacion para el alcance evaluado.
4. Depende de terceros en algo critico no resuelto.
5. La documentacion contradice el runtime de forma relevante.
6. La operativa minima (ejecucion/despliegue) no esta resuelta.
7. El bloque solo funciona en condiciones manuales o fragiles.

## Limitaciones de la revision
1. Si el alcance revisado es parcial, declararlo explicitamente.
2. Si faltan validaciones relevantes, indicarlo como limitacion.
3. Si existen dependencias de otros companeros u otros bloques, reflejarlo como limitacion activa.
4. No presentar la revision como exhaustiva si solo cubre una parte del flujo.
5. Si la evaluacion se apoya en auditorias o documentacion previas sin contraste con estado reciente, declararlo como limitacion de frescura.

## Proceso recomendado
1. Confirmar bloque principal y objetivo de revision pre-MVP.
2. Levantar alcance real revisado y detectar si hay cobertura parcial.
3. Recoger estado actual, evidencia, validaciones, bloqueos y dependencias externas.
4. Comprobar si la evidencia usada refleja el estado reciente (`fecha_evidencia_principal`, `ultimo_estado_conocido`, `cambios_recientes_relevantes`).
5. Si no se puede comprobar frescura, marcar diagnostico potencialmente desactualizado.
6. Evaluar cada criterio de readiness con evidencia trazable.
7. Separar puntos en `cumplido`, `no cumplido` e `incierto/no verificable`.
8. Determinar veredicto global con taxonomia de readiness.
9. Proponer el siguiente paso minimo que mas acerque a MVP sin plan inflado.
10. Redactar salida estructurada de 13 secciones.

## Interfaz sugerida
### Modo normal (guiado)
1. Pedir primero `bloque_nombre` y `objetivo_revision`.
2. Pedir despues solo los campos recomendados faltantes que afecten la conclusion.
3. Evitar preguntas no esenciales.
4. Permitir continuar con evidencia parcial marcando incertidumbre de forma explicita.

### Modo estructurado (directo)
Aceptar toda la informacion en una sola entrada (texto o JSON). Ejemplo JSON:

```json
{
  "bloque_nombre": "transicion-aneca",
  "objetivo_revision": "Determinar si la transicion ANECA esta suficientemente madura para considerarse apta para MVP.",
  "alcance_revisado": [
    "Pipeline",
    "adaptador ANECA",
    "worker/service/cache",
    "API resultado_preferente"
  ],
  "estado_actual": [
    "Contrato canonico ANECA fijado",
    "Legacy aun existe como compatibilidad interna",
    "Modo desacoplado bastante estable"
  ],
  "evidencia_disponible": [
    "smokes en verde",
    "validaciones tecnicas en verde",
    "documentacion actualizada"
  ],
  "validaciones": [
    "ejecutado y correcto: smoke_jobs_queue.php",
    "ejecutado y correcto: validate_scraping_technical_contracts.php"
  ],
  "bloqueos_conocidos": [
    "MCP todavia no integrado extremo a extremo"
  ],
  "dependencias_externas": [
    "integracion MCP",
    "decisiones de despliegue"
  ]
}
```

## Estructura de salida
La salida final debe incluir como minimo estas 13 secciones:

1. `Resumen ejecutivo`
2. `Alcance revisado`
3. `Estado actual del bloque`
4. `Checklist de readiness MVP`
5. `Puntos cumplidos`
6. `Puntos no cumplidos`
7. `Puntos inciertos o no verificables`
8. `Bloqueos principales`
9. `Dependencias externas o ajenas`
10. `Veredicto de readiness`
11. `Siguiente paso minimo para acercarlo a MVP`
12. `Texto breve para Notion`
13. `Texto tecnico ampliado`

Notas de salida:
1. En `Checklist de readiness MVP`, indicar criterio, estado y evidencia asociada.
2. Si faltan validaciones reales, indicarlo explicitamente en el resumen y en el veredicto.
3. Si la cobertura es parcial, no extrapolar al sistema completo.
4. Diferenciar claramente confirmado vs incierto/no verificable.
5. Incluir una nota breve de `Limitaciones de la revision` cuando haya cobertura parcial, falten validaciones o existan dependencias externas relevantes.
6. Si hay `Bloqueos duros para MVP`, reflejarlos en `Bloqueos principales` y condicionar el veredicto.
7. Si hay riesgo de desactualizacion de evidencia, incluir advertencia breve de frescura en `Resumen ejecutivo` o `Texto tecnico ampliado`.

Plantilla sugerida:

```md
## 1. Resumen ejecutivo
Diagnostico global: ...
Alcance de la conclusion: parcial | amplio
Advertencia de frescura: la evidencia principal no refleja necesariamente los cambios mas recientes del bloque. (si aplica)
...

## 2. Alcance revisado
- ...

## 3. Estado actual del bloque
- ...

## 4. Checklist de readiness MVP
- funcionalidad: <cumplido|no cumplido|incierto> | evidencia=...
- integracion: <cumplido|no cumplido|incierto> | evidencia=...
- contratos: <cumplido|no cumplido|incierto> | evidencia=...
- compatibilidad: <cumplido|no cumplido|incierto> | evidencia=...
- validacion/tests: <cumplido|no cumplido|incierto> | evidencia=...
- documentacion: <cumplido|no cumplido|incierto> | evidencia=...
- despliegue u operativa: <cumplido|no cumplido|incierto> | evidencia=...
- dependencias externas: <cumplido|no cumplido|incierto> | evidencia=...
- riesgos y bloqueos: <cumplido|no cumplido|incierto> | evidencia=...

## 5. Puntos cumplidos
- ...

## 6. Puntos no cumplidos
- ...

## 7. Puntos inciertos o no verificables
- ...

## 8. Bloqueos principales
- ...

## 9. Dependencias externas o ajenas
- ...

## 10. Veredicto de readiness
<listo para MVP|casi listo para MVP|no listo para MVP|no evaluable con la evidencia actual>

## 11. Siguiente paso minimo para acercarlo a MVP
...

## 12. Texto breve para Notion
...

## 13. Texto tecnico ampliado
...
```

## Reglas importantes
1. No inventar evidencia ni conclusiones no sustentadas.
2. No asumir readiness por intuicion: usar criterios y evidencia trazable.
3. Si no hay validaciones reales, declararlo explicitamente y ajustar el veredicto.
4. Si faltan datos criticos, marcar `incierto` o `no evaluable` en lugar de forzar conclusion.
5. Si hay contradicciones entre evidencia, estado y bloqueos, senalarlas explicitamente.
6. Si el alcance revisado es parcial, la conclusion global debe reflejar esa limitacion.
7. No comportarse como skill de implementacion.
8. No comportarse como skill de tests.
9. No comportarse como skill de cierre formal de bloque.
10. Priorizar una checklist util y ligera, evitando burocracia innecesaria.
11. El siguiente paso propuesto debe ser minimo, concreto y orientado a desbloquear MVP.
12. Si la cobertura es parcial o falta evidencia critica, la conclusion global debe ser prudente y no sobrevalorar el readiness para MVP.
13. Si existe al menos un bloqueo duro para MVP no resuelto, no emitir `listo para MVP`.
14. Si la evidencia principal parece anterior a cambios recientes relevantes, advertir que el veredicto puede estar parcialmente desactualizado.
15. Ante riesgo de desactualizacion, no presentar como `siguiente paso minimo` algo potencialmente ya resuelto sin contraste.
16. Si no se puede confirmar estado actual de ese punto, marcarlo como `pendiente de contraste con estado actual`.

## Ejemplos de uso
1. `Usa $checklist-pre-mvp para revisar si transicion ANECA esta lista para MVP.`
2. `Usa $checklist-pre-mvp para evaluar cola + cache en readiness pre-MVP.`
3. `Usa $checklist-pre-mvp para revisar si ORCID esta suficientemente consolidado para MVP.`
4. `Usa $checklist-pre-mvp para decidir si integracion MCP puede entrar en MVP.`
5. `Usa $checklist-pre-mvp para revisar despliegue Docker/OCR con foco de readiness.`
6. `Usa $checklist-pre-mvp para evaluar si este modulo backend esta apto para entrega MVP.`

## Relacion con otras skills del proyecto
1. Con `$ejecutar-tests`: esta skill consume estado de validaciones existente, pero no ejecuta pruebas reales.
2. Con `$generar-documentacion`: esta skill genera un diagnostico pre-MVP puntual, pero no mantiene documentacion diaria acumulativa.
3. Con `$cerrar-bloque-tecnico`: esta skill evalua readiness para MVP; el cierre formal del bloque se realiza aparte cuando corresponda.
4. Con `$auditar-integracion`: esta skill mide readiness global del bloque para MVP; si hay dudas profundas de integracion, usar auditoria especifica con `$auditar-integracion`.
5. Flujo recomendado cuando aplique: auditar integracion (`$auditar-integracion`) -> validar (`$ejecutar-tests`) -> evaluar readiness (`$checklist-pre-mvp`) -> cerrar bloque (`$cerrar-bloque-tecnico`) -> registrar en diario (`$generar-documentacion`).
