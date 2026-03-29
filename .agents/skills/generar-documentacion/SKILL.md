---
name: generar-documentacion
description: Generar y mantener documentacion tecnica diaria y registro diario de trabajo en Proyecto Acelerador. Usar cuando el usuario invoque $generar-documentacion o pida crear/actualizar `docs/estado-tecnico-YYYY-MM-DD.md` y `docs/registro-diario-YYYY-MM-DD.md` con merge inteligente, sin sobrescribir, sin duplicados y con validacion final opcional de $ejecutar-tests.
---

# generar-documentacion

## Mision
Generar y mantener los documentos diarios del proyecto con evidencia real del trabajo, acumulacion inteligente y trazabilidad de autoria.

## Flujo por defecto (interfaz normal)
En modo humano/interfaz, pedir **solo** estas 3 cosas en secuencia (pregunta-respuesta real):

1. `Autor de la documentación [Basilio Lagares]:`
2. Rol segun autor:
- Si autor es `Basilio Lagares`: `Rol [Desarrollo backend]:`
- Si no: `Indica el rol del autor:`
3. `¿Quieres ejecutar la batería de tests ahora? [s/N]:`

No pedir JSON, no pedir bloques manuales, no pedir secciones 1..8.

Despues de esas 3 respuestas, la skill continua automaticamente:
1. Detecta contexto real de la sesion.
2. Genera/actualiza:
- `docs/estado-tecnico-YYYY-MM-DD.md`
- `docs/registro-diario-YYYY-MM-DD.md`
3. Si el usuario acepta tests, ejecuta `$ejecutar-tests`.
4. Documenta resultado real de tests o, si no se ejecutan:
- `No se han realizado tests en esta ejecución.`

## Modo estructurado (automatizacion)
Solo se activa de forma explicita con payload/bandera:

1. `--payload-json '<JSON>'`
2. `--payload-file <ruta-json>`
3. `--stdin`

Recomendado para pipelines: agregar `--non-interactive`.

## Reglas obligatorias
1. Mantener estructura fija de documentos (cabecera, secciones, firma).
2. No sobrescribir en bruto: siempre merge por seccion.
3. No duplicar contenido.
4. No inventar datos de trabajo ni resultados de tests.
5. Conservar autor/rol de cabecera del primer autor del dia.
6. Etiquetar nuevas lineas con `[Autor | Rol]` cuando cambia el autor.
7. Mantener seccion 8 de validacion con trazabilidad clara de la ultima validacion disponible del dia.
