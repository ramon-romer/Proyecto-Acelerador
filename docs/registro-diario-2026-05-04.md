# Registro diario de trabajo

## Cabecera
PROYECTO: Acelerador
DOCUMENTO: Registro diario
FECHA: 2026-05-04
AUTOR: Basilio Lagares
ROL: Desarrollo backend
ESTADO: Diagnostico de duplicidad y recuperacion funcional minima en `evaluador/`

## 1. Resumen del dia
- [Basilio Lagares | Desarrollo backend] Se audito la situacion de duplicidad `evaluador/` y `evaluador_prueba/` para determinar ruta versionada, estado funcional y plan de convergencia.
- [Basilio Lagares | Desarrollo backend] Se ejecutaron ajustes minimos en CSYJ para que la ruta canonica `evaluador/` recupere flujo CVN y extractor asociado.
- [Basilio Lagares | Desarrollo backend] Se dejo operativo el entorno local de bases para evaluador y se verifico migracion ORCID en todas las areas.

## 2. Trabajo realizado
- [Basilio Lagares | Desarrollo backend] Ejecucion de comandos de diagnostico Git y diff estructural entre carpetas para cuantificar divergencia real.
- [Basilio Lagares | Desarrollo backend] Comparacion focalizada de archivos CSYJ (`index.php`, `procesar_pdf.php`) y deteccion de regresion por extractor por defecto.
- [Basilio Lagares | Desarrollo backend] Copia controlada a `evaluador/` de `procesar_pdf_csyj_cvn.php` y `src/FecytCvnExtractorCsyj.php` desde `evaluador_prueba/`.
- [Basilio Lagares | Desarrollo backend] Lint de archivos tocados y lint recursivo en todo `evaluador/` sin errores.
- [Basilio Lagares | Desarrollo backend] Preparacion de BD local: carga de `schema.sql` por areas, creacion de `evaluador_aneca_csyj` y ejecucion de migracion `20260429_add_orcid_candidato_evaluaciones.sql`.
- [Basilio Lagares | Desarrollo backend] Verificacion en `information_schema` de columna `orcid_candidato` e indice `idx_evaluaciones_orcid_candidato_fecha` en las 5 bases objetivo.

## 3. Decisiones tecnicas
- [Basilio Lagares | Desarrollo backend] No tocar MCP ni aplicar cambios destructivos durante la convergencia.
- [Basilio Lagares | Desarrollo backend] Priorizar correcciones minimas de funcionalidad en `evaluador/` antes de abordar sincronizacion extensa de modulos.
- [Basilio Lagares | Desarrollo backend] Mantener `evaluador_prueba/` intacto por ahora como referencia comparativa hasta cerrar paridad.

## 4. Problemas encontrados
- [Basilio Lagares | Desarrollo backend] `evaluador/` no tenia dos piezas CSYJ presentes en `evaluador_prueba/` (script CVN y extractor especifico).
- [Basilio Lagares | Desarrollo backend] Diferencia de contrato operativo en CSYJ: `config.php` usa `evaluador_aneca_csyj` pero `schema.sql` define `evaluador_aneca`.
- [Basilio Lagares | Desarrollo backend] Inicialmente no existian las bases `evaluador_aneca_*`, bloqueando migraciones y pruebas E2E.

## 5. Soluciones aplicadas
- [Basilio Lagares | Desarrollo backend] Sincronizacion puntual de archivos CSYJ faltantes en ruta canonica.
- [Basilio Lagares | Desarrollo backend] Restablecido el uso de `formato_cv` y la inyeccion de extractor en `Pipeline` para evitar comportamiento degradado.
- [Basilio Lagares | Desarrollo backend] Creacion operativa de esquemas locales y ejecucion correcta de migracion ORCID con comprobacion posterior.

## 6. Pendientes
- [Basilio Lagares | Desarrollo backend] Corregir en fuente la incoherencia `schema.sql` de CSYJ para alinear nombre de BD con runtime.
- [Basilio Lagares | Desarrollo backend] Continuar convergencia por areas para que `evaluador/` absorba diferencias utiles de `evaluador_prueba/` sin regresiones.
- [Basilio Lagares | Desarrollo backend] Limpiar ruido documental/artefactos que siguen apuntando a `evaluador_prueba` cuando se cierre oficialmente la migracion.

## 7. Siguiente paso
- [Basilio Lagares | Desarrollo backend] Ejecutar fase 2 de sincronizacion: revisar diferencias funcionales por modulo (Experimentales, Humanidades, Salud, Tecnicas), aplicar cambios en `evaluador/` y validar con smoke + contrato canonico.

## 8. Validacion realizada
- [Basilio Lagares | Desarrollo backend] Se ejecuto bateria solicitada por el usuario: `php .agents/skills/ejecutar-tests/scripts/ejecutar_tests.php --json --nivel=basico --ventana=30m --scope=toda-app`.
- [Basilio Lagares | Desarrollo backend] Resultado: `3 passed`, `0 failed`, `0 no_verificable`.
- [Basilio Lagares | Desarrollo backend] Checks aprobados: `php-version`, `backend-smoke`, `mcp-unit`.
- [Basilio Lagares | Desarrollo backend] Validacion complementaria: `evaluador/tests/validate_canonical_schema.php` con `132/132 PASS`.

## Firma
Registro elaborado por Basilio Lagares como constancia del trabajo tecnico realizado durante la fecha indicada.
