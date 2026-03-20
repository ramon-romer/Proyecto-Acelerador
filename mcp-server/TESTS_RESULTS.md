# Resultados de Testing (Bateria Bestia)

Fecha de ejecucion: 2026-03-20

Este documento resume la bateria mas intensa ejecutada sobre el modulo de extraccion/scraping en `mcp-server`.

## 1) Unit tests (repetidos)

Comando base:
```bash
php mcp-server/tests/unit_extract_pdf.php
```

Ejecucion:
- Corridas: 5
- Corridas OK: 5/5
- Resultado por corrida: `passed=13 failed=0`

Cubre:
- Extraccion regex de campos de negocio.
- Contrato de salida.
- Parser de argumentos CLI.
- Fuente DB (SQLite).
- Fuzz test de robustez (200 iteraciones por corrida).

## 2) Bateria funcional general

Comando:
```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File mcp-server/test_extract_pdf.ps1
```

Resumen (reporte `validacion_extract_pdf_report.md`):
- Total PDFs en barrido: 57
- OK: 55
- Errores: 2
- OCR no disponible: 0
- Corruptos: 1
- Protegidos: 1
- Casos con claves faltantes en exitos: 0
- Uso sin MCP: True

Interpretacion:
- Los 2 errores corresponden a casos esperables (archivo corrupto y PDF protegido).
- No hay rotura de contrato JSON en casos exitosos.

## 3) Bateria OCR agresiva (alta carga)

Comando:
```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File mcp-server/test_extract_pdf_ocr_aggressive.ps1 -Iterations 20
```

Resumen (reporte `validacion_ocr_agresiva_report.md`):
- Archivos target OCR: 6
- Iteraciones por archivo: 20
- Full OK / total: 120 / 120
- Forced OCR OK / total: 80 / 120
- Forced OCR vacio (warning): 40
- Forced OCR errores duros: 0
- Duracion media full: 1069.95 ms
- Duracion p95 full: 3154.48 ms
- HardFail: False

Interpretacion:
- No hay fallos duros OCR.
- Hay warnings de OCR vacio en una parte del modo forced (casos limite), pero el flujo full mantiene estabilidad.

## 4) Stress adicional continuo (PDF + DB)

Stress PDF:
- Archivos por ciclo: 6
- Ciclos: 40
- Total ejecuciones: 240
- OK: 240
- Errores: 0
- JSON invalido: 0
- Fallos de contrato: 0

Stress DB:
- Total ejecuciones: 80
- OK: 80
- Errores: 0
- JSON invalido: 0
- Fallos de contrato: 0

## 5) Conclusion global

Estado actual:
- Estable y apto para continuar integracion.
- Contrato JSON consistente en exitos.
- OCR operativo en entorno real.
- Sin fallos intermitentes detectados en stress continuo.

Riesgos conocidos:
- PDFs corruptos o protegidos siguen devolviendo error controlado (esperado).
- Algunos casos limite en forced OCR pueden devolver texto vacio (warning, no hard fail).
