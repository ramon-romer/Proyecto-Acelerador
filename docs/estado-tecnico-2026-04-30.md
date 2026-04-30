# Estado tecnico del dia

## Cabecera
PROYECTO: Acelerador
DOCUMENTO: Estado tecnico del dia
FECHA: 2026-04-30
AUTOR: Basilio Lagares
ROL: Desarrollo backend
ESTADO: Implementacion y validacion tecnica (ANECA Tecnicas + entorno BD)

## 1. Resumen tecnico de la jornada
- [Basilio Lagares | Desarrollo backend] Se retiro cualquier intento de centralizacion en `evaluador/src` y se mantuvo la implementacion de 1A exclusivamente en el evaluador de Tecnicas, segun directriz del equipo.
- [Basilio Lagares | Desarrollo backend] Se adapto el calculo `1A` de Tecnicas a baremo fijo con tope 32 y reglas de tercil por ranking/calidad/cuartil, aplicando filtros de validez y exclusion funcional.
- [Basilio Lagares | Desarrollo backend] Se ejecuto validacion agresiva repetida de 5 minutos con `$ejecutar-tests`, incluyendo bateria intensiva backend y bucle MCP sin fallos funcionales.
- [Basilio Lagares | Desarrollo backend] Se diagnostico y resolvio el error `Unknown database 'acelerador'` ajustando el fallback de BD del backend a la base activa del entorno (`acelerador_staging_20260406`).

## 2. Modulos o areas afectadas
- [Basilio Lagares | Desarrollo backend] `evaluador/evaluador_aneca_tecnicas/funciones_evaluador_tecnicas.php` (logica 1A de publicaciones).
- [Basilio Lagares | Desarrollo backend] `acelerador_panel/backend/config/database.php` (fallback de nombre de base de datos en runtime).
- [Basilio Lagares | Desarrollo backend] Herramientas de validacion: `.agents/skills/ejecutar-tests/scripts/ejecutar_tests.php` y `acelerador_panel/backend/tools/inspect_schema.php`.

## 3. Cambios realizados
- [Basilio Lagares | Desarrollo backend] Eliminado cualquier cambio previo en `evaluador/src` relacionado con `AnecaBaremoPublicaciones` para cumplir la restriccion de no tocar `src`.
- [Basilio Lagares | Desarrollo backend] Implementada/ajustada en Tecnicas la funcion `calcular_1a_tecnicas(array $publicaciones): float` con tope `32.0` y puntuacion `T1=32/6`, `T2=32/8`, `T3=32/8`.
- [Basilio Lagares | Desarrollo backend] Reforzada la logica local de item 1A (`tec_puntuar_item_1a`) con filtros: `es_valida/es_valido`, `es_divulgacion`, `es_docencia`, `es_acta_congreso`, `es_informe_proyecto`, y solo indices `JCR/SCOPUS/SJR`.
- [Basilio Lagares | Desarrollo backend] Incorporada resolucion de tercil con prioridad: `tercil` directo -> `ranking_posicion/ranking_total` -> `calidad_posicion` (`x/y`) -> fallback `cuartil`.
- [Basilio Lagares | Desarrollo backend] Ajustado `acelerador_panel/backend/config/database.php` para incluir `acelerador_staging_20260406` como candidato por defecto cuando no se define `DB_NAME`.

## 4. Impacto en arquitectura o integracion
- [Basilio Lagares | Desarrollo backend] Se preserva la directriz de separacion: `evaluador/src` permanece intacto y la logica de rama Tecnicas queda encapsulada en su modulo especifico.
- [Basilio Lagares | Desarrollo backend] El backend de panel reduce fragilidad de entorno al poder resolver la BD activa sin export manual de variables en pruebas operativas.
- [Basilio Lagares | Desarrollo backend] No hay cambios en base de datos, rutas publicas nuevas ni pantallas legacy; el impacto es de logica de negocio y configuracion de conexion.

## 5. Dependencias relevantes
- [Basilio Lagares | Desarrollo backend] Entorno MySQL local (`localhost:3306`) con base activa detectada `acelerador_staging_20260406`.
- [Basilio Lagares | Desarrollo backend] PHP CLI (`8.2.12`) para ejecucion de lint y baterias de validacion.
- [Basilio Lagares | Desarrollo backend] Skill interna `$ejecutar-tests` para verificacion agresiva temporal.

## 6. Riesgos y pendientes
- [Basilio Lagares | Desarrollo backend] El fallback actual de BD referencia un nombre de staging concreto; si cambia el nombre real de la instancia habra que actualizar entorno (`DB_NAME`) o fallback.
- [Basilio Lagares | Desarrollo backend] En `inspect_schema`, el mapeo `tutoria.descripcion -> descripcion` aparece `MISSING` en la tabla fisica actual; requiere decision de contrato/esquema.
- [Basilio Lagares | Desarrollo backend] No hay bateria especifica ANECA/evaluador disponible en la skill actual; las corridas agresivas se redistribuyen a backend/MCP.

## 7. Proximos pasos
- [Basilio Lagares | Desarrollo backend] Acordar con el equipo si el nombre canonico de BD en este entorno debe fijarse por variable (`DB_NAME`) para evitar depender de fallback.
- [Basilio Lagares | Desarrollo backend] Revisar el desajuste de columna `descripcion` en dominio tutoria y decidir si se corrige mapping o esquema.
- [Basilio Lagares | Desarrollo backend] Mantener seguimiento del bloque ANECA/evaluador para incorporar checks dedicados en `$ejecutar-tests` cuando se habiliten.

## 8. Validacion y pruebas ejecutadas
- [Basilio Lagares | Desarrollo backend] En esta ejecucion documental no se han lanzado tests nuevos por decision explicita (`n`).
- [Basilio Lagares | Desarrollo backend] Ultima validacion disponible del dia: `php .agents/skills/ejecutar-tests/scripts/ejecutar_tests.php --nivel=agresivo --ventana=5m --scope=toda-app --output=both --fase-intensiva=si` con resultado global `7 passed, 0 failed, 0 no_verificable` y duracion aproximada `301s`.
- [Basilio Lagares | Desarrollo backend] Validaciones puntuales adicionales del dia: `php -l evaluador/evaluador_aneca_tecnicas/funciones_evaluador_tecnicas.php`, `php -l acelerador_panel/backend/config/database.php` y `php acelerador_panel/backend/tools/inspect_schema.php`, todas en estado correcto tras ajuste de BD.

## Firma
Documento elaborado por Basilio Lagares como reflejo del estado tecnico real del trabajo realizado en la fecha indicada.
