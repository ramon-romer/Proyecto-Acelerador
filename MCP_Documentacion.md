# MCP en Acelerador: documentacion operativa

Autor: Basilio Lagares  
Fecha de reorganizacion: 2026-04-09

## 1. Objetivo del documento
Este documento describe solo el modulo MCP de Acelerador (`mcp-server/`): que hace hoy, como funciona internamente, que contrato tecnico entrega y como se relaciona con el resto del sistema.

No cubre en detalle:
- flujo funcional completo ANECA,
- checklist de cierre de fase SIN MCP,
- estado operativo detallado de backend/frontend fuera del papel de MCP.

Esos temas se mantienen en:
- `MCP_Anexo_Contexto_Proyecto.md`
- `MCP_Anexo_DONE_SIN_MCP.md`

## 2. Que es MCP en Acelerador
En este repositorio, MCP es un modulo concreto en `mcp-server/` que actua como capa intermedia entre fuentes heterogeneas y consumidores internos.

Responsabilidad principal de MCP:
- recibir una fuente (`pdf` o `db`),
- extraer contenido util,
- normalizarlo a un contrato JSON tecnico fijo,
- exponer el resultado por CLI/HTTP,
- y soportar ejecucion sincronica o asincronica.

## 3. Papel real actual de MCP
Estado `implementado`:
- modulo autonomo en `mcp-server/` con endpoints HTTP y ejecucion CLI,
- extraccion PDF nativa + OCR,
- ingesta de fuente `db`,
- cola de jobs asincronos con `worker_jobs.php`,
- contrato tecnico de salida estable (7 claves).

Estado `parcial / en progreso`:
- MCP no es todavia la ruta unica de ingesta documental de toda la app,
- no todas las rutas de negocio consumen directamente salida MCP,
- mapeo transversal MCP -> contrato canonico de app aun no cerrado extremo a extremo.

Estado `previsto / futuro`:
- usar MCP como frontera comun de integracion documental para mas fuentes y mas consumidores,
- ampliar orquestacion multi-fuente con reglas de dominio.

## 4. Fuentes de entrada
### Implementado
- `pdf`: via `POST /extract-pdf` y flujo interno de analisis/extraccion.
- `db`: via `POST /extract-data` (lectura por PDO segun configuracion).

### Parcial / en progreso
- soporte y cobertura funcional dependen de dependencias de entorno OCR en cada despliegue.

### Previsto / futuro
- fuentes externas tipo ANECA, Dialnet y otras, segun configuraciones y reglas de orquestacion documentadas en material tecnico del modulo.

## 5. Flujo interno del MCP
Flujo base:
`fuente (pdf|db) -> extraccion -> normalizacion minima -> DocumentoExtractor -> contrato JSON tecnico`

Secuencia operativa (PDF):
1. Entrada por CLI o HTTP.
2. Diagnostico de archivo (tamano, paginas, viabilidad sync/async).
3. Extraccion de texto nativo.
4. Fallback OCR si texto nativo es insuficiente.
5. Extraccion de campos por `DocumentoExtractor`.
6. Entrega de resultado directo o en job asincrono.

Secuencia operativa (DB):
1. Entrada por HTTP `POST /extract-data`.
2. Lectura de origen configurado.
3. Mapeo minimo a estructura de salida tecnica.

## 6. Tratamiento de PDFs y OCR
### Implementado
- Extraccion nativa PDF con `smalot/pdfparser`.
- OCR con `pdftoppm` + `tesseract` cuando el texto nativo no alcanza umbral util.
- Parametros de control en entorno:
- `MAX_OCR_PAGES`
- `OCR_BATCH_SIZE`

### Parcial / en progreso
- robustez OCR dependiente de instalacion/disponibilidad de binarios en entorno.

### Previsto / futuro
- endurecer cobertura OCR en todos los entornos objetivo con evidencia recurrente en CI/validaciones de integracion.

## 7. Modos de ejecucion y procesamiento sync/async
### Modos de ejecucion implementados
- HTTP:
- `POST /extract-pdf`
- `POST /extract-data`
- consulta de jobs asincronos por `GET /jobs/{job_id}`
- CLI/script:
- procesamiento directo
- worker de cola: `php mcp-server/worker_jobs.php --once|--loop`

### Criterio sync/async implementado
- Sync cuando el PDF cumple umbrales de tamano/paginas.
- Async cuando supera umbrales operativos.

Parametros relevantes (`implementado`):
- `MAX_SYNC_PDF_BYTES`
- `MAX_SYNC_PAGES`

## 8. Contrato tecnico de salida MCP
Contrato tecnico actual (`implementado`):

```json
{
  "tipo_documento": "FACTURA | null",
  "numero": "string | null",
  "fecha": "string | null",
  "total_bi": "string | null",
  "iva": "string | null",
  "total_a_pagar": "string | null",
  "texto_preview": "string"
}
```

Propiedades operativas:
- las 7 claves se mantienen siempre,
- `texto_preview` es obligatorio como `string`,
- resto de claves puede venir en `null`,
- existe deteccion de faltantes funcionales (`faltantes`) en respuesta de API.

## 9. Relacion entre contrato tecnico MCP y contrato canonico de la app
Estado real:
- MCP produce un contrato tecnico de extraccion, util para ingesta y desacoplamiento de fuente.
- La app (flujo ANECA) usa como contrato canonico otro JSON mas rico por bloques.

Decision vigente documentada:
- mantener ambos contratos separados por responsabilidad,
- mapear MCP -> contrato canonico cuando MCP entre en rutas de negocio de app.

Estado:
- `implementado`: ambos contratos existen.
- `parcial / en progreso`: mapeo transversal y adopcion completa en todos los consumidores.

## 10. Integracion actual con el resto del sistema
Integracion actual, en terminos estrictos de MCP:
- con backend: desacoplamiento intencional y punto de extension para integrar eventos/ingesta sin forzar dependencia prematura.
- con frontend: no hay consumo generalizado directo de MCP como unica via de datos.
- con evaluador ANECA: hoy existe coexistencia; el evaluador opera su pipeline principal y MCP sigue como modulo separado.

Lectura correcta del estado:
- `implementado`: MCP como pieza autonoma.
- `parcial / en progreso`: convergencia de todas las rutas de ingesta sobre MCP.

## 11. Limitaciones y riesgos
Limitaciones actuales:
- contrato tecnico MCP deliberadamente pequeno para extraccion generica,
- cobertura semantica insuficiente para negocio ANECA sin mapeo adicional,
- dependencia de utilidades externas para OCR.

Riesgos de integracion:
- mantener varios contratos sin frontera clara,
- mezclar capa de extraccion tecnica con reglas de negocio,
- forzar integracion MCP sin cerrar previamente criterios base de fase SIN MCP.

Incoherencia corregida en esta reorganizacion:
- se unifica el criterio de OCR en evaluador: `Pipeline.php` si integra fallback con `OcrProcessor`; el bloqueo actual es de dependencia de entorno (por ejemplo `tesseract`), no de ausencia de codigo.

## 12. Decisiones abiertas
1. Definir alcance exacto del mapeo MCP -> contrato canonico ANECA v1.
2. Decidir estrategia de convivencia temporal de pipelines durante la transicion.
3. Definir criterio operativo para declarar MCP como ruta preferente o unica por dominio.
4. Cerrar politica de versionado de contratos cuando aparezca una linea `v2`.

## 13. Roadmap de integracion MCP
Fase 1 (`parcial / en progreso`):
- mantener MCP estable como capa tecnica de extraccion,
- congelar contrato tecnico MCP y reglas de faltantes,
- consolidar validaciones reproducibles de OCR en entorno objetivo.

Fase 2 (`parcial / en progreso`):
- implementar adaptador de mapeo MCP -> contrato canonico de app,
- validar compatibilidad con schema canonico en pruebas de integracion.

Fase 3 (`previsto / futuro`):
- integrar consumidores clave para reducir rutas paralelas,
- definir oficialmente la ruta canonica de ingesta documental.

Fase 4 (`previsto / futuro`):
- ampliar fuentes externas y orquestacion multi-fuente,
- versionar contratos adicionales sin romper consumidores existentes.

## 14. Resumen ejecutivo final
MCP en Acelerador ya esta implementado como modulo tecnico autonomo para extraccion y normalizacion minima de fuentes heterogeneas (`pdf` y `db`), con soporte sync/async y OCR.

Hoy su rol principal es desacoplar origen y consumo tecnico. Aun no es la unica ruta documental de toda la app, por lo que la prioridad no es reescribir su base, sino cerrar su integracion progresiva con el contrato canonico de negocio y con los consumidores finales.