# MCP - Anexo de estado de fase SIN MCP

Autor: Basilio Lagares  
Fecha de reorganizacion: 2026-04-09

## 1. Objetivo del anexo
Concentrar criterios de cierre, checklist, evidencias, bloqueos externos y pendientes de la fase base SIN MCP.

## 2. Definicion de DONE SIN MCP
`DONE SIN MCP` se alcanza cuando los criterios minimos de contrato, flujo funcional y validaciones base estan en estado `ya cumplido`, sin depender de MCP como ruta principal.

## 3. Checklist de estado actual
### Contrato y datos
- `ya cumplido`: contrato canonico ANECA v1 definido y documentado.
- `ya cumplido`: validacion automatica por schema (`evaluador/tests/validate_canonical_schema.php`).
- `ya cumplido`: lote actual en verde (`76/76` JSON validos).
- `pendiente`: parametrizacion completa de metadatos por area/comite en extractor comun.

### Flujo funcional
- `ya cumplido`: flujo ANECA SIN MCP operativo de subida a persistencia de `json_entrada`.
- `ya cumplido`: fallback OCR integrado en pipeline principal.
- `pendiente con dependencia externa`: evidencia OCR completa en entorno con `tesseract` disponible.

### Backend y frontend
- `ya cumplido`: backend modular operativo sin MCP.
- `pendiente`: cierre de rutas criticas frontend sin SQL directo en UI.
- `pendiente con dependencia externa`: validaciones sobre BD objetivo y e2e real de entorno.

### Documentacion y cierre
- `ya cumplido`: criterio de DONE SIN MCP definido y publicado.
- `pendiente`: mantener sincronizados artefactos derivados cuando cambie el `.md`.

## 4. Evidencias tecnicas relevantes
1. `evaluador/tests/validate_canonical_schema.php` -> `passed=76 failed=0 total=76`.
2. `evaluador/tests/validate_ocr_fallback.php` -> casos `PASS` y `BLOCKED_DEPENDENCY` segun disponibilidad OCR.
3. `docs/schemas/contrato-canonico-aneca-v1.schema.json` como referencia de validacion canonica.

## 5. Pruebas minimas de cierre
Comandos de referencia:
- `php evaluador/tests/validate_canonical_schema.php`
- `php evaluador/tests/validate_ocr_fallback.php`

Interpretacion de salida OCR:
- `PASS`: caso verificado con comportamiento esperado.
- `BLOCKED_DEPENDENCY`: falta dependencia de entorno OCR (tipicamente `tesseract`).
- `FAIL`: fallo funcional en pipeline/contrato.

## 6. Bloqueos por dependencias externas
1. Entorno sin `tesseract` operativo para cierre final de evidencia OCR.
2. Disponibilidad de BD objetivo para verificaciones de esquema/flujo final.
3. Entorno e2e completo para pruebas integradas finales.

## 7. Pendientes antes de pasar a integracion MCP
1. Ejecutar validacion OCR final con dependencias completas y registrar evidencia reproducible.
2. Cerrar parametrizacion de metadatos de comite/subcomite por area.
3. Completar cierre de rutas frontend criticas hacia API.
4. Confirmar validaciones pendientes dependientes de BD objetivo.

## 8. Estado global
Estado global de fase SIN MCP: `aun no cerrado`.

Motivo principal de no cierre:
- falta evidencia final OCR en entorno operativo completo, junto con pendientes de integracion base no documentales.