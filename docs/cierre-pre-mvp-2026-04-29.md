# Cierre pre-MVP - Proyecto Acelerador

Fecha de corte: 2026-04-29

## 1. Estado general pre-MVP

Estado global: casi listo para MVP operativo, sin bloqueantes tecnicos de codigo conocidos dentro del alcance actual.

La ruta canonica actual queda fijada en `evaluador/`. El directorio `evaluador_prueba/` queda como legacy, documental o artefacto de trabajo, no como ruta canonica de entrega.

El contrato ANECA esta validado con:

```bash
php evaluador/tests/validate_canonical_schema.php
```

Resultado confirmado: `PASS 131/131`.

MCP-first queda fuera de alcance del MVP inmediato. La entrega pre-MVP se apoya en el pipeline estable actual, con MCP-first pospuesto para fase posterior.

## 2. Que queda cerrado

- Ruta canonica actual consolidada en `evaluador/`.
- `evaluador_prueba/` clasificado como legacy/documental/artefacto.
- Contrato ANECA validado con `PASS 131/131`.
- Endpoints publicos de scraping sin exposicion de rutas internas.
- Admin legacy endurecido con CSRF y prepared statements.
- Trazabilidad candidato/evaluacion migrada a `orcid_candidato` en codigo y contrato operativo.
- Migracion ORCID creada en `evaluador/migrations/20260429_add_orcid_candidato_evaluaciones.sql`.
- OCR local con posible `ocr_ready=false` clasificado como condicion de entorno, no como bug de codigo.
- MCP-first explicitamente pospuesto para fase posterior.

## 3. Que queda pendiente antes de MVP

- Aplicar y verificar la migracion ORCID en la base de datos runtime.
- Confirmar existencia de columna `orcid_candidato`.
- Confirmar existencia del indice asociado a `orcid_candidato`.
- Guardar una evaluacion nueva y verificar que queda trazada por ORCID.
- Comprobar lectura por ORCID desde `panel_profesor.php`.
- Ejecutar suite MVP corta en entorno objetivo o equivalente.
- Confirmar que los endpoints publicos de scraping siguen sin exponer rutas internas tras despliegue.
- Confirmar que el flujo principal ANECA usa la ruta canonica `evaluador/`.
- Documentar el estado OCR real del entorno de entrega.

## 4. Que queda pendiente antes de demo

- Preparar una base de datos demo con la migracion ORCID aplicada.
- Usar un documento de prueba controlado y reproducible.
- Confirmar que una evaluacion nueva aparece trazada con `orcid_candidato`.
- Confirmar lectura por ORCID desde `panel_profesor.php`.
- Validar OCR en Docker/CI si la demo incluye PDFs escaneados.
- Tener preparado el mensaje operativo si el entorno local devuelve `ocr_ready=false`.
- Evitar presentar MCP-first como parte de la demo inmediata; dejarlo como roadmap post-MVP.

## 5. Que queda post-MVP

- Retomar MCP-first como capa/orquestador final.
- Mantener fallback local explicito cuando MCP-first se reactive.
- Instrumentar `provider_usado`, `fallback_activado` y `motivo_fallback`.
- Ampliar cobertura de tests de regresion y e2e.
- Evaluar retirada gradual de legacy cuando el flujo canonico este suficientemente probado.
- Mejorar observabilidad operativa y trazabilidad de errores.
- Consolidar documentacion tecnica estable despues de la primera entrega MVP.

## 6. Suite MVP corta

Comandos previstos para validacion corta pre-MVP:

```bash
php evaluador/tests/validate_canonical_schema.php
php mcp-server/tests/unit_extract_pdf.php
php acelerador_panel/backend/tests/run_usecases_smoke.php
php acelerador_panel/backend/tests/run_aggressive_battery.php --duration-seconds=30
php meritos/scraping/tools/smoke_jobs_queue.php
php meritos/scraping/tools/validate_scraping_technical_contracts.php
php meritos/scraping/tools/validate_aneca_canonical_adapter.php
php meritos/scraping/tools/check_ocr_environment.php
```

Estado documentado en este cierre:

- `php evaluador/tests/validate_canonical_schema.php`: ejecutado previamente y confirmado como `PASS 131/131`.
- Resto de comandos: incluidos como suite MVP corta recomendada; no se declaran ejecutados en este cierre documental.

Si se ejecutan estos comandos y generan artefactos, se debe registrar ruta, nombre de archivo y proposito antes de entrega.

## 7. Checklist de migracion ORCID

Migracion creada:

```text
evaluador/migrations/20260429_add_orcid_candidato_evaluaciones.sql
```

Pendiente operativo antes de MVP:

- [ ] Aplicar/verificar migracion en BD runtime.
- [ ] Confirmar columna `orcid_candidato`.
- [ ] Confirmar indice asociado a `orcid_candidato`.
- [ ] Guardar evaluacion nueva.
- [ ] Comprobar lectura por ORCID desde `panel_profesor.php`.
- [ ] Registrar entorno, fecha y resultado de la verificacion.

Criterio de cierre ORCID: migracion aplicada en runtime y trazabilidad comprobada con una evaluacion nueva consultable por ORCID.

## 8. Checklist OCR Docker

Comando de validacion Docker/OCR:

```bash
docker compose run --rm acelerador-php php meritos/scraping/tools/check_ocr_environment.php
```

Checklist:

- [ ] Confirmar disponibilidad de Docker en el entorno objetivo.
- [ ] Ejecutar el check OCR dentro del contenedor.
- [ ] Confirmar `ocr_ready=true` para demo/release con PDFs escaneados.
- [ ] Si local devuelve `ocr_ready=false`, clasificarlo como condicion de entorno, no bug de codigo.
- [ ] Validar OCR en Docker/CI antes de demo/release si entran PDFs escaneados en alcance.
- [ ] Documentar resultado real del check OCR y entorno usado.

Nota operativa: `ocr_ready=false` en local no bloquea el codigo por si mismo. Para demo o release con PDFs escaneados, el entorno Docker/CI debe demostrar OCR disponible.

## 9. Nota MCP-first fuera de alcance inmediato

MCP-first queda fuera de alcance del MVP inmediato.

Se retomara post-MVP como capa/orquestador final, sin romper el pipeline estable actual. Cuando se reactive, debe incluir:

- `provider_usado`.
- `fallback_activado`.
- `motivo_fallback`.
- Fallback local explicito.
- Compatibilidad con el pipeline estable existente.

La reintroduccion de MCP-first no debe modificar contratos ni desplazar el flujo estable sin validacion especifica.

## 10. Riesgos residuales

- Migracion ORCID creada pero pendiente de aplicar/verificar en BD runtime.
- Posible diferencia entre entorno local y entorno Docker/CI para OCR.
- Suite MVP corta completa pendiente de ejecucion documentada en el entorno de entrega.
- `evaluador_prueba/` sigue existiendo como legacy/documental/artefacto y puede generar confusion si no se comunica que `evaluador/` es la ruta canonica.
- MCP-first puede generar expectativas de alcance si no se comunica claramente como post-MVP.
- Legacy permanece en el repositorio por compatibilidad; no debe interpretarse como ruta principal.

## 11. Checklist final antes de commit/entrega

- [ ] `git status --short` revisado.
- [ ] Confirmar que solo hay cambios documentales previstos.
- [ ] Confirmar que no se ha tocado codigo funcional.
- [ ] Confirmar que no se han modificado contratos.
- [ ] Confirmar que no se ha implementado MCP-first.
- [ ] Confirmar que no se ha borrado legacy.
- [ ] Confirmar que no se han borrado artefactos sin autorizacion.
- [ ] Confirmar que no se ha hecho `git add`.
- [ ] Confirmar que no se ha hecho commit.
- [ ] Registrar artefactos generados si se ejecutaron tests.
- [ ] Ejecutar o planificar suite MVP corta en entorno objetivo.
- [ ] Aplicar/verificar migracion ORCID en BD runtime.
- [ ] Validar OCR Docker/CI si la demo/release incluye PDFs escaneados.

## 12. Veredicto final

El cierre pre-MVP queda documentado como estable con pendientes operativos acotados.

No hay bloqueantes tecnicos de codigo conocidos dentro del alcance actual, dejando MCP-first fuera de alcance inmediato. El foco antes de MVP es operativo: migracion ORCID en runtime, suite MVP corta, confirmacion OCR segun entorno y preparacion de demo sin depender de MCP-first.
