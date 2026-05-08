# Validacion extract_pdf.php

- Generado: 2026-03-20T12:21:07
- Lint: True
- Uso sin MCP (CLI + libreria): True

## Resumen barrido samples

- Total: 57
- OK: 55
- Errores: 2
- OCR no disponible: 0
- Corruptos: 1
- Protegidos: 1
- Otros errores: 0
- Casos con claves faltantes: 0

## Interface sin MCP

- CLI: `php mcp-server/extract_pdf.php <ruta.pdf>`
- Libreria: `require 'mcp-server/extract_pdf.php'; (new PdfProcessor())->procesarPdf('ruta.pdf');`
- Probe libreria exit code: 0
- Probe libreria output: `["tipo_documento","numero","fecha","total_bi","iva","total_a_pagar","texto_preview"]`
