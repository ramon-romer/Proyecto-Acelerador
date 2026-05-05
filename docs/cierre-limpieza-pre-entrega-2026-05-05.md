# Cierre de limpieza pre-entrega - 2026-05-05

## Resumen ejecutivo

Este documento formaliza el cierre documental de la auditoria de limpieza pre-entrega realizada en modo solo inspeccion sobre el repositorio `Proyecto-Acelerador`, rama `Desarrollo`.

La conclusion operativa es que la preparacion para entrega MVP no debe basarse en borrado directo de archivos dentro del repositorio, sino en una paquetizacion controlada por lista blanca. El repositorio contiene materiales con naturalezas distintas: codigo y documentacion aptos para demo/comite, componentes necesarios para despliegue productivo, y evidencias internas de laboratorio, pruebas, outputs, backups o portfolio tecnico que no deben mezclarse con el paquete entregable.

Esta tarea no ejecuta limpieza real. No se han borrado, movido, renombrado ni modificado archivos de codigo. MCP queda pendiente y fuera del alcance de esta limpieza.

## Estado actual del repositorio

El repositorio se encuentra en una fase pre-entrega orientada a consolidar un MVP demostrable. La auditoria identifica una convivencia normal en proyectos de desarrollo activo entre:

- Fuentes funcionales de la aplicacion.
- Documentacion tecnica y de seguimiento.
- Contratos, esquemas y validaciones.
- Resultados de pruebas, logs, outputs y artefactos temporales.
- Material sensible o potencialmente sensible procedente de PDFs, storage, dumps o backups.
- Componentes MCP todavia pendientes de cierre especifico.

El estado recomendado para entrega no es "limpiar el repositorio hasta dejar solo lo visible", sino definir paquetes de salida con criterios distintos segun el destinatario y el objetivo: demo, despliegue o laboratorio interno.

## Separacion conceptual de paquetes

### A) Paquete comite/demo

Este paquete debe contener solo lo necesario para explicar, demostrar y defender el MVP ante comite o evaluadores no operativos. Debe priorizar claridad, reproducibilidad controlada y ausencia de datos sensibles.

Contenido recomendado:

- Documentacion de estado tecnico y cierre MVP.
- Guias de uso o demostracion.
- Evidencias sinteticas no sensibles.
- Capturas o outputs curados cuando sean necesarios.
- Contratos o esquemas utiles para explicar la arquitectura, si no contienen informacion sensible.

No debe incluir:

- PDFs reales con PII.
- Dumps SQL, backups o exports completos.
- Logs extensos, outputs brutos, zips generados o evidencias no revisadas.
- Credenciales, configuraciones locales o material de laboratorio.
- Componentes MCP no cerrados como si fueran parte estable de la entrega.

### B) Paquete despliegue produccion

Este paquete debe contener solo lo necesario para levantar, configurar, validar y operar la aplicacion en un entorno productivo o preproductivo controlado.

Contenido recomendado:

- Codigo fuente necesario para backend, frontend, servicios y scripts operativos validados.
- Esquemas, migraciones, contratos y configuracion de despliegue imprescindible.
- Documentacion tecnica de despliegue, operacion y validacion.
- Tests o smoke checks necesarios para verificar la instalacion.

No debe incluir:

- Datos reales de usuarios o candidatos.
- PDFs de almacenamiento local.
- Backups historicos, dumps manuales o exports de desarrollo.
- Reports de ejecuciones antiguas que no formen parte del proceso operativo.
- Artefactos zip u outputs generados en pruebas.

### C) Paquete laboratorio/portfolio interno

Este paquete agrupa material util para trazabilidad, aprendizaje, auditoria interna, portfolio tecnico o investigacion, pero no debe confundirse con la entrega MVP ni con el paquete de produccion.

Contenido posible:

- Informes de auditoria y cierre.
- Reports de validaciones y baterias de pruebas.
- Outputs de experimentacion.
- Backups historicos conservados por decision manual.
- Evidencias de integraciones, pruebas OCR, scraping o colas.
- Documentacion de decisiones y deuda tecnica.

Este paquete puede conservar rutas de valor interno, pero requiere revision manual cuando existan datos personales, dumps SQL o materiales generados no curados.

## Tabla de clasificacion de rutas

| Ruta o patron | Clasificacion recomendada | Riesgo principal | Accion recomendada |
| --- | --- | --- | --- |
| `docs/` | Comite/demo y laboratorio interno | Bajo, salvo adjuntos o volcados incrustados | Mantener; revisar documentos nuevos antes de entrega |
| `docs/schemas/` | Produccion y documentacion tecnica | Bajo | Mantener si describe contratos vigentes |
| `docs/sql/` | Produccion si son migraciones controladas; laboratorio si son dumps | Medio | Revisar manualmente contenido SQL antes de empaquetar |
| `docs/test-runs/` | Laboratorio interno | Logs, outputs, datos de prueba | Excluir del paquete comite salvo evidencias curadas |
| `acelerador_panel/` | Produccion y demo tecnica | Configuracion local, outputs o caches internas | Incluir solo por lista blanca |
| `meritos/` | Produccion y demo tecnica | Datos procesados, outputs scraping/OCR | Incluir solo componentes necesarios y revisados |
| `evaluador/src/` | Fuera del alcance de esta limpieza | Riesgo por modificacion no autorizada | No tocar en esta fase |
| `mcp-server/` | Pendiente, fuera de alcance | MCP no cerrado | No tocar ni cerrar; tratar como pendiente |
| `storage/` | Riesgo alto | PII en PDFs o datos derivados | Revision manual obligatoria; no empaquetar por defecto |
| `*.pdf` | Riesgo alto | PII o documentacion no anonimizada | Revision manual antes de cualquier entrega |
| `*.sql` | Riesgo alto | Dumps, backups, datos reales o estructura sensible | Revision manual; incluir solo migraciones limpias |
| `*.zip` | Riesgo medio/alto | Artefactos empaquetados opacos | Excluir por defecto salvo validacion manual |
| `reports/` | Laboratorio interno | Logs, evidencias brutas, salidas antiguas | Excluir del paquete comite; conservar para trazabilidad |
| `logs/` | Laboratorio interno | Datos sensibles, rutas locales, errores | Excluir por defecto |
| `outputs/` | Laboratorio interno | Resultados generados no curados | Excluir por defecto |
| `.gitignore` / `.dockerignore` | Configuracion del repositorio | Cambio de politica de versionado | No modificar en esta tarea |

## Riesgos detectados

### PII en PDFs/storage

Los PDFs y rutas de almacenamiento pueden contener datos personales, CVs, expedientes, identificadores, nombres, correos, telefonos u otra informacion no apta para una entrega amplia. Cualquier ruta bajo `storage/` o cualquier patron `*.pdf` debe considerarse sensible hasta que exista revision manual.

Riesgo MVP: exponer informacion personal en un paquete de demo o comite.

Mitigacion: excluir por defecto y permitir solo documentos anonimizados o sinteticos mediante lista blanca.

### Dumps/backups SQL

Los ficheros SQL pueden ser migraciones limpias, pero tambien dumps, backups o volcados con datos reales. No deben clasificarse automaticamente como seguros por extension ni por ubicacion.

Riesgo MVP: distribuir datos reales, historicos internos o estructura no destinada a terceros.

Mitigacion: revisar manualmente cada SQL y separar migraciones operativas de dumps/backups.

### Logs/reports/outputs/zips

Los logs, reports, outputs y zips generados pueden contener rutas locales, trazas, datos de prueba, resultados obsoletos, documentos procesados o paquetes opacos dificiles de auditar rapidamente.

Riesgo MVP: entregar ruido tecnico, evidencias no curadas o informacion sensible indirecta.

Mitigacion: excluir del paquete comite/demo por defecto; conservar en laboratorio interno si aportan trazabilidad.

### MCP pendiente

El area MCP no debe presentarse como cerrada dentro de esta limpieza. Cualquier decision sobre su apagado, cierre, migracion o entrega requiere un bloque especifico posterior.

Nota explicita: MCP queda pendiente y fuera del alcance de esta limpieza.

## Politica recomendada

### No borrado directo

No se recomienda borrar archivos como mecanismo principal de preparacion pre-entrega. El borrado directo puede destruir trazabilidad, evidencias tecnicas o materiales utiles para auditoria interna.

La limpieza debe ser documental y de empaquetado, no destructiva.

### Paquetizacion por lista blanca

Cada paquete de entrega debe generarse desde una lista blanca explicita de rutas permitidas. Todo lo que no este en la lista blanca queda excluido por defecto.

Criterios minimos para entrar en lista blanca:

- Necesidad clara para demo, despliegue o documentacion MVP.
- Ausencia de PII o datos reales no anonimizados.
- Estado tecnico vigente.
- Compatibilidad con el alcance de entrega.
- Revision manual cuando la ruta pertenezca a categorias de riesgo.

### Revision manual de rutas de riesgo

Las rutas con PDFs, storage, SQL, zips, logs, reports u outputs requieren revision manual antes de cualquier empaquetado externo.

La revision debe confirmar:

- Si contiene datos personales.
- Si contiene datos reales o historicos.
- Si es necesario para la entrega.
- Si existe alternativa sintetica o anonimizada.
- Si debe quedar solo en laboratorio/portfolio interno.

## Rutas que NO se deben borrar

Las siguientes rutas o familias de rutas no deben borrarse como parte de la limpieza pre-entrega:

- `evaluador/src/`
- `mcp-server/`
- `.gitignore`
- `.dockerignore`
- `docs/`
- `docs/schemas/`
- Documentacion de cierre, estado tecnico, registros diarios y auditorias.
- Contratos JSON y esquemas vigentes.
- Codigo fuente necesario para el MVP.
- Scripts de validacion o smoke tests usados para demostrar funcionamiento.
- Evidencias internas utiles para trazabilidad, siempre que se mantengan fuera del paquete externo si contienen riesgos.
- Material MCP pendiente, que debe conservarse hasta cierre especifico.

Esta lista no autoriza incluir automaticamente dichas rutas en paquetes externos; solo fija que no deben eliminarse en esta fase.

## Checklist de proximos pasos seguros

- Definir una lista blanca para el paquete comite/demo.
- Definir una lista blanca separada para el paquete de despliegue produccion.
- Mantener el paquete laboratorio/portfolio interno fuera de la entrega externa.
- Revisar manualmente cualquier `*.pdf` y cualquier contenido bajo `storage/`.
- Revisar manualmente cualquier `*.sql` para distinguir migraciones de dumps/backups.
- Excluir por defecto `logs/`, `reports/`, `outputs/` y `*.zip` de paquetes externos.
- Validar que no se incorporan credenciales, rutas locales sensibles ni datos reales.
- Documentar cualquier excepcion aprobada con motivo, responsable y fecha.
- Preparar una auditoria especifica posterior para MCP.
- Confirmar antes de entrega que el paquete final procede de lista blanca y no de copia completa del repositorio.

## Cierre

La limpieza pre-entrega queda documentada como una politica de separacion y empaquetado, no como una intervencion destructiva sobre el repositorio.

El criterio recomendado para MVP es conservar la trazabilidad interna, reducir el ruido del paquete externo y controlar riesgos mediante lista blanca y revision manual de rutas sensibles.

MCP queda pendiente y fuera del alcance de esta limpieza.
