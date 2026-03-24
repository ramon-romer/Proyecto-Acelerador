# MCP Server - Extraccion y Scraping de Documentos

Este modulo centraliza la extraccion de texto y campos de negocio desde distintas fuentes de datos.

Fuentes soportadas:
- PDF de texto
- PDF de imagen (OCR)
- PDF hibrido (combinacion nativo + OCR)
- Base de datos (via PDO + SQL)

Salida final (contrato JSON fijo):
- `tipo_documento`
- `numero`
- `fecha`
- `total_bi`
- `iva`
- `total_a_pagar`
- `texto_preview`

## Flujo funcional

1. Entrada de fuente (`pdf` o `db`).
2. Extraccion de texto:
- En PDF: nativo (`smalot/pdfparser`) + OCR (`pdftoppm` + `tesseract`) cuando hace falta.
- En DB: consulta SQL y construccion de texto desde columna textual o desde pares `columna: valor`.
3. Extraccion de campos de negocio (regex).
4. Salida JSON con contrato fijo.
5. Si faltan campos clave, se avisa por `stderr`:
- `Te falta este campo: <campo>. Debes introducirlo manualmente.`

## Uso por CLI

Modo legacy (compatibilidad):
```bash
php mcp-server/extract_pdf.php mcp-server/pdf/prueba.pdf
```

Fuente PDF explicita:
```bash
php mcp-server/extract_pdf.php --fuente=pdf --ruta=mcp-server/pdf/prueba.pdf
```

Fuente DB por flags:
```bash
php mcp-server/extract_pdf.php --fuente=db --dsn=sqlite:mcp-server/resultados/fuente_test.db "--query=SELECT texto FROM docs" --text_column=texto
```

Fuente DB por archivo de config:
```bash
php mcp-server/extract_pdf.php --fuente=db --config=mcp-server/resultados/fuente_db_config.json
```

Ejemplo de `fuente_db_config.json`:
```json
{
  "dsn": "sqlite:mcp-server/resultados/fuente_test.db",
  "query": "SELECT texto FROM docs",
  "text_column": "texto",
  "max_rows": 1000,
  "max_text_chars": 2000000,
  "query_timeout_seconds": 30
}
```

Notas:
- `--params=<json>` permite enviar parametros para consultas preparadas.
- `--max_rows=<n>` limita filas leidas desde DB.
- `--max_text_chars=<n>` limita el texto total construido desde DB.
- `--query_timeout_seconds=<n>` corta consultas/procesado muy lentos.
- Para multi-fuente (ANECA, Dialnet, etc.) puedes mantener un JSON por fuente en `mcp-server/resultados/`:
  - `fuente_db_config_aneca.example.json`
  - `fuente_db_config_dialnet.example.json`

## Integracion como libreria

```php
require 'mcp-server/extract_pdf.php';

$processor = new PdfProcessor();
$resultado = $processor->procesarFuente([
    'tipo' => 'pdf',
    'ruta' => 'mcp-server/pdf/prueba.pdf'
]);
```

Para DB:
```php
$resultado = $processor->procesarFuente([
    'tipo' => 'db',
    'dsn' => 'sqlite:ruta.db',
    'query' => 'SELECT texto FROM docs',
    'text_column' => 'texto'
]);
```

## Dependencias tecnicas

PHP:
- `smalot/pdfparser`
- `PDO` y driver de base de datos segun fuente (`pdo_sqlite`, `pdo_mysql`, etc.)

OCR:
- `pdftoppm` (Poppler)
- `tesseract`
- modelo `spa.traineddata` en `TESSDATA_PREFIX`

Variables de entorno OCR recomendadas:
- `PDFTOPPM_PATH`
- `TESSERACT_PATH`
- `TESSDATA_PREFIX`

## Estructura relevante

- `mcp-server/extract_pdf.php`: motor principal multi-fuente.
- `mcp-server/ocr_probe.php`: probe para ejecutar OCR forzado en tests.
- `mcp-server/tests/unit_extract_pdf.php`: tests unitarios.
- `mcp-server/test_extract_pdf.ps1`: bateria funcional general.
- `mcp-server/test_extract_pdf_ocr_aggressive.ps1`: bateria OCR agresiva.
- `mcp-server/resultados/`: reportes JSON/MD de validacion.

## Testing

Unit tests:
```bash
php mcp-server/tests/unit_extract_pdf.php
```

Bateria funcional:
```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File mcp-server/test_extract_pdf.ps1
```

Bateria OCR agresiva:
```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File mcp-server/test_extract_pdf_ocr_aggressive.ps1 -Iterations 20
```

Resultados detallados de la ultima bateria "bestia":
- Ver `mcp-server/TESTS_RESULTS.md`
- Ver reportes en `mcp-server/resultados/`

## Registro de avances

- Registro tecnico de la sesion actual:
  - `mcp-server/REGISTRO_TECNICO_2026-03-23.md`

## Documentacion MVP (2026-03-24)

- `docs/2026-03-24-resumen-trabajo.md`
- `docs/estado-tecnico-mvp.md`
- `docs/estrategia-testing-mvp.md`
