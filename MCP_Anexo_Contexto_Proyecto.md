# MCP - Anexo de contexto del proyecto

Autor: Basilio Lagares  
Fecha de reorganizacion: 2026-04-09

## 1. Objetivo del anexo
Este anexo aporta contexto de arquitectura para entender donde encaja MCP dentro de Acelerador. No define operativa interna de MCP ni checklist de cierre de fase.

## 2. Resumen del flujo ANECA (contexto)
Ruta operativa actual en evaluador (resumen):
`subida PDF -> extraccion texto -> limpieza -> extraccion academica -> JSON -> completado manual opcional -> persistencia`

Estado:
- `implementado`: pipeline funcional en `evaluador/`.
- `parcial / en progreso`: cobertura de algunos subbloques y parametrizacion de metadatos por area.
- `previsto / futuro`: convergencia completa con una ruta de ingesta unificada.

Nota de coherencia:
- `evaluador/src/Pipeline.php` integra fallback OCR con `OcrProcessor`.
- Si falta `tesseract` en entorno, los casos OCR quedan bloqueados por dependencia externa.

## 3. Contrato canonico de la app (contexto)
Contrato canonico vigente en app:
- estructura por `bloque_1`, `bloque_2`, `bloque_3`, `bloque_4`,
- `metadatos_extraccion`,
- `archivo_pdf`, `json_generado`, `texto_extraido`.

Referencia de schema:
- `docs/schemas/contrato-canonico-aneca-v1.schema.json`

Estado:
- `implementado`: contrato canonico v1 y validador de schema.
- `parcial / en progreso`: adopcion transversal por todas las rutas de ingesta.
- `previsto / futuro`: evolucion de versionado mayor (`v2`) cuando haya cambios incompatibles.

## 4. Coexistencia de pipelines
Hoy conviven estas rutas:
1. `mcp-server/` (extraccion tecnica multi-fuente).
2. `evaluador/` (flujo academico ANECA operativo).
3. `meritos/scraping/` (pipeline paralelo/heredado).

Lectura arquitectonica:
- `implementado`: coexistencia real.
- `parcial / en progreso`: convergencia funcional entre rutas.
- `previsto / futuro`: definir oficialmente ruta canonica y papel residual de flujos heredados.

## 5. Relacion con backend y frontend (contexto)
Backend:
- desacoplamiento actual respecto a MCP y punto de extension para integracion progresiva.

Frontend:
- coexistencia de consumo mixto (SQL directo en partes del panel y migracion progresiva a API REST).

Estado:
- `implementado`: backend funcional sin bloquearse por MCP.
- `parcial / en progreso`: integracion de capas y contratos de extremo a extremo.
- `previsto / futuro`: homogeneizar consumo por API y definir entrada documental canonica.

## 6. Deudas tecnicas de integracion
1. Mapeo estable MCP -> contrato canonico ANECA.
2. Parametrizacion de metadatos de comite/subcomite por area.
3. Reducir duplicidad de rutas documentales con responsabilidad parecida.
4. Consolidar validaciones OCR en entorno objetivo.
5. Mantener documentacion sincronizada entre `.md` y artefactos derivados.

## 7. Observaciones de arquitectura general
- La separacion fuente/negocio ya existe, pero no esta cerrada como unica ruta de plataforma.
- MCP no debe evaluarse como sustituto inmediato de todo flujo actual, sino como pieza de convergencia.
- La integracion debe hacerse por capas para preservar operativa ya validada.