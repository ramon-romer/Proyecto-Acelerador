# Arquitectura MCP para Matching Multi-fuente (MATCH 1)

> Nota de estado (2026-05-06): este documento recoge una propuesta arquitectonica previa (MATCH 1) y no describe por si solo el estado final implementado. El cierre tecnico oficial de la integracion MCP auxiliar para MVP (veredicto: APTO CON OBSERVACIONES) se documenta en docs/cierre-integracion-mcp-auxiliar-matching-evaluacion-2026-05-06.md.
Fecha: 2026-05-05  
Estado: propuesta arquitectonica previa (implementacion MCP auxiliar cerrada en 2026-05-06; ver documento de cierre)

## 1) Resumen ejecutivo

En el MVP del Proyecto Acelerador, MCP se incorpora como **capa auxiliar de matching multi-fuente** para ayudar en el cruce, contraste y enriquecimiento de contexto entre profesores, tutores, grupos y evidencias de investigacion.

Regla principal:

- MCP **no** evalua ANECA.
- MCP **no** barema.
- MCP **no** decide puntuaciones.
- MCP **no** sustituye a los evaluadores ANECA propios.

La autoridad de evaluacion final permanece en el nucleo evaluador ANECA.  
El matching multi-fuente se define como capacidad complementaria para recomendacion y afinidad, con baseline local obligatorio y uso MCP best-effort.

## 2) Separacion de responsabilidades

### Nucleo evaluador ANECA (autoridad)

- Baremacion.
- Puntuacion.
- Evaluacion final.
- Contratos canonicos ANECA.
- Reglas por rama/comite.

### Matching multi-fuente (servicio de recomendacion)

- Perfiles de profesores/tutores.
- Grupos de investigacion.
- Lineas/intereses.
- Publicaciones.
- Meritos.
- Datos derivados de evaluaciones ANECA.
- CVs procesados/canonicos.
- Futuras fuentes externas.

### Capa MCP (asistente auxiliar)

- Matching auxiliar.
- Contraste de informacion.
- Enriquecimiento contextual.
- Explicacion de coincidencias.
- Diagnostico tecnico/semantico.
- Nunca autoridad evaluadora.

## 3) Fuentes de datos disponibles

### 3.1 Profesor/usuario/tutor

- Aporta al matching: identidad interna, perfil basico, departamento, contexto de tutoria.
- Identificador principal: `id_profesor` (interno), con apoyo de `id_usuario` cuando aplique.
- Limitaciones: calidad de perfil variable segun completitud de datos.
- Obligatoria u opcional: **obligatoria** para recomendaciones de profesor.

### 3.2 Grupos y asignaciones

- Aporta al matching: relacion tutor-grupo-profesor, pertenencia actual, cobertura de lineas.
- Identificador principal: `id_grupo` + `id_profesor`.
- Limitaciones: no siempre captura afinidad tematica profunda; refleja estado operativo.
- Obligatoria u opcional: **obligatoria** para matching orientado a grupo.

### 3.3 ORCID

- Aporta al matching: vinculo estable entre identidad academica, evaluaciones y CV.
- Identificador principal: `ORCID` / `orcid_candidato` normalizado.
- Limitaciones: puede faltar o estar incompleto en parte del historico.
- Obligatoria u opcional: **preferente** (obligatoria cuando exista; opcional con fallback cuando no exista).

### 3.4 Evaluaciones ANECA persistidas

- Aporta al matching: rama, area, bloques y senales de investigacion ya evaluadas.
- Identificador principal: `orcid_candidato` (preferente), con apoyo por ids internos.
- Limitaciones: datos de evaluacion tienen contexto evaluador; no deben reutilizarse de forma literal para decision automatica de matching.
- Obligatoria u opcional: **opcional** en MVP (muy recomendada si disponible).

### 3.5 `json_entrada` y resultados de evaluacion

- Aporta al matching: evidencias estructuradas de publicaciones, proyectos, meritos y diagnostico.
- Identificador principal: clave de evaluacion + `orcid_candidato`.
- Limitaciones: heterogeneidad por rama/version y necesidad de filtrado de campos sensibles.
- Obligatoria u opcional: **opcional** (fuente de enriquecimiento).

### 3.6 CV procesado/canonico ANECA

- Aporta al matching: palabras clave, lineas, senales de actividad investigadora y evidencia normalizada.
- Identificador principal: `orcid`/`orcid_candidato` + referencia de job/artefacto.
- Limitaciones: calidad dependiente del extractor y disponibilidad del artefacto.
- Obligatoria u opcional: **opcional** en MVP (alta prioridad como enriquecimiento).

### 3.7 Publicaciones/meritos

- Aporta al matching: afinidad tematica, proximidad por produccion cientifica y proyectos.
- Identificador principal: combinacion ORCID + metadatos (doi/keywords/categoria) cuando existan.
- Limitaciones: cobertura parcial y posible sesgo por volumen/documentacion.
- Obligatoria u opcional: **opcional** (recomendado cuando exista).

### 3.8 Futuras fuentes externas

- Aporta al matching: ampliacion de cobertura, validacion cruzada y seÃ±ales adicionales.
- Identificador principal: federado (ORCID u otro id estable mapeado).
- Limitaciones: dependencia externa, latencia y requisitos de privacidad.
- Obligatoria u opcional: **opcional** (post-MVP por fases).

## 4) Identificadores y vinculacion

Politica de vinculacion MVP:

- ORCID es el identificador preferente de continuidad academica.
- `id_profesor`/`id_usuario` gobiernan relaciones internas operativas.
- Correo se usa solo como apoyo; no como clave principal ideal.
- Fallback por nombre/apellidos se admite solo para compatibilidad legacy y con baja confianza.

Relacion conceptual:

`profesor (id_profesor, ORCID)` <-> `evaluacion ANECA (orcid_candidato, json_entrada, resultado)` <-> `CV/canonico` <-> `grupo (id_grupo)` <-> `tutoria/asignacion`.

## 5) Arquitectura objetivo MVP

Flujo conceptual obligatorio:

`Tutor/admin solicita recomendacion`  
-> `ResearchProfileAggregator` reune datos locales  
-> `ResearchGroupMatchingService` calcula baseline local  
-> `McpMatchingAssistant` intenta enriquecimiento best-effort  
-> `MatchingOrchestrator` combina baseline + contexto MCP  
-> `Backend` devuelve recomendacion estable  
-> `Frontend` muestra candidatos sin depender de MCP

Reglas de arquitectura:

- Baseline local obligatorio.
- MCP solo auxiliar.
- Si MCP falla, baseline local continua.
- No cambiar contrato publico sin version.
- No acoplar frontend a MCP.

## 6) Componentes propuestos (documento base previo; implementacion ya cerrada para MVP)

### ResearchProfileAggregator

- Reune y normaliza perfil multi-fuente local.
- Aplica politicas de disponibilidad por fuente.
- Produce estructura interna homogena para matching.

### ResearchGroupMatchingService

- Calcula `score_local` y ranking base.
- Ejecuta reglas transparentes y auditables.
- No usa decisiones opacas externas como autoridad.

### MatchingOrchestrator

- Coordina baseline local + intento MCP.
- Enforce de modos (`local_only`, `mcp_only`, `auto`).
- Aplica reglas de fallback y resiliencia.

### McpMatchingAssistant

- Consume payload minimizado.
- Devuelve enriquecimiento contextual y sugerencias.
- No emite resultado vinculante de evaluacion.

### MatchingPrivacyFilter

- Minimiza payload antes de MCP.
- Elimina PII no necesaria.
- Registra politica aplicada por solicitud.

### MatchingTrace

- Registra fuentes usadas, faltantes y estado MCP.
- Deja evidencia tecnica para auditoria interna.
- No expone detalle interno al frontend por defecto.

### CandidateRecommendationService

- Construye respuesta final estable para backend.
- Resuelve `score_final` segun reglas locales transparentes.
- Asegura continuidad funcional aun con fallo MCP.

## 7) Modos de funcionamiento

### `local_only`

- Matching local/manual/baseline.
- No usa MCP.
- Ultima defensa operativa.

### `mcp_only`

- Diagnostico MCP de matching/contexto.
- No modo de produccion.
- No evalua ANECA.
- No crea recomendacion final en ausencia de baseline local.

### `auto`

- Baseline local obligatorio.
- MCP auxiliar best-effort.
- Si MCP falla, baseline local sigue.
- Si baseline local falla, falla la recomendacion.
- MCP no decide unilateralmente el resultado.

## 8) Contrato interno v0 de matching (no publico)

Identificador de version propuesto: `matching-multifuente-v0`.

```json
{
  "ok": true,
  "version": "matching-multifuente-v0",
  "modo": "auto",
  "grupo_objetivo": {
    "id": null,
    "nombre": null,
    "lineas_investigacion": [],
    "palabras_clave": []
  },
  "fuentes_usadas": {
    "perfil_profesor": true,
    "grupos": true,
    "evaluacion_aneca": true,
    "cv_procesado": true,
    "publicaciones": true,
    "fuentes_externas": false
  },
  "candidatos": [
    {
      "profesor_id": "",
      "nombre_mostrable": "",
      "orcid": "",
      "rama": "",
      "areas": [],
      "lineas_investigacion": [],
      "coincidencias": [],
      "evidencias": [],
      "score_local": 0,
      "score_mcp": null,
      "score_final": 0,
      "motivos": [],
      "advertencias": []
    }
  ],
  "trazabilidad_interna": {
    "mcp_intentado": false,
    "mcp_disponible": null,
    "motivo_mcp": null,
    "fuentes_faltantes": [],
    "datos_minimizados": true
  }
}
```

Reglas del score:

- `score_local` es autoridad inicial.
- `score_mcp` es auxiliar (contexto/enriquecimiento).
- `score_final` sale de reglas locales transparentes.
- MCP no decide unilateralmente `score_final`.

## 9) Reglas iniciales de baseline local

Criterios propuestos de baseline:

- Coincidencia de rama/area.
- Coincidencia de lineas de investigacion.
- Publicaciones relacionadas.
- Proyectos relacionados.
- Palabras clave del CV/canonico.
- Disponibilidad o pertenencia a grupos.
- Relacion tutor/profesor.
- Senales de evaluacion ANECA relacionadas con investigacion.

Advertencia obligatoria:

- No convertir la puntuacion ANECA en decision automatica de matching sin contexto.

## 10) Politica de datos minimos y privacidad

Reglas MVP de minimizacion:

- No enviar CV completo al MCP por defecto.
- No enviar DNI, telefono, direccion ni datos innecesarios.
- Evitar correo completo salvo necesidad justificada.
- Preferir ORCID/pseudonimo/hash cuando sea viable.
- Enviar keywords, areas, lineas, resumenes y metricas.
- Guardar que fuentes se usaron en trazabilidad interna.
- No exponer trazabilidad MCP al frontend salvo contrato explicito.
- Cualquier fuente externa futura pasa por `MatchingPrivacyFilter`.

## 11) Relacion con evaluaciones ANECA

Uso permitido en matching:

- Evaluaciones ANECA alimentan contexto de afinidad.
- Datos utiles: rama, area, publicaciones, proyectos, meritos de investigacion, evidencias y bloques relacionados.

Uso no permitido:

- MCP no reevalua datos ANECA.
- MCP no cambia puntuacion ANECA.
- MCP no sustituye la decision del evaluador ANECA.

Resultado:

- MCP ayuda a detectar afinidades y compatibilidades, no a emitir evaluacion ANECA.

## 12) Riesgos y mitigaciones

- Riesgo: confundir MCP con evaluador.  
  Mitigacion: separar explicitamente autoridad ANECA vs capa auxiliar en codigo y docs.

- Riesgo: acoplar frontend a MCP.  
  Mitigacion: contrato backend estable e independiente de disponibilidad MCP.

- Riesgo: enviar demasiados datos personales.  
  Mitigacion: `MatchingPrivacyFilter` obligatorio con minimizacion por defecto.

- Riesgo: depender de MCP para crear grupos/recomendaciones.  
  Mitigacion: baseline local obligatorio y fallback operativo.

- Riesgo: no tener baseline local.  
  Mitigacion: `ResearchGroupMatchingService` como precondicion de `auto`.

- Riesgo: duplicar logica ANECA.  
  Mitigacion: prohibicion expresa de baremacion/puntuacion en MCP.

- Riesgo: vinculacion ORCID incompleta.  
  Mitigacion: estrategia gradual ORCID-first + fallback legacy trazado.

- Riesgo: usar puntuaciones ANECA fuera de contexto.  
  Mitigacion: solo senales agregadas/relativas; nunca auto-decision directa.

- Riesgo: contrato interno no versionado.  
  Mitigacion: version fija `matching-multifuente-v0` y evolucion versionada.

- Riesgo: falta de trazabilidad.  
  Mitigacion: `MatchingTrace` con fuentes usadas, estado MCP y faltantes.

## 13) Plan de implementacion por fases (historico)

- MATCH 1: documentacion actual (este documento).
- MATCH 2: `ResearchProfileAggregator` local.
- MATCH 3: baseline local de matching.
- MATCH 4: MCP auxiliar best-effort.
- MATCH 5: integracion backend/panel.
- MATCH 6: fuentes externas futuras.
- MATCH 7: contrato versionado y tests de regresion.

## 14) Criterios de aceptacion previos (historico, previos a la implementacion ya cerrada)

- Documento aprobado.
- Fuentes de datos confirmadas.
- Identificador principal elegido (ORCID preferente).
- Baseline local definido.
- Payload minimo MCP definido.
- Riesgos de privacidad aceptados.
- No tocar evaluadores ANECA.
- No tocar login.
- No cambiar contratos publicos sin version.

## 15) Archivos candidatos a tocar en fases posteriores (orientativo)

- `acelerador_panel/backend/src/Application/...`
- `acelerador_panel/backend/src/Infrastructure/Persistence/...`
- `acelerador_panel/backend/src/Infrastructure/Repositories/...`
- `acelerador_panel/backend/src/Presentation/Routes/...`
- `docs/...`
- `tools/smokes` futuros

Nota: rutas concretas se confirmaran por fase. Este MATCH 1 fue la base documental previa a la implementacion.

## 16) Archivos que no deben tocarse inicialmente

- `evaluador/src/*`
- `mcp-server/*`
- `docs/schemas/*`
- frontend para logica de matching
- login
- `Dockerfile`
- `docker-compose.yml`

## Contexto de alineacion (sin rediseno)

- La documentacion base de orquestacion MCP ya existe en `docs/arquitectura-mcp-orquestador.md`.
- La primera arquitectura tecnica MCP en `meritos/scraping/src/Evaluation/` se mantiene como contexto y **no** se reabre en MATCH 1.
- Este documento se limita a definir arquitectura de matching multi-fuente asistido por MCP para MVP, sin tocar runtime ni contratos publicos.

