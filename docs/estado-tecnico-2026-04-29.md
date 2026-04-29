# Estado tecnico del dia

## Cabecera
PROYECTO: Acelerador
DOCUMENTO: Estado tecnico del dia
FECHA: 2026-04-29
AUTOR: Basilio Lagares
ROL: Desarrollador backend
ESTADO: Cierre pre-MVP documental

## 1. Resumen tecnico de la jornada
- [Basilio Lagares | Desarrollador backend] Se consolido el cierre documental pre-MVP del Proyecto Acelerador con foco en estado actual, pendientes operativos y checklist de entrega.
- [Basilio Lagares | Desarrollador backend] Se dejo constancia de que no quedan bloqueantes tecnicos de codigo conocidos dentro del alcance inmediato, dejando MCP-first fuera del MVP.
- [Basilio Lagares | Desarrollador backend] Se fijo `evaluador/` como ruta canonica actual y `evaluador_prueba/` como legacy/documental/artefacto.
- [Basilio Lagares | Desarrollador backend] Se documento que el contrato ANECA esta validado con `php evaluador/tests/validate_canonical_schema.php` en estado `PASS 131/131`.
- [Basilio Lagares | Desarrollador backend] Se registro que la migracion ORCID existe en `evaluador/migrations/20260429_add_orcid_candidato_evaluaciones.sql`, pendiente de aplicar/verificar en BD runtime.

## 2. Modulos o areas afectadas
- [Basilio Lagares | Desarrollador backend] Documentacion tecnica en `docs/cierre-pre-mvp-2026-04-29.md`.
- [Basilio Lagares | Desarrollador backend] Indice documental principal mediante enlace desde `README.md`.
- [Basilio Lagares | Desarrollador backend] Control documental MVP en `docs/control-versiones-estado-tecnico-mvp.md`.
- [Basilio Lagares | Desarrollador backend] Documentacion diaria creada en `docs/estado-tecnico-2026-04-29.md` y `docs/registro-diario-2026-04-29.md`.

## 3. Cambios realizados
- [Basilio Lagares | Desarrollador backend] Se genero un documento de cierre pre-MVP con estado general, cerrado, pendiente antes de MVP, pendiente antes de demo, post-MVP, suite MVP corta, checklists ORCID/OCR Docker, nota MCP-first y riesgos residuales.
- [Basilio Lagares | Desarrollador backend] Se incluyo la suite MVP corta como lista de comandos recomendados, sin declararla ejecutada en esta documentacion diaria.
- [Basilio Lagares | Desarrollador backend] Se dejo marcada la validacion ANECA conocida: `PASS 131/131`.
- [Basilio Lagares | Desarrollador backend] Se documento que `ocr_ready=false` en local es condicion de entorno y no bug de codigo.
- [Basilio Lagares | Desarrollador backend] Se explicito que Docker/OCR debe validarse en Docker/CI si demo o release incluyen PDFs escaneados.
- [Basilio Lagares | Desarrollador backend] Se dejo MCP-first como post-MVP, a retomar como capa/orquestador final con `provider_usado`, `fallback_activado`, `motivo_fallback` y fallback local explicito.

## 4. Impacto en arquitectura o integracion
- [Basilio Lagares | Desarrollador backend] No se realizaron cambios funcionales ni refactorizaciones; el impacto es documental y de coordinacion tecnica.
- [Basilio Lagares | Desarrollador backend] El cierre reduce ambiguedad operativa antes de MVP al separar ruta canonica (`evaluador/`), legacy (`evaluador_prueba/`), pendientes runtime y alcance post-MVP.
- [Basilio Lagares | Desarrollador backend] La decision de dejar MCP-first fuera del MVP inmediato protege el pipeline estable actual y evita introducir cambios de orquestacion antes de entrega.

## 5. Dependencias relevantes
- [Basilio Lagares | Desarrollador backend] BD runtime para aplicar/verificar la migracion ORCID.
- [Basilio Lagares | Desarrollador backend] Docker/CI para validar OCR cuando haya PDFs escaneados en demo o release.
- [Basilio Lagares | Desarrollador backend] Suite MVP corta pendiente de ejecucion documentada en entorno objetivo.
- [Basilio Lagares | Desarrollador backend] Comunicacion al equipo para evitar confundir `evaluador_prueba/` con la ruta canonica actual.

## 6. Riesgos y pendientes
- [Basilio Lagares | Desarrollador backend] Migracion ORCID creada pero pendiente de aplicar/verificar en BD runtime.
- [Basilio Lagares | Desarrollador backend] Pendiente confirmar columna e indice `orcid_candidato`.
- [Basilio Lagares | Desarrollador backend] Pendiente guardar una evaluacion nueva y comprobar lectura por ORCID desde `panel_profesor.php`.
- [Basilio Lagares | Desarrollador backend] OCR local puede devolver `ocr_ready=false`; esto queda clasificado como entorno, no como bug de codigo.
- [Basilio Lagares | Desarrollador backend] MCP-first queda fuera de alcance inmediato y puede generar expectativa si no se comunica como post-MVP.

## 7. Proximos pasos
- [Basilio Lagares | Desarrollador backend] Aplicar/verificar la migracion ORCID en BD runtime.
- [Basilio Lagares | Desarrollador backend] Ejecutar suite MVP corta en entorno objetivo o equivalente.
- [Basilio Lagares | Desarrollador backend] Validar OCR en Docker/CI si la demo o release incluyen PDFs escaneados.
- [Basilio Lagares | Desarrollador backend] Revisar `git status --short` antes de commit/entrega y confirmar que solo hay cambios documentales previstos.

## 8. Validacion y pruebas ejecutadas
- [Basilio Lagares | Desarrollador backend] No se han realizado tests en esta ejecucion.
- [Basilio Lagares | Desarrollador backend] Validacion conocida previa registrada en el cierre: `php evaluador/tests/validate_canonical_schema.php` con resultado `PASS 131/131`.
- [Basilio Lagares | Desarrollador backend] No se generaron artefactos nuevos de tests en esta ejecucion documental.

## Firma
Documento elaborado por Basilio Lagares como reflejo del estado tecnico real del trabajo realizado en la fecha indicada.
