# Arquitectura MCP + Orquestador (Fase 0)

> Nota de estado (2026-05-06): este documento describe arquitectura y decisiones previas de orquestacion MCP. El estado final implementado para MVP (MCP como capa auxiliar, modo recomendado por defecto `auto`, fallback a `local_only`, `score_final = score_local`, veredicto APTO CON OBSERVACIONES) queda consolidado en `docs/cierre-integracion-mcp-auxiliar-matching-evaluacion-2026-05-06.md`.
## 1) Resumen ejecutivo

El modo estable actual del Proyecto Acelerador es `local_only` y debe mantenerse sin cambios funcionales durante la primera fase de evolucion arquitectonica.

La arquitectura futura introducira un `EvaluationOrchestrator` para soportar tres modos (`local_only`, `mcp_only`, `auto`) sin romper el contrato publico cuando `ok=true`. El MCP actual se documenta como proveedor experimental de laboratorio (`internal_http_v1`), no como proveedor productivo canonico.

Objetivo original de esta fase (historico): dejar definicion tecnica clara y trazable, antes de la implementacion posterior del MVP.

## 2) Estado en el momento de redaccion (historico)

- El sistema funciona en `local_only` como modo real de produccion interna actual.
- El MCP existente es un modulo PHP HTTP autonomo/legacy de extraccion.
- Ese MCP no esta integrado todavia como proveedor canonico del evaluador.
- El MCP actual no devuelve directamente el contrato ANECA canonico como salida publica final.
- El pipeline local/backend actual sigue siendo la via estable.

## 3) Principios de diseno

- Contrato unico hacia frontend: cuando `ok=true`, la aplicacion debe responder con el mismo contenedor/contrato publico independientemente del proveedor interno usado.
- El pipeline local es salvavidas operativo y no se sustituye en la transicion inicial.
- En MVP, MCP se trata como capa auxiliar de matching/contraste/contexto, no como nucleo evaluador.
- MCP-first real queda reservado a `mcp_v2` post-MVP y solo con contrato compatible.
- En `auto` pre-MVP, local es la fuente final y MCP no debe degradar el resultado final.
- `procesar_pdf.php` no debe llamar MCP directamente; el enrutamiento lo decide el orquestador.
- `evaluador/src` no se toca en la fase inicial de introduccion del patron.

## 4) Arquitectura objetivo

Flujo objetivo:

`endpoint/backend actual`
-> `EvaluationOrchestrator`
-> `EvaluationProviderInterface`
-> `LocalEvaluationProvider` | `McpEvaluationProvider`
-> `normalizador`
-> `validador de contrato`
-> `respuesta publica homogenea`

Notas:

- `EvaluationOrchestrator` decide estrategia segun `execution_mode` y estado de salud MCP.
- Cada provider devuelve resultado interno normalizable (exito o error tipificado).
- El normalizador adapta la salida del provider al formato interno comun.
- El validador garantiza cumplimiento del contrato publico antes de responder al cliente.
- `McpEvaluationProvider` es un nombre tecnico temporal y no representa un evaluador ANECA.

## 5) Decision estrategica actual (FASE 4A, 2026-05-05)

- Se adopta estrategia **B ahora + C despues**.
- `internal_http_v1` queda como extractor auxiliar/diagnostico.
- `mcp_v2` queda como diseno futuro para etapa post-MVP.
- Se descarta para MVP forzar adaptacion de `internal_http_v1` al contrato de 11 claves del runtime local.
- `local_only` se mantiene como modo productivo estable.

## Correccion conceptual: MCP no es evaluador (FASE 4A.2, 2026-05-05)

### Regla de arquitectura

- MCP no es nucleo evaluador.
- MCP no sustituye evaluadores ANECA.
- MCP no calcula baremos.
- MCP no decide puntuacion.
- MCP ayuda al matching, contraste, contexto y enriquecimiento de evidencias.

### Separacion de responsabilidades

Nucleo evaluador (propio del proyecto):

- Evaluadores ANECA propios.
- Baremos.
- Contratos canonicos.
- Resultado final.

Capa auxiliar:

- MCP.
- Matching.
- Contraste de datos.
- Enriquecimiento de evidencias y contexto.
- Diagnostico de extraccion.

### Reinterpretacion de modos

- `local_only`: nucleo local sin MCP.
- `mcp_only`: diagnostico MCP/matching aislado, no evaluacion final ANECA.
- `auto`: nucleo local + MCP auxiliar best-effort.

### Aclaracion sobre `McpEvaluationProvider`

- Es un nombre tecnico temporal.
- No representa un evaluador ANECA.
- Puede renombrarse en una fase posterior a `McpMatchingProvider` o `McpAuxiliaryProvider`.
- No se renombra ahora para evitar ruido innecesario en esta etapa.

### Frase institucional recomendada

"El MCP se incorpora como capa auxiliar de matching, contraste y enriquecimiento de contexto. No forma parte del nucleo evaluador ni sustituye a los evaluadores ANECA implementados en el proyecto. La evaluacion final, baremacion y generacion del resultado siguen siendo responsabilidad exclusiva de los evaluadores ANECA propios."

## 6) Estado de modos

### `local_only`

- Modo productivo estable.
- Fuente final oficial de respuesta.
- No depende de MCP para completar la evaluacion.

### `mcp_only`

- Modo de diagnostico experimental.
- Util para validacion tecnica y observabilidad de integracion MCP/matching.
- Si MCP falla, devuelve error controlado sin fallback local.
- No ejecuta ni sustituye evaluacion ANECA final.

### `auto` (estado descrito en este documento, historico)

- En el momento de redaccion: pendiente de implementacion de FASE 4.
- Pre-MVP no significa MCP-first real.
- Su semantica pre-MVP queda redefinida como: `local_only` obligatorio + MCP auxiliar best-effort.

## 7) Definicion de `auto` pre-MVP (auxiliar best-effort)

- `auto = local_only` como base obligatoria + MCP auxiliar best-effort.
- `local_only` no es fallback secundario en MVP.
- `local_only` es la ultima defensa y la fuente de verdad.
- El resultado final que se devuelve al cliente es el resultado local intacto.
- MCP no sustituye `LocalEvaluationProvider` ni la evaluacion ANECA final.
- No cambia contrato publico.
- No expone trazabilidad MCP en respuesta publica.

### Flujo obligatorio de `auto` en MVP

1. Ejecutar `LocalEvaluationProvider` para obtener el resultado final.
2. Si `LocalEvaluationProvider` falla, `auto` debe fallar con el error local.
3. Si `LocalEvaluationProvider` funciona, intentar MCP como capa auxiliar best-effort.
4. Si MCP falla, capturar el fallo y continuar.
5. Clasificar/normalizar respuesta MCP si existe.
6. Registrar trazabilidad interna solo en un lugar seguro.
7. Devolver el resultado local intacto.
8. No cambiar el contrato publico.

Regla operativa:

- MCP falla -> `auto` continua con resultado local.
- Local falla -> `auto` falla, porque el nucleo evaluador ANECA no pudo completar la evaluacion.

## 8) Definicion de `auto` post-MVP (`mcp_v2`)

- MCP puede ser proveedor preferente solo si entrega contrato compatible.
- Debe validar las 11 claves minimas del runtime y/o ANECA canonico equivalente.
- Debe incluir timeout, healthcheck, circuit breaker y fallback local operativo.
- Debe superar tests de compatibilidad antes de habilitarse en produccion.

## 9) Opciones evaluadas (FASE 3D)

- A: adaptar `internal_http_v1` a 11 claves runtime. Estado: descartada para MVP.
- B: usar `internal_http_v1` como extractor auxiliar/diagnostico. Estado: recomendada ahora.
- C: disenar `mcp_v2` real compatible con contrato objetivo. Estado: pospuesta a post-MVP (diseno paralelo permitido).
- D: pausar MCP temporalmente. Estado: solo si hay presion extrema y sin valor visible pre-MVP.

## 10) Riesgos a evitar

- Presentar MCP como evaluador ANECA.
- Duplicar evaluadores o logica de baremacion.
- Usar MCP para decidir puntuaciones finales.
- Duplicar pipeline local de forma encubierta.
- Inventar campos para simular compatibilidad de contrato.
- Romper el contrato publico actual.
- Acoplar frontend a detalles internos de MCP.
- Convertir MCP en punto unico de fallo.
- Activar `auto` antes de tiempo.
- Confundir `auto` auxiliar pre-MVP con MCP-first real.

## 11) Criterios para fase posterior (MCP-first real, historico)

- Contrato MCP compatible con salida objetivo.
- Validacion contra schema/contrato de runtime.
- Fallback local probado end-to-end.
- Timeout MCP probado en condiciones reales.
- Errores MCP tipificados y mapeados.
- No exposicion de rutas internas ni detalles sensibles.
- `local_only` intacto y verificable como baseline.

## 12) Fallos MCP oficiales para fallback (cuando aplique)

- `mcp_unavailable`
- `mcp_timeout`
- `mcp_connection_refused`
- `mcp_invalid_json`
- `mcp_invalid_schema`
- `mcp_empty_response`
- `mcp_partial_response`
- `mcp_internal_error`
- `mcp_auth_error`
- `mcp_rate_limited`
- `mcp_provider_misconfigured`
- `mcp_version_incompatible`
- `mcp_unknown_error`

Regla operativa: en modo `auto` auxiliar pre-MVP, estos fallos no deben degradar la respuesta final local al cliente.

## 13) Trazabilidad y contrato publico

Campos de trazabilidad previstos:

- `modo_solicitado`
- `modo_ejecucion`
- `provider_usado`
- `fallback_activado`
- `motivo_fallback`
- `mcp_disponible`
- `tiempo_mcp_ms`
- `tiempo_local_ms`
- `tiempo_total_ms`
- `contrato_validado`
- `errores`
- `advertencias`

Reglas:

- La trazabilidad MCP se mantiene interna (logs/telemetria), no en respuesta publica.
- No se modifica `docs/schemas/api-response.v1.schema.json` en esta etapa.
- Se evita cualquier cambio de contrato publico durante pre-MVP.

## 14) Configuracion prevista (sin cambio de contrato)

- `ACELERADOR_EXECUTION_MODE=local_only|mcp_only|auto`
- `MCP_PROVIDER=internal_http_v1`
- `MCP_TIMEOUT_MS=5000`
- `MCP_FALLBACK_ENABLED=true`
- `MCP_HEALTHCHECK_ENABLED=true`

Politica actual:

- Default operativo: `ACELERADOR_EXECUTION_MODE=local_only`.
- `internal_http_v1` permanece experimental/laboratorio.

## 15) Recomendacion de fase siguiente en ese momento (FASE 4B, historico)

- Implementar `auto` como nucleo local + MCP auxiliar best-effort para matching/contexto solo si aporta valor visible antes de MVP.
- En esa implementacion, MCP no debe alterar el resultado final ANECA.
- Si no aporta valor visible, mantener `auto` no implementado y cerrar bloque MCP como preparado para post-MVP.

## 16) Plan por fases actualizado

### Fase 0-3 (completadas)

- Arquitectura base, orquestador, modos reconocidos y `mcp_only` diagnostico experimental.

### Fase 4A (actual, completada)

- Decision estrategica formal: B ahora + C despues.
- Redefinicion de semantica `auto` pre-MVP como auxiliar.

### Fase 4B (siguiente)

- Evaluar e implementar `auto` auxiliar best-effort solo si aporta valor tangible pre-MVP.

### Post-MVP

- Diseno/implementacion de `mcp_v2` compatible para habilitar MCP-first real con fallback robusto.

## 17) Archivos/capas que no se deben tocar inicialmente

- `evaluador/src`
- `docs/schemas/*.json`
- `Dockerfile`
- `docker-compose.yml`
- `frontend`
- `procesar_pdf.php` para llamadas directas MCP

