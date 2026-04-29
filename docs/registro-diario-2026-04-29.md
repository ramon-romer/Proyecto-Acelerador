# Registro diario de trabajo

## Cabecera
PROYECTO: Acelerador
DOCUMENTO: Registro diario
FECHA: 2026-04-29
AUTOR: Basilio Lagares
ROL: Desarrollador backend
ESTADO: Cierre pre-MVP documental

## 1. Resumen del dia
- [Basilio Lagares | Desarrollador backend] Se documento el cierre pre-MVP del Proyecto Acelerador para dejar una foto final clara del estado actual.
- [Basilio Lagares | Desarrollador backend] La jornada se centro en documentacion y coordinacion tecnica, sin cambios funcionales.
- [Basilio Lagares | Desarrollador backend] Se dejo constancia de pendientes operativos antes de MVP: migracion ORCID runtime, suite MVP corta y validacion OCR Docker/CI si aplica.

## 2. Trabajo realizado
- [Basilio Lagares | Desarrollador backend] Creacion/consolidacion del cierre pre-MVP en `docs/cierre-pre-mvp-2026-04-29.md`.
- [Basilio Lagares | Desarrollador backend] Enlace del cierre desde `README.md`.
- [Basilio Lagares | Desarrollador backend] Registro del cierre en `docs/control-versiones-estado-tecnico-mvp.md` como version documental `v0.3-cierre-pre-mvp`.
- [Basilio Lagares | Desarrollador backend] Creacion de la documentacion diaria de estado y registro del 2026-04-29.

## 3. Decisiones tecnicas
- [Basilio Lagares | Desarrollador backend] No introducir mas cambios funcionales antes del cierre pre-MVP.
- [Basilio Lagares | Desarrollador backend] Mantener `evaluador/` como ruta canonica actual.
- [Basilio Lagares | Desarrollador backend] Mantener `evaluador_prueba/` como legacy/documental/artefacto.
- [Basilio Lagares | Desarrollador backend] Dejar MCP-first fuera del MVP inmediato y retomarlo post-MVP como capa/orquestador final con fallback local explicito.
- [Basilio Lagares | Desarrollador backend] Tratar `ocr_ready=false` local como condicion de entorno, no como bug de codigo.

## 4. Problemas encontrados
- [Basilio Lagares | Desarrollador backend] La migracion ORCID esta creada pero aun pendiente de aplicar/verificar en BD runtime.
- [Basilio Lagares | Desarrollador backend] Docker/OCR queda pendiente de validar cuando Docker este disponible.
- [Basilio Lagares | Desarrollador backend] La suite MVP corta queda documentada como pendiente de ejecucion completa en entorno objetivo.

## 5. Soluciones aplicadas
- [Basilio Lagares | Desarrollador backend] Se genero documentacion de cierre con checklist explicita para ORCID, OCR Docker, MCP-first, riesgos residuales y entrega.
- [Basilio Lagares | Desarrollador backend] Se separo evidencia confirmada de comandos recomendados: solo se declara confirmado `PASS 131/131` del contrato ANECA.
- [Basilio Lagares | Desarrollador backend] Se dejaron pendientes operativos identificados para que el equipo pueda ejecutar el cierre de MVP sin ambiguedad.

## 6. Pendientes
- [Basilio Lagares | Desarrollador backend] Aplicar/verificar `evaluador/migrations/20260429_add_orcid_candidato_evaluaciones.sql` en BD runtime.
- [Basilio Lagares | Desarrollador backend] Confirmar columna e indice `orcid_candidato`.
- [Basilio Lagares | Desarrollador backend] Guardar evaluacion nueva y comprobar lectura por ORCID desde `panel_profesor.php`.
- [Basilio Lagares | Desarrollador backend] Ejecutar suite MVP corta en entorno objetivo.
- [Basilio Lagares | Desarrollador backend] Ejecutar `docker compose run --rm acelerador-php php meritos/scraping/tools/check_ocr_environment.php` si demo/release incluyen PDFs escaneados.

## 7. Siguiente paso
- [Basilio Lagares | Desarrollador backend] Cerrar pendientes operativos de ORCID runtime y suite MVP corta antes de entrega.
- [Basilio Lagares | Desarrollador backend] Preparar demo sin depender de MCP-first y con estado OCR explicitado segun entorno.

## 8. Validacion realizada
- [Basilio Lagares | Desarrollador backend] No se han realizado tests en esta ejecucion.
- [Basilio Lagares | Desarrollador backend] Validacion previa documentada: `php evaluador/tests/validate_canonical_schema.php` con resultado `PASS 131/131`.
- [Basilio Lagares | Desarrollador backend] No se generaron artefactos nuevos de tests en esta ejecucion documental.

## Firma
Registro elaborado por Basilio Lagares como constancia del trabajo tecnico realizado durante la fecha indicada.
