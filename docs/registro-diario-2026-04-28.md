# Registro diario de trabajo

## Cabecera
PROYECTO: Acelerador
DOCUMENTO: Registro diario
FECHA: 2026-04-28
AUTOR: Basilio Lagares
ROL: Desarrollo backend
ESTADO: En progreso

## 1. Resumen del dia
- [Basilio Lagares | Desarrollo backend] Se documenta retrospectivamente el trabajo del 2026-04-28 porque no se ejecuto `$generar-documentacion` al cierre de la jornada.
- [Basilio Lagares | Desarrollo backend] La actividad principal se concentra en evaluador ANECA/CSYJ, evidencias smoke de cache-contratos-adaptador y evolucion de la operativa de tests.
- [Basilio Lagares | Desarrollo backend] Se incorpora como evidencia el commit `6b850fb` (`ACC-DES-RAMA-CSYJ`) y el merge `8f05c49`, ambos fechados el 2026-04-28.
- [Basilio Lagares | Desarrollo backend] Se conserva criterio prudente: solo se registran hechos trazables en git, ficheros modificados, salidas generadas y reportes existentes.

## 2. Trabajo realizado
- [Basilio Lagares | Desarrollo backend] Integracion de cambios de rama CSYJ en `evaluador_prueba`, con nuevos procesadores, extractor CVN especifico y salidas de prueba.
- [Basilio Lagares | Desarrollo backend] Generacion de multiples resultados CSYJ del 2026-04-28 en JSON, TXT y PDF para el candidato/perfil `3111-1111-1111-1113_CSYJ`.
- [Basilio Lagares | Desarrollo backend] Generacion de evidencias `cv_cache_smoke`, `contracts_sample` y `adapter_sample` con salida canonica ANECA (`*.aneca.canonico.json`).
- [Basilio Lagares | Desarrollo backend] Ejecucion previa registrada de bateria agresiva de backend de tutorias, con reporte `aggressive_battery_report.json` en estado PASS.
- [Basilio Lagares | Desarrollo backend] Cambio en `acelerador_panel/fronten/panel_profesor.php` para que las rutas por perfil apunten a `/evaluador/...`.
- [Basilio Lagares | Desarrollo backend] Actualizacion de `$ejecutar-tests` para incorporar modo interactivo, alcance explicito, salida consola/JSON/ambas, fase intensiva configurable y reporte humano detallado.
- [Basilio Lagares | Desarrollo backend] Adaptacion de `$generar-documentacion` para invocar `$ejecutar-tests` con `--scope=toda-app` y `--intensiva=auto`.

## 3. Decisiones tecnicas
- [Basilio Lagares | Desarrollo backend] Se orienta el frontend hacia `evaluador` como ruta destino, dejando `evaluador_prueba` como candidato a retirada o compatibilidad temporal pendiente de confirmar.
- [Basilio Lagares | Desarrollo backend] Se evita que `$ejecutar-tests` asuma automaticamente nivel, ventana y alcance cuando faltan datos, salvo confirmacion interactiva o defaults explicitados.
- [Basilio Lagares | Desarrollo backend] Se mantiene separada la evidencia generada (smoke/logs/traces) de los cambios de codigo, para poder decidir despues que artefactos se versionan.
- [Basilio Lagares | Desarrollo backend] La documentacion retrospectiva se limita a hechos verificables y no rellena huecos no trazables de conversacion.

## 4. Problemas encontrados
- [Basilio Lagares | Desarrollo backend] No existian `docs/estado-tecnico-2026-04-28.md` ni `docs/registro-diario-2026-04-28.md` antes de esta ejecucion.
- [Basilio Lagares | Desarrollo backend] El estado actual del repositorio muestra muchas eliminaciones en `evaluador_prueba` y un directorio `evaluador` nuevo sin seguimiento, lo que requiere clasificacion antes de cerrar la migracion.
- [Basilio Lagares | Desarrollo backend] Se detectan numerosos artefactos de salida generados el 2026-04-28 que pueden inflar el working tree si no se decide politica de versionado/limpieza.

## 5. Soluciones aplicadas
- [Basilio Lagares | Desarrollo backend] Se reconstruyo la jornada con `git log`, `git status`, diffs locales, ficheros modificados por fecha y reportes JSON existentes.
- [Basilio Lagares | Desarrollo backend] Se separo en la validacion lo que se ejecuta ahora de lo que ya existia como evidencia del dia.
- [Basilio Lagares | Desarrollo backend] Se documentaron riesgos de migracion `evaluador_prueba` -> `evaluador` sin revertir ni limpiar cambios del usuario.

## 6. Pendientes
- [Basilio Lagares | Desarrollo backend] Confirmar si `evaluador` sustituye definitivamente a `evaluador_prueba`.
- [Basilio Lagares | Desarrollo backend] Revisar que `/evaluador/evaluador_aneca_*` funciona desde el entorno web real.
- [Basilio Lagares | Desarrollo backend] Clasificar salidas smoke/logs/traces del 2026-04-28 como evidencia a conservar o artefactos temporales.
- [Basilio Lagares | Desarrollo backend] Validar la nueva interfaz de `$ejecutar-tests` en modo interactivo y no interactivo.

## 7. Siguiente paso
- [Basilio Lagares | Desarrollo backend] Hacer cierre tecnico de la migracion `evaluador_prueba` -> `evaluador`, revisando rutas, despliegue, versionado y compatibilidad.
- [Basilio Lagares | Desarrollo backend] Ejecutar una pasada dirigida de `$ejecutar-tests` cuando se quiera actualizar la evidencia de validacion desde esta documentacion.

## 8. Validacion realizada
- [Basilio Lagares | Desarrollo backend] No se han realizado tests en esta ejecucion.
- [Basilio Lagares | Desarrollo backend] Evidencia existente del 2026-04-28: `acelerador_panel/backend/tests/results/aggressive_battery_report.json` registra `finalStatus=PASS`, 747843 operaciones, 9349616 aserciones, 2991 invariant checks, 0 errores inesperados y 0 aserciones fallidas.
- [Basilio Lagares | Desarrollo backend] Evidencia existente del 2026-04-28: salidas smoke de cache, contratos y adaptador ANECA canonico generadas bajo `meritos/scraping/output/`.

## Firma
Registro elaborado por Basilio Lagares como constancia del trabajo tecnico realizado durante la fecha indicada.
