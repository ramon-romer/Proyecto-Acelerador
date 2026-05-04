# Estado tecnico del dia

## Cabecera
PROYECTO: Acelerador
DOCUMENTO: Estado tecnico del dia
FECHA: 2026-05-04
AUTOR: Basilio Lagares
ROL: Desarrollo backend
ESTADO: Diagnostico y estabilizacion de ruta canonica `evaluador/` (sin tocar MCP)

## 1. Resumen tecnico de la jornada
- [Basilio Lagares | Desarrollo backend] Se realizo diagnostico completo de duplicidad `evaluador/` vs `evaluador_prueba/`, confirmando divergencia de arboles y necesidad de consolidacion incremental sobre `evaluador/`.
- [Basilio Lagares | Desarrollo backend] Se aplico sincronizacion minima en CSYJ dentro de `evaluador/` para recuperar funcionalidad sin borrar nada: selector de formato CV, flujo CVN y extractor especifico faltante.
- [Basilio Lagares | Desarrollo backend] Se desbloqueo el runtime local de BD para evaluador, creando esquemas necesarios y aplicando migracion de `orcid_candidato` en las 5 areas.
- [Basilio Lagares | Desarrollo backend] Se ejecuto bateria de tests solicitada por el usuario con resultado global en verde (`3 passed, 0 failed`).

## 2. Modulos o areas afectadas
- [Basilio Lagares | Desarrollo backend] `evaluador/evaluador_aneca_csyj/index.php`.
- [Basilio Lagares | Desarrollo backend] `evaluador/evaluador_aneca_csyj/procesar_pdf.php`.
- [Basilio Lagares | Desarrollo backend] `evaluador/evaluador_aneca_csyj/procesar_pdf_csyj_cvn.php` (alta en ruta canonica).
- [Basilio Lagares | Desarrollo backend] `evaluador/src/FecytCvnExtractorCsyj.php` (alta en ruta canonica).
- [Basilio Lagares | Desarrollo backend] `evaluador/migrations/20260429_add_orcid_candidato_evaluaciones.sql` (ejecutada en entorno local).

## 3. Cambios realizados
- [Basilio Lagares | Desarrollo backend] En CSYJ se restauro envio de `formato_cv` (`aneca` y `cvn_fecyt`) para evitar perdida de bifurcacion funcional al subir PDF.
- [Basilio Lagares | Desarrollo backend] En `procesar_pdf.php` se restablecio la seleccion explicita de extractor y su inyeccion en `Pipeline`, evitando fallback involuntario al extractor generico.
- [Basilio Lagares | Desarrollo backend] Se incorporaron a `evaluador/` los dos archivos que faltaban frente a `evaluador_prueba/`: `procesar_pdf_csyj_cvn.php` y `FecytCvnExtractorCsyj.php`.
- [Basilio Lagares | Desarrollo backend] Se detecto incoherencia de esquema en CSYJ (`schema.sql` crea `evaluador_aneca` mientras runtime apunta a `evaluador_aneca_csyj`) y se resolvio operativamente en local creando `evaluador_aneca_csyj` para desbloquear pruebas.
- [Basilio Lagares | Desarrollo backend] Se aplico migracion de trazabilidad ORCID y se verifico columna + indice en `evaluador_aneca_csyj`, `evaluador_aneca_experimentales`, `evaluador_aneca_humanidades`, `evaluador_aneca_salud`, `evaluador_aneca_tecnicas`.

## 4. Impacto en arquitectura o integracion
- [Basilio Lagares | Desarrollo backend] Se refuerza la ruta canonica `evaluador/` como base funcional real sin eliminar `evaluador_prueba/`.
- [Basilio Lagares | Desarrollo backend] Se evita ruptura de flujo CSYJ al mantener compatibilidad con formato CVN dentro de la ruta canonica.
- [Basilio Lagares | Desarrollo backend] No se tocaron componentes MCP ni contratos de su servidor en esta intervencion.

## 5. Dependencias relevantes
- [Basilio Lagares | Desarrollo backend] MySQL local detectado en `C:\xampp\mysql\bin\mysql.exe`.
- [Basilio Lagares | Desarrollo backend] PHP CLI `8.2.12`.
- [Basilio Lagares | Desarrollo backend] Scripts de validacion: `evaluador/tests/validate_canonical_schema.php` y skill `$ejecutar-tests`.

## 6. Riesgos y pendientes
- [Basilio Lagares | Desarrollo backend] Persisten diferencias de negocio entre `evaluador/` y `evaluador_prueba/` fuera del ajuste minimo CSYJ (por ejemplo funciones de puntuacion y otros modulos).
- [Basilio Lagares | Desarrollo backend] El `schema.sql` de CSYJ mantiene incoherencia de nombre de base y requiere correccion en codigo fuente para evitar dependencia de preparacion manual del entorno.
- [Basilio Lagares | Desarrollo backend] Existen artefactos/documentacion legacy que siguen mencionando `evaluador_prueba`, con riesgo de confusion operativa del equipo.

## 7. Proximos pasos
- [Basilio Lagares | Desarrollo backend] Corregir `schema.sql` de CSYJ para crear/usar `evaluador_aneca_csyj` de forma consistente con runtime.
- [Basilio Lagares | Desarrollo backend] Ejecutar siguiente fase de convergencia `evaluador_prueba` -> `evaluador` por modulos, sin perdida de funcionalidad y con validacion incremental.
- [Basilio Lagares | Desarrollo backend] Definir plan de retirada o congelacion formal de `evaluador_prueba/` una vez cubierta la paridad funcional en `evaluador/`.

## 8. Validacion y pruebas ejecutadas
- [Basilio Lagares | Desarrollo backend] `php .agents/skills/ejecutar-tests/scripts/ejecutar_tests.php --json --nivel=basico --ventana=30m --scope=toda-app`.
- [Basilio Lagares | Desarrollo backend] Resultado suite: `executed=true`, `total=3`, `passed=3`, `failed=0`, `noVerificable=0`, `suiteName=ejecutar-tests:standard-30m`.
- [Basilio Lagares | Desarrollo backend] Checks en verde: `php-version`, `backend-smoke`, `mcp-unit`.
- [Basilio Lagares | Desarrollo backend] Validaciones adicionales de esta sesion: lint completo de `evaluador/` sin errores y validacion de esquema canonico `132/132 PASS` en `evaluador/output/json`.

## Firma
Documento elaborado por Basilio Lagares como reflejo del estado tecnico real del trabajo realizado en la fecha indicada.
