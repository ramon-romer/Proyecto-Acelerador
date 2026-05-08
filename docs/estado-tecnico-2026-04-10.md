# Estado técnico del día

## Cabecera
PROYECTO: Acelerador
DOCUMENTO: Estado técnico del día
FECHA: 2026-04-10
AUTOR: Basilio Lagares
ROL: Desarrollo backend
ESTADO: Cerrado (frentes OCR e inspect-schema)

## 1. Resumen técnico de la jornada
- OCR del evaluador queda cerrado operativamente con fallback real activo.
- OcrProcessor queda desacoplado de mcp-server para tessdata, con resolución neutral.
- inspect-schema queda resuelto en entorno mínimo de backend tutorías.
- Se mantiene la separación entre contrato canónico de la app y capa técnica MCP.

## 2. Módulos o áreas afectadas
- evaluador/src/OcrProcessor.php
- evaluador/.tools/tessdata/spa.traineddata
- docs/estado-tecnico-2026-04-10.md
- docs/sql/bootstrap-minimo-backend-tutorias.sql

## 3. Cambios realizados
- Se consolida la resolución neutral de tessdata en evaluador:
- TESSDATA_PREFIX
- evaluador/.tools/tessdata
- tools/tessdata
- rutas estándar del sistema
- evaluador/.tools/tessdata/spa.traineddata se versiona intencionalmente para reproducibilidad del MVP.
- Se acepta temporalmente la duplicación de spa.traineddata en otra ruta del repo como deuda técnica menor.
- Se consolida el SQL en ruta estable: docs/sql/bootstrap-minimo-backend-tutorias.sql.
- Este documento se mantiene como referencia operativa principal del cierre.
- Las carpetas timestamp de docs/test-runs se consideran evidencia temporal local, no fuente principal versionada.

## 4. Impacto en arquitectura o integración
- El evaluador opera OCR sin dependencia directa de mcp-server para tessdata.
- inspect-schema queda operativo con bootstrap mínimo backend tutorías.
- Alcance explícito del bootstrap: backend tutorías e inspect-schema.
- No debe interpretarse como bootstrap global de toda la aplicación (login/fronten completo queda fuera de alcance).

## 5. Dependencias relevantes
- OCR: tesseract, pdftoppm y tessdata spa en ruta neutral del evaluador.
- Backend tutorías: BD acelerador con tablas mínimas mapeadas para inspect-schema.

## 6. Riesgos y pendientes
- Limitación conocida: el caso sintético scan_probe_no_text puede devolver OCR sin texto útil.
- Deuda técnica menor: duplicación temporal de spa.traineddata.
- Pendiente futuro deseable: consolidar tessdata en ruta neutral compartida (por ejemplo tools/tessdata/) sin acoplar evaluador a mcp-server.

## 7. Próximos pasos
- Mantener este estado técnico y docs/sql/bootstrap-minimo-backend-tutorias.sql como base documental estable del cierre.
- No reabrir frentes cerrados salvo incidencia real nueva.

## 8. Validación y pruebas ejecutadas
- No se han realizado tests en esta ejecución.

## Firma
Documento elaborado por Basilio Lagares como reflejo del estado técnico real del trabajo realizado en la fecha indicada.
