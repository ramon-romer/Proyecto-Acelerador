# Cierre tecnico de integracion MCP auxiliar para matching y evaluacion CV - 2026-05-06

## 1. Resumen ejecutivo

Estado del bloque: cerrado con observaciones no bloqueantes.

La integracion MCP auxiliar para matching de tutorias y evaluacion CV queda cerrada tecnicamente como apta para MVP con observaciones. La auditoria final concluye que no quedan riesgos bloqueantes abiertos: el baseline local sigue siendo la fuente de verdad, MCP opera como capa auxiliar best-effort y no decide puntuaciones finales.

El bloqueo tecnico detectado durante la validacion, provocado por la ampliacion de `ProfesorRepositoryInterface` con `listForMatching()`, fue corregido en los repositorios in-memory usados por las pruebas backend. Las validaciones principales vuelven a pasar.

Veredicto final: APTO CON OBSERVACIONES.

## 2. Alcance revisado

El cierre cubre dos frentes tecnicos:

- Matching de tutorias en backend, incluyendo endpoint de recomendaciones, baseline local, orquestacion MCP auxiliar y contrato de respuesta.
- Evaluacion CV en modo `auto`, incluyendo ejecucion local como resultado final y uso de MCP como auxiliar no vinculante.

Quedan fuera del alcance de este cierre:

- Frontend y pantallas existentes.
- Login.
- `mcp-server`.
- Baremos ANECA y evaluadores en `evaluador/src`.
- Refactors de arquitectura posteriores a MVP.
- Cambios documentales diarios acumulativos.

## 3. Decision arquitectonica

La decision consolidada para MVP es mantener MCP como capa auxiliar de matching, contraste y enriquecimiento contextual.

La arquitectura cerrada se basa en estas reglas:

- MCP esta implementado como capa auxiliar para el MVP, no como nucleo principal del sistema.
- El modo recomendado por defecto para la integracion auxiliar es `auto`.
- El baseline local es obligatorio.
- El resultado local es la fuente de verdad.
- MCP puede aportar senales auxiliares, advertencias o trazabilidad tecnica.
- MCP no sustituye repositorios, casos de uso ni evaluadores locales.
- MCP no modifica baremos ANECA.
- MCP no cambia contratos publicos existentes de evaluacion.
- Si MCP falla, no esta disponible o supera timeout en modo `auto`, el sistema continua en `local_only` y deja trazabilidad interna del fallback.
- Si el nucleo local falla, el flujo falla.
- MCP-first queda pospuesto para una fase posterior, fuera del cierre MVP.

## 4. Que hace MCP y que no hace

MCP si hace:

- Intenta enriquecer el matching como auxiliar best-effort.
- Contrasta disponibilidad de informacion adicional.
- Aporta advertencias o motivos auxiliares cuando procede.
- Permite diagnostico tecnico interno de disponibilidad MCP.
- En evaluacion CV `auto`, se ejecuta como intento auxiliar despues del resultado local.

MCP no hace:

- No evalua ANECA.
- No barema.
- No decide `score_final`.
- No sustituye `LocalEvaluationProvider`.
- No sustituye `ResearchGroupMatchingService`.
- No modifica resultados locales validos.
- No se convierte en punto unico de fallo para `auto`.

## 5. Contrato de `score_final`

El contrato de cierre para matching MVP es:

- `score_local` se calcula por baseline local.
- `score_mcp` puede existir solo como campo auxiliar o permanecer `null`.
- `score_final` queda anclado a `score_local`.
- Ninguna respuesta MCP puede elevar, rebajar o reemplazar `score_final`.

Regla operativa:

```text
score_final = score_local
```

Esto mantiene auditabilidad, evita decisiones opacas y separa recomendacion local de enriquecimiento auxiliar.

## 6. Comportamiento por modo

### `local_only`

- Ejecuta solo baseline local.
- No intenta MCP.
- Devuelve recomendaciones con contrato estable.
- Es la ultima defensa operativa y el modo mas simple de diagnostico local.

### `auto`

- Es el modo recomendado por defecto para el MVP auxiliar.
- Ejecuta baseline local obligatorio.
- Intenta MCP como auxiliar best-effort.
- Si MCP esta disponible, puede anadir motivos o advertencias auxiliares.
- Si MCP falla, no esta disponible o supera timeout, activa fallback local, continua como `local_only` y devuelve baseline.
- El fallback deja trazabilidad interna para auditoria y diagnostico.
- En evaluacion CV, devuelve el resultado local intacto aunque MCP falle.
- Si el flujo local falla, la operacion falla.

### `mcp`

- Fuerza intento MCP para diagnostico/control tecnico.
- Si MCP no esta disponible, devuelve error controlado `503 MCP_UNAVAILABLE`.
- No activa fallback silencioso local en este modo.
- No convierte MCP en autoridad de score ni evaluacion.

## 7. Validaciones ejecutadas

- `php acelerador_panel/backend/tools/smoke_tutoria_matching.php`: ejecutado y correcto. Resultado informado: OK 4/4.
- `php meritos/scraping/tools/smoke_evaluation_auto_auxiliary.php`: ejecutado y correcto. Resultado informado: OK 7/7.
- `php acelerador_panel/backend/tests/run_usecases_smoke.php`: ejecutado y correcto. Resultado informado: OK.
- `php acelerador_panel/backend/tests/run_aggressive_battery.php --duration-seconds=30`: ejecutado y correcto. Resultado informado: PASS, `unexpectedErrors=0`.

Estado global de validacion: ejecutado y correcto, con observacion no bloqueante por permisos al persistir el reporte de la bateria agresiva.

Observacion:

- Durante la bateria agresiva aparece un warning de permisos al escribir `acelerador_panel/backend/tests/results/aggressive_battery_report.json`.
- El warning no impide el resultado `PASS` ni incrementa `unexpectedErrors`.
- Debe tratarse como deuda operativa menor si se requiere conservar reportes automaticamente.

## 8. Bloqueo detectado y correccion aplicada

Bloqueo detectado:

- `ProfesorRepositoryInterface` fue ampliada con `listForMatching(int $limit, ?string $search = null): array`.
- Los repositorios in-memory usados por tests no implementaban el nuevo metodo.
- Como consecuencia, `run_usecases_smoke.php` y `run_aggressive_battery.php` fallaban por error fatal de contrato PHP.

Correccion aplicada:

- Se implemento `listForMatching()` en `InMemoryProfesorRepository` de `acelerador_panel/backend/tests/run_usecases_smoke.php`.
- Se implemento `listForMatching()` en `InMemoryProfesorRepository` de `acelerador_panel/backend/tests/run_aggressive_battery.php`.
- La implementacion in-memory es minima, determinista y alineada con el contrato: filtra por texto cuando se solicita, ordena de forma estable y aplica `limit`.

Conclusion:

- El bloqueo tecnico queda resuelto.
- La correccion no altera matching, MCP, baremos ANECA, frontend ni login.
- La correccion no maquilla las pruebas: adapta los dobles in-memory al contrato real exigido por la interfaz.

## 9. Riesgos bloqueantes y no bloqueantes

Riesgos bloqueantes:

- Ninguno detectado tras la correccion y revalidacion.

Riesgos no bloqueantes:

- Warning de permisos al escribir `acelerador_panel/backend/tests/results/aggressive_battery_report.json`.
- La trazabilidad de reportes de bateria puede quedar incompleta si el entorno no permite escritura en esa ruta.
- `mcp_only`/`mcp` sigue siendo un modo de diagnostico tecnico, no un modo productivo de decision final.
- La capa MCP auxiliar debe mantenerse desacoplada del frontend y de baremos ANECA en iteraciones futuras.

## 10. Veredicto final

Veredicto: APTO CON OBSERVACIONES.

La integracion MCP auxiliar queda tecnicamente cerrada para MVP porque:

- Mantiene el nucleo local como fuente de verdad.
- No introduce dependencia obligatoria de MCP.
- No altera baremos ni puntuaciones ANECA.
- No decide `score_final`.
- Tiene fallback local en `auto`.
- Tiene error controlado en modo `mcp` cuando MCP no esta disponible.
- El bloqueo de contrato en tests backend fue corregido y revalidado.

La unica observacion pendiente es operativa y no bloqueante: revisar permisos de escritura del reporte de bateria si se quiere conservar ese artefacto como evidencia automatica.

## 11. Checklist de no regresion

- [x] Baseline local obligatorio para matching.
- [x] `score_final` anclado a `score_local`.
- [x] MCP no decide puntuaciones.
- [x] MCP no modifica baremos ANECA.
- [x] MCP no sustituye evaluacion local.
- [x] `auto` continua con local si MCP falla.
- [x] Local falla implica fallo del flujo.
- [x] Modo `mcp` devuelve error controlado si MCP no esta disponible.
- [x] Endpoint matching conserva contrato estable.
- [x] Repositorios in-memory de tests cumplen `ProfesorRepositoryInterface`.
- [x] Smoke matching OK.
- [x] Smoke evaluacion auxiliar OK.
- [x] Smoke use cases backend OK.
- [x] Bateria agresiva 30s PASS sin errores inesperados.
- [x] Sin cambios en frontend.
- [x] Sin cambios en login.
- [x] Sin cambios en `mcp-server`.
- [x] Sin cambios en baremos ANECA.

## 12. Texto breve para Notion

Cerrada la integracion MCP auxiliar para matching de tutorias y evaluacion CV con veredicto APTO CON OBSERVACIONES. El baseline local sigue siendo la fuente de verdad, MCP queda como auxiliar best-effort, no decide `score_final` ni modifica baremos ANECA. El bloqueo de tests por `listForMatching()` en repositorios in-memory fue corregido y las validaciones principales pasan. Observacion no bloqueante: warning de permisos al escribir el reporte de bateria agresiva.

## 13. Texto tecnico ampliado para documentacion

La integracion MCP auxiliar queda consolidada para MVP como una capa de enriquecimiento y diagnostico, no como nucleo de decision. En matching de tutorias, el flujo local construye el baseline y mantiene `score_final = score_local`; MCP puede aportar contexto auxiliar, pero no modifica ranking final ni sustituye el calculo local. En evaluacion CV, el modo `auto` devuelve el resultado local intacto y ejecuta MCP como intento auxiliar best-effort. Si MCP falla, el flujo local continua; si local falla, la evaluacion falla.

La incidencia bloqueante detectada en validacion no estaba en la arquitectura MCP, sino en los dobles de prueba: `ProfesorRepositoryInterface` habia incorporado `listForMatching()` y los repositorios in-memory de los runners backend no lo implementaban. La correccion consistio en adaptar esos dobles al contrato actual con una implementacion minima y determinista. Tras la correccion, los smokes de matching, evaluacion auxiliar, use cases backend y bateria agresiva de 30 segundos quedan en verde.

El cierre se considera apto para MVP con una observacion operativa: revisar permisos de escritura de `acelerador_panel/backend/tests/results/aggressive_battery_report.json` si se necesita conservar el reporte como evidencia automatica en futuras ejecuciones.
