# Registro diario de trabajo

## Cabecera
PROYECTO: Acelerador
DOCUMENTO: Registro diario
FECHA: 2026-04-30
AUTOR: Basilio Lagares
ROL: Desarrollo backend
ESTADO: Ajuste Tecnicas 1A + estabilizacion de entorno de pruebas

## 1. Resumen del dia
- [Basilio Lagares | Desarrollo backend] Se rehizo el trabajo de 1A para cumplir la directriz de no tocar `evaluador/src` y concentrar cambios en el evaluador de Tecnicas.
- [Basilio Lagares | Desarrollo backend] Se ejecuto una bateria agresiva de 5 minutos varias veces para validar estabilidad tras cambios.
- [Basilio Lagares | Desarrollo backend] Se analizo el error de BD y se dejo apuntando por defecto a la base real del entorno para evitar falsos negativos en checks.

## 2. Trabajo realizado
- [Basilio Lagares | Desarrollo backend] Limpieza/reversion de cambios en `evaluador/src` (sin residuo de `Baremos/AnecaBaremoPublicaciones.php`).
- [Basilio Lagares | Desarrollo backend] Implementacion local en `evaluador/evaluador_aneca_tecnicas/funciones_evaluador_tecnicas.php` de la formula 1A de Tecnicas con tope 32, equivalencia T2/T3 y calculo de terciles por ratio/cuartil.
- [Basilio Lagares | Desarrollo backend] Verificacion manual de casos solicitados: `7 T1 + 2 T2 => 32.00` (tope), `10/56 => T1`, `36/79 => T2`, `70/79 => T3`.
- [Basilio Lagares | Desarrollo backend] Diagnostico de conexion MySQL para `inspect_schema` y deteccion de base disponible `acelerador_staging_20260406`.
- [Basilio Lagares | Desarrollo backend] Ajuste de fallback en `acelerador_panel/backend/config/database.php` para incluir la base de staging actual.

## 3. Decisiones tecnicas
- [Basilio Lagares | Desarrollo backend] Mantener fuera de `src` toda logica nueva del bloque 1A de Tecnicas.
- [Basilio Lagares | Desarrollo backend] Priorizar cambios minimos y localizados en archivos existentes del evaluador de Tecnicas.
- [Basilio Lagares | Desarrollo backend] Resolver el problema de BD de pruebas por configuracion runtime (fallback) para no depender de export manual de `DB_NAME`.

## 4. Problemas encontrados
- [Basilio Lagares | Desarrollo backend] `inspect_schema` fallaba con `Unknown database 'acelerador'` al no existir ese nombre en el servidor MySQL local.
- [Basilio Lagares | Desarrollo backend] La skill `$ejecutar-tests` no dispone actualmente de una bateria intensiva especifica ANECA/evaluador, por lo que redistribuye carga a backend/MCP.

## 5. Soluciones aplicadas
- [Basilio Lagares | Desarrollo backend] Reversion controlada de cambios fuera de alcance (`evaluador/src`) y reimplementacion en el modulo correcto de Tecnicas.
- [Basilio Lagares | Desarrollo backend] Ajuste del fallback de nombres de BD para alinear el runtime con la base existente en entorno (`acelerador_staging_20260406`).
- [Basilio Lagares | Desarrollo backend] Reejecucion de bateria agresiva completa sin variables manuales, confirmando que todos los checks quedan en verde.

## 6. Pendientes
- [Basilio Lagares | Desarrollo backend] Validar con el equipo si se desea fijar `DB_NAME` explicito por entorno y dejar el fallback solo como red de seguridad.
- [Basilio Lagares | Desarrollo backend] Revisar el estado `MISSING` de `tutoria.descripcion` detectado por `inspect_schema`.

## 7. Siguiente paso
- [Basilio Lagares | Desarrollo backend] Mantener seguimiento del evaluador de Tecnicas con casos reales de publicaciones para asegurar que la nueva logica 1A no introduce regresiones funcionales.
- [Basilio Lagares | Desarrollo backend] Proponer ampliacion de `$ejecutar-tests` con checks dedicados ANECA/evaluador para futuras corridas agresivas con cobertura directa del modulo.

## 8. Validacion realizada
- [Basilio Lagares | Desarrollo backend] En esta ejecucion de documentacion no se han ejecutado tests nuevos (respuesta del usuario: `n`).
- [Basilio Lagares | Desarrollo backend] Ultima validacion completa disponible hoy: `ejecutar-tests:agresivo-5m` con `7 passed`, `0 failed`, `0 no_verificable` y `~301s`.
- [Basilio Lagares | Desarrollo backend] Lint y comprobaciones del dia en estado correcto para archivos modificados y herramientas clave (`php -l` + `inspect_schema`).

## Firma
Registro elaborado por Basilio Lagares como constancia del trabajo tecnico realizado durante la fecha indicada.
