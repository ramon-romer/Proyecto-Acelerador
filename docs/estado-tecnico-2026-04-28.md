# Estado tecnico del dia

## Cabecera
PROYECTO: Acelerador
DOCUMENTO: Estado tecnico del dia
FECHA: 2026-04-28
AUTOR: Basilio Lagares
ROL: Desarrollo backend
ESTADO: En progreso

## 1. Resumen tecnico de la jornada
- [Basilio Lagares | Desarrollo backend] Se reconstruyo la documentacion del dia 2026-04-28 a partir de evidencia real del repositorio, git log, git status, salidas smoke y reportes de validacion existentes.
- [Basilio Lagares | Desarrollo backend] Se integro actividad de la rama `Desarrollo` asociada al commit `6b850fb` (`ACC-DES-RAMA-CSYJ`) y merge `8f05c49`, ambos del 2026-04-28.
- [Basilio Lagares | Desarrollo backend] Se avanzo en la rama/flujo CSYJ del evaluador ANECA, con extractor especifico CVN, procesamiento CSYJ y multiples salidas PDF/TXT/JSON de prueba del dia.
- [Basilio Lagares | Desarrollo backend] Se generaron evidencias smoke de cache, contratos JSON y adaptador canonico ANECA bajo `meritos/scraping/output/`.
- [Basilio Lagares | Desarrollo backend] Se ajusto el enlace del panel de profesor para apuntar al directorio `evaluador` en lugar de `evaluador_prueba`.
- [Basilio Lagares | Desarrollo backend] Se amplio la skill `ejecutar-tests` hacia modo interactivo real, alcance explicito, salida legible/JSON y configuracion de fase intensiva.

## 2. Modulos o areas afectadas
- [Basilio Lagares | Desarrollo backend] `evaluador_prueba/evaluador_aneca_csyj/`: flujo CSYJ, formularios, procesado de PDF y nuevo procesador CVN especifico.
- [Basilio Lagares | Desarrollo backend] `evaluador_prueba/src/`: cambios en `AnecaExtractorCsyj.php` y alta de `FecytCvnExtractorCsyj.php`.
- [Basilio Lagares | Desarrollo backend] `evaluador/`: aparece como nuevo arbol de evaluador en el working tree, en paralelo a la retirada pendiente de `evaluador_prueba`.
- [Basilio Lagares | Desarrollo backend] `meritos/scraping/output/`: evidencias de smoke de cache, contratos y adaptador ANECA canonico generadas el 2026-04-28.
- [Basilio Lagares | Desarrollo backend] `acelerador_panel/fronten/panel_profesor.php`: rutas frontend hacia evaluadores por perfil.
- [Basilio Lagares | Desarrollo backend] `.agents/skills/ejecutar-tests/` y `.agents/skills/generar-documentacion/`: ajustes de operativa de tests y compatibilidad con documentacion diaria.

## 3. Cambios realizados
- [Basilio Lagares | Desarrollo backend] Commit detectado `6b850fb`: modificaciones en `evaluador_prueba/evaluador_aneca_csyj/funciones_evaluador_csyj.php`, `index.php` y `procesar_pdf.php`.
- [Basilio Lagares | Desarrollo backend] Commit detectado `6b850fb`: alta de `evaluador_prueba/evaluador_aneca_csyj/procesar_pdf_csyj_cvn.php`.
- [Basilio Lagares | Desarrollo backend] Commit detectado `6b850fb`: alta de `evaluador_prueba/src/FecytCvnExtractorCsyj.php` y cambios en `AnecaExtractorCsyj.php`.
- [Basilio Lagares | Desarrollo backend] Commit detectado `6b850fb`: generacion de salidas CSYJ del 2026-04-28 en `evaluador_prueba/output/json`, `evaluador_prueba/output/txt` y `evaluador_prueba/storage/pdfs`.
- [Basilio Lagares | Desarrollo backend] Se registran 3 tandas de smoke de cache (`smoke_cache_20260428_*`), 4 de contratos (`contracts_20260428_*`) y 3 de adaptador ANECA (`aneca_adapter_20260428_*`).
- [Basilio Lagares | Desarrollo backend] Se registran 18 JSON de `cv_cache_smoke`, 8 JSON de `contracts_sample` y 6 JSON de `adapter_sample` con fecha 20260428.
- [Basilio Lagares | Desarrollo backend] En `panel_profesor.php` se cambiaron rutas `/evaluador_prueba/...` por `/evaluador/...` para CSYJ, experimentales, humanidades, salud y tecnica.
- [Basilio Lagares | Desarrollo backend] En `$ejecutar-tests` se incorporaron flags `--interactive`, `--scope`, `--output`, `--both`, `--intensiva`, `--fase-intensiva` y `--yes`, ademas de normalizacion de entradas y mini-acta tecnica.
- [Basilio Lagares | Desarrollo backend] En `$generar-documentacion` se ajusto la llamada a `$ejecutar-tests` para pasar `--scope=toda-app` e `--intensiva=auto` cuando se ejecuten pruebas desde la documentacion.

## 4. Impacto en arquitectura o integracion
- [Basilio Lagares | Desarrollo backend] La migracion de rutas hacia `evaluador` reduce dependencia visible de `evaluador_prueba`, pero deja pendiente revisar que el despliegue y el servidor sirvan el nuevo arbol de forma consistente.
- [Basilio Lagares | Desarrollo backend] La especializacion CSYJ introduce un extractor CVN propio y evidencia de procesamiento por rama, reforzando la transicion desde evaluador generico hacia perfiles ANECA diferenciados.
- [Basilio Lagares | Desarrollo backend] Las evidencias smoke de cache/contratos/adaptador sostienen la trazabilidad del contrato canonico ANECA y de la compatibilidad con el pipeline de meritos.
- [Basilio Lagares | Desarrollo backend] La skill `ejecutar-tests` pasa de defaults implicitos a configuracion explicita, mejorando seguridad operativa para validaciones de alcance amplio.

## 5. Dependencias relevantes
- [Basilio Lagares | Desarrollo backend] Dependencia funcional entre `panel_profesor.php` y la existencia real del directorio publico `/evaluador/...`.
- [Basilio Lagares | Desarrollo backend] Dependencia de compatibilidad entre salidas ANECA canonicas (`*.aneca.canonico.json`) y schemas/documentacion de contratos JSON.
- [Basilio Lagares | Desarrollo backend] Dependencia entre `$generar-documentacion` y `$ejecutar-tests`: la ejecucion automatica de tests necesita alcance explicito (`toda-app`) tras los cambios de la skill.

## 6. Riesgos y pendientes
- [Basilio Lagares | Desarrollo backend] El working tree muestra muchas eliminaciones pendientes en `evaluador_prueba` y un arbol nuevo `evaluador`; conviene confirmar si es migracion definitiva antes de limpiar o versionar.
- [Basilio Lagares | Desarrollo backend] Hay salidas generadas de smoke y procesamiento con fecha 20260428 que conviene clasificar como evidencia versionable o artefacto temporal.
- [Basilio Lagares | Desarrollo backend] La documentacion se genero al dia siguiente; puede faltar contexto conversacional no reflejado en git, por lo que se ha limitado a evidencia verificable.
- [Basilio Lagares | Desarrollo backend] Verificar que las rutas nuevas del panel no rompen entornos que aun dependan de `/evaluador_prueba`.

## 7. Proximos pasos
- [Basilio Lagares | Desarrollo backend] Decidir si `evaluador_prueba` queda reemplazado por `evaluador` y ajustar versionado/despliegue en consecuencia.
- [Basilio Lagares | Desarrollo backend] Revisar y consolidar las salidas smoke de `meritos/scraping/output/` para separar evidencia util de artefactos descartables.
- [Basilio Lagares | Desarrollo backend] Ejecutar una validacion dirigida de rutas del panel profesor contra `/evaluador/...`.
- [Basilio Lagares | Desarrollo backend] Validar la nueva interfaz de `$ejecutar-tests` en modo no interactivo y desde `$generar-documentacion`.

## 8. Validacion y pruebas ejecutadas
- [Basilio Lagares | Desarrollo backend] No se han realizado tests en esta ejecucion.
- [Basilio Lagares | Desarrollo backend] Evidencia existente del 2026-04-28: `acelerador_panel/backend/tests/results/aggressive_battery_report.json` registra `finalStatus=PASS`, 747843 operaciones, 9349616 aserciones, 2991 invariant checks, 0 errores inesperados y 0 aserciones fallidas entre 13:05:09 y 13:05:39 +02:00.
- [Basilio Lagares | Desarrollo backend] Evidencias existentes del 2026-04-28: tandas smoke `smoke_cache_20260428_*`, `contracts_20260428_*` y `aneca_adapter_20260428_*` con salidas JSON/TXT/logs/traces bajo `meritos/scraping/output/`.

## Firma
Documento elaborado por Basilio Lagares como reflejo del estado tecnico real del trabajo realizado en la fecha indicada.
